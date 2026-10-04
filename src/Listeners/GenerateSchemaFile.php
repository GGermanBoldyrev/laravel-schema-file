<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Listeners;

use GGermanBoldyrev\SchemaFile\Console\ConsoleNotifier;
use GGermanBoldyrev\SchemaFile\GenerationResult;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use GGermanBoldyrev\SchemaFile\SchemaFileGenerator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\MigrationsEnded;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The automatic entry point: rewrites the schema file once migrations have run or been rolled back.
 *
 * Everything here is about whether to write at all; the writing itself is the generator's.
 */
final readonly class GenerateSchemaFile
{
    /**
     * @param  string  $defaultConnection  The application's default connection as configured. While
     *                                     migrations run, Laravel makes the migrated connection the
     *                                     default, so it cannot be asked for at that point.
     */
    public function __construct(
        private SchemaFileGenerator $generator,
        private SchemaFileConfig $config,
        private DatabaseManager $connections,
        private LoggerInterface $logger,
        private ConsoleNotifier $console,
        private string $defaultConnection,
    ) {
    }

    public function handle(MigrationsEnded $event): void
    {
        if (! $this->config->enabled || $this->isPretending($event) || ! $this->migratedOwnConnection()) {
            return;
        }

        // The migrations have already been applied: a schema file that could not
        // be written must not make them look like they failed.
        try {
            $result = $this->generator->generate($this->config);
        } catch (Throwable $exception) {
            $this->logger->warning('The schema file could not be written after running migrations.', [
                'exception' => $exception,
            ]);

            $this->console->warn("The schema file was not written. {$exception->getMessage()}");

            return;
        }

        if ($result === GenerationResult::Written) {
            $this->console->info("Schema file written to [{$this->config->path}].");
        }
    }

    private function isPretending(MigrationsEnded $event): bool
    {
        return (bool) ($event->options['pretend'] ?? false);
    }

    /**
     * Whether the migrations ran on the connection the schema file describes, not on another one.
     */
    private function migratedOwnConnection(): bool
    {
        return $this->connections->getDefaultConnection() === ($this->config->connection ?? $this->defaultConnection);
    }
}
