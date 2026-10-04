<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile;

use Illuminate\Config\Repository;
use InvalidArgumentException;

/**
 * The settings of one run as a typed object, so the rest of the code never reads raw config keys.
 *
 * It starts as what config/schema-file.php says; a caller that needs something different
 * for a single run, like the command with its options, takes a changed copy with with().
 */
final readonly class SchemaFileConfig
{
    /**
     * @param  bool  $enabled  Whether the file is rewritten automatically after migrations.
     * @param  string|null  $connection  Null means the application's default connection.
     * @param  list<string>  $except  Names or patterns ("telescope_*") of tables left out of the schema file.
     */
    public function __construct(
        public string $path,
        public ?string $connection = null,
        public array $except = [],
        public bool $enabled = false,
    ) {
    }

    /**
     * Build the settings from config/schema-file.php, failing loudly on a value of the wrong type.
     *
     * @param  bool  $local  Whether the application runs in the local environment,
     *                       which is where automatic generation is on unless configured.
     */
    public static function fromRepository(Repository $config, bool $local = false): self
    {
        return new self(
            path: $config->string('schema-file.path'),
            connection: in_array($config->get('schema-file.connection'), [null, ''], true)
                ? null
                : $config->string('schema-file.connection'),
            except: [...self::migrationsTable($config), ...self::except($config)],
            enabled: in_array($config->get('schema-file.enabled'), [null, ''], true)
                ? $local
                : $config->boolean('schema-file.enabled'),
        );
    }

    /**
     * A copy with the given settings replaced; null leaves a setting as it is.
     */
    public function with(?string $path = null, ?string $connection = null): self
    {
        return new self(
            path: $path ?? $this->path,
            connection: $connection ?? $this->connection,
            except: $this->except,
            enabled: $this->enabled,
        );
    }

    /**
     * The table Laravel tracks migrations in: bookkeeping, not part of the application's schema.
     *
     * @return list<string>
     */
    private static function migrationsTable(Repository $config): array
    {
        $migrations = $config->get('database.migrations');
        $table = is_array($migrations) ? ($migrations['table'] ?? null) : $migrations;

        return is_string($table) ? [$table] : [];
    }

    /**
     * @return list<string>
     */
    private static function except(Repository $config): array
    {
        $tables = array_values($config->array('schema-file.except'));

        foreach ($tables as $table) {
            if (! is_string($table)) {
                throw new InvalidArgumentException(
                    'Configuration value for key [schema-file.except] must be a list of table names.'
                );
            }
        }

        return $tables;
    }
}
