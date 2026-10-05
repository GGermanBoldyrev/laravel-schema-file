<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console\Operations\Modes;

use GGermanBoldyrev\SchemaFile\Console\Operations\Operation;
use GGermanBoldyrev\SchemaFile\Console\Operations\OperationResult;
use GGermanBoldyrev\SchemaFile\GenerationResult;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use GGermanBoldyrev\SchemaFile\SchemaFileGenerator;

/**
 * Writes the schema file from the current state of the database.
 *
 * This is what the command does unless asked for something else, so it turns no command line down.
 */
final readonly class WriteSchemaFile implements Operation
{
    public function __construct(
        private SchemaFileGenerator $generator,
    ) {
    }

    public function supports(array $options): bool
    {
        return true;
    }

    public function run(SchemaFileConfig $config): OperationResult
    {
        return OperationResult::success(match ($this->generator->generate($config)) {
            GenerationResult::Written => "Schema file written to [{$config->path}].",
            GenerationResult::Unchanged => "Schema file [{$config->path}] is already up to date.",
        });
    }
}
