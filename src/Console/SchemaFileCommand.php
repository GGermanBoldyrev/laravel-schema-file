<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console;

use GGermanBoldyrev\SchemaFile\Console\Operations\ConflictingOperationsException;
use GGermanBoldyrev\SchemaFile\Console\Operations\OperationRegistry;
use GGermanBoldyrev\SchemaFile\Console\Operations\OperationResult;
use GGermanBoldyrev\SchemaFile\Reader\Columns\UnsupportedDriverException;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use Illuminate\Console\Attributes\Aliases;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Help;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Attributes\Usage;
use Illuminate\Console\Command;

/**
 * The manual entry point: writes the schema file from the current state of the database.
 *
 * What it does for a given command line is an operation's business; the command only
 * turns its options into settings and prints how the operation ended.
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
    public function handle(OperationRegistry $operations, SchemaFileConfig $config): int
    {
        $config = $config->with(
            path: $this->stringOption('path'),
            connection: $this->stringOption('database'),
        );

        // Not bugs to trace but things to tell the user: one line instead of a stack trace.
        try {
            $result = $operations->for($this->options())->run($config);
        } catch (ConflictingOperationsException|UnsupportedDriverException $exception) {
            $result = OperationResult::failure($exception->getMessage());
        }

        $result->successful
            ? $this->components->info($result->message)
            : $this->components->error($result->message);

        return $result->successful ? self::SUCCESS : self::FAILURE;
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
