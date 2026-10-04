<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile;

use GGermanBoldyrev\SchemaFile\Console\SchemaFileCommand;
use GGermanBoldyrev\SchemaFile\Listeners\GenerateSchemaFile;
use GGermanBoldyrev\SchemaFile\Mapper\ColumnMapperRegistry;
use GGermanBoldyrev\SchemaFile\Mapper\Drivers\SqliteColumnMapper;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the package into a Laravel application: its config, its settings object, its column mappers, its command and its migration listener.
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

        // Extend this binding to support another database driver or replace a mapper.
        $this->app->singleton(
            ColumnMapperRegistry::class,
            fn (): ColumnMapperRegistry => new ColumnMapperRegistry([
                'sqlite' => new SqliteColumnMapper,
            ]),
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

        $this->app->make(Dispatcher::class)->listen(MigrationsEnded::class, GenerateSchemaFile::class);
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            self::CONFIG_PATH => $this->app->configPath(self::CONFIG_KEY.'.php'),
        ], 'schema-file-config');
    }
}
