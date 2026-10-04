<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console;

use GGermanBoldyrev\SchemaFile\GenerationResult;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use GGermanBoldyrev\SchemaFile\SchemaFileGenerator;
use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Help;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Attributes\Usage;
use Illuminate\Console\Command;

/**
 * The manual entry point: writes the schema file from the current state of the database.
 */
#[Signature('schema:generate
    {--database= : The database connection to read the schema from}
    {--path= : Where to write the schema file instead of the configured path}
    {--check : Do not write anything; fail if the schema file is out of date}')]
#[Aliases(['migrate:schema'])]
#[Description('Write the current database schema to a single PHP file')]
#[Help('Reads the structure of the database and rewrites the schema file (database/schema.php unless configured otherwise). The same happens automatically after every migration.')]
#[Usage('schema:generate --check')]
#[Usage('schema:generate --database=analytics --path=database/analytics-schema.php')]
final class SchemaFileCommand extends Command
{
    public function handle(SchemaFileGenerator $generator, SchemaFileConfig $config): int
    {
        $config = $config->with(
            path: $this->stringOption('path'),
            connection: $this->stringOption('database'),
        );

        if ($this->option('check')) {
            return $this->check($generator, $config);
        }

        $this->components->info(match ($generator->generate($config)) {
            GenerationResult::Written => "Schema file written to [{$config->path}].",
            GenerationResult::Unchanged => "Schema file [{$config->path}] is already up to date.",
        });

        return self::SUCCESS;
    }

    private function check(SchemaFileGenerator $generator, SchemaFileConfig $config): int
    {
        if ($generator->isUpToDate($config)) {
            $this->components->info("Schema file [{$config->path}] is up to date.");

            return self::SUCCESS;
        }

        $this->components->error("Schema file [{$config->path}] is out of date. Run [php artisan schema:generate] to update it.");

        return self::FAILURE;
    }

    /**
     * The value of an option that takes a string, or null when it was not given.
     */
    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
