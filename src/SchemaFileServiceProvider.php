<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile;

use GGermanBoldyrev\SchemaFile\Console\ConsoleNotifier;
use GGermanBoldyrev\SchemaFile\Console\Operations\Modes\CheckSchemaFile;
use GGermanBoldyrev\SchemaFile\Console\Operations\Modes\WriteSchemaFile;
use GGermanBoldyrev\SchemaFile\Console\Operations\OperationRegistry;
use GGermanBoldyrev\SchemaFile\Console\SchemaFileCommand;
use GGermanBoldyrev\SchemaFile\Listeners\GenerateSchemaFile;
use GGermanBoldyrev\SchemaFile\Reader\Columns\ColumnMapperRegistry;
use GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers\MySqlColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers\PostgresColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers\SqliteColumnMapper;
use Illuminate\Config\Repository;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the package into a Laravel application: its config, its settings object, its column mappers, its command with its operations and its migration listener.
 */
final class SchemaFileServiceProvider extends ServiceProvider
{
    private const string CONFIG_KEY = 'schema-file';

    private const string CONFIG_PATH = __DIR__.'/../config/schema-file.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, self::CONFIG_KEY);

        // Built on every resolution, so a config value changed at runtime is picked up.
        $this->app->bind(
            SchemaFileConfig::class,
            fn (Application $app): SchemaFileConfig => SchemaFileConfig::fromRepository(
                $app->make(Repository::class),
                local: $app->environment('local') === true,
            ),
        );

        // One instance, so that the output kept when a command starts is there when migrations end.
        $this->app->singleton(ConsoleNotifier::class);

        // Extend this binding to support another database driver or replace a mapper.
        $this->app->singleton(
            ColumnMapperRegistry::class,
            fn (): ColumnMapperRegistry => new ColumnMapperRegistry([
                'sqlite' => new SqliteColumnMapper,
                'mysql' => new MySqlColumnMapper,
                'mariadb' => new MySqlColumnMapper,
                'pgsql' => new PostgresColumnMapper,
            ]),
        );

        // Built for each run of the command, so its operations get a generator made from the bindings
        // as they are then. Writing is what runs when the command line asks for nothing else.
        $this->app->bind(
            OperationRegistry::class,
            fn (Application $app): OperationRegistry => new OperationRegistry(
                fallback: $app->make(WriteSchemaFile::class),
                operations: [
                    $app->make(CheckSchemaFile::class),
                ],
            ),
        );
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->registerCommands();
        $this->registerListeners();
        $this->registerPublishing();
    }

    private function registerCommands(): void
    {
        $this->commands([
            SchemaFileCommand::class,
        ]);
    }

    private function registerListeners(): void
    {
        // Captured now, before any migration command temporarily changes the default connection.
        $this->app->when(GenerateSchemaFile::class)
            ->needs('$defaultConnection')
            ->give($this->app->make(Repository::class)->string('database.default'));

        $events = $this->app->make(Dispatcher::class);

        $events->listen(CommandStarting::class, [ConsoleNotifier::class, 'capture']);
        $events->listen(CommandFinished::class, [ConsoleNotifier::class, 'release']);
        $events->listen(MigrationsEnded::class, GenerateSchemaFile::class);
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            self::CONFIG_PATH => $this->app->configPath(self::CONFIG_KEY.'.php'),
        ], 'schema-file-config');
    }
}
