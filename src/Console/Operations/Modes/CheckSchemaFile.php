<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console\Operations\Modes;

use GGermanBoldyrev\SchemaFile\Console\Operations\Operation;
use GGermanBoldyrev\SchemaFile\Console\Operations\OperationResult;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use GGermanBoldyrev\SchemaFile\SchemaFileGenerator;

/**
 * Writes nothing and fails when the schema file does not match the database: what --check asks for.
 */
final readonly class CheckSchemaFile implements Operation
{
    public function __construct(
        private SchemaFileGenerator $generator,
    ) {
    }

    public function supports(array $options): bool
    {
        // Truthy rather than true: a caller of Artisan::call() may pass 1 or "yes" for the flag.
        return (bool) ($options['check'] ?? false);
    }

    public function run(SchemaFileConfig $config): OperationResult
    {
        return $this->generator->isUpToDate($config)
            ? OperationResult::success("Schema file [{$config->path}] is up to date.")
            : OperationResult::failure("Schema file [{$config->path}] is out of date. Run [php artisan schema:generate] to update it.");
    }
}
