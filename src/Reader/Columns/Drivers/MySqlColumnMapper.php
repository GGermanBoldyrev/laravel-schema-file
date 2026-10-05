<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers;

use GGermanBoldyrev\SchemaFile\Reader\Columns\AbstractColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\ColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\TableContext;
use GGermanBoldyrev\SchemaFile\Schema\Expression;
use Illuminate\Database\MySqlConnection;

/**
 * MySQL and MariaDB. They name their types the same way and differ in how they
 * report defaults: MariaDB as the SQL that was declared, MySQL as the bare value
 * with a note elsewhere saying whether it is an expression.
 *
 * @phpstan-import-type RawColumn from ColumnMapper
 */
final readonly class MySqlColumnMapper extends AbstractColumnMapper
{
    /**
     * MySQL type name => Blueprint method.
     */
    private const array METHODS = [
        'bigint' => 'bigInteger',
        'int' => 'integer',
        'mediumint' => 'mediumInteger',
        'smallint' => 'smallInteger',
        'tinyint' => 'tinyInteger',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'varchar' => 'string',
        'char' => 'char',
        'text' => 'text',
        'tinytext' => 'tinyText',
        'mediumtext' => 'mediumText',
        'longtext' => 'longText',
        'json' => 'json',
        'enum' => 'enum',
        'set' => 'set',
        'uuid' => 'uuid',
        'date' => 'date',
        'datetime' => 'dateTime',
        'timestamp' => 'timestamp',
        'time' => 'time',
        'year' => 'year',
        'blob' => 'binary',
        'binary' => 'binary',
        'varbinary' => 'binary',
    ];

    /**
     * Unsigned auto-incrementing type => the Blueprint method that creates it.
     */
    private const array INCREMENTS = [
        'int' => 'increments',
        'mediumint' => 'mediumIncrements',
        'smallint' => 'smallIncrements',
        'tinyint' => 'tinyIncrements',
    ];

    private const array INTEGERS = ['bigint', 'int', 'mediumint', 'smallint', 'tinyint'];

    private const array WITH_PRECISION = ['dateTime', 'timestamp', 'time'];

    protected function isId(array $column, TableContext $table): bool
    {
        return $column['type_name'] === 'bigint' && $column['auto_increment'] && $this->hasUnsignedType($column);
    }

    protected function method(array $column): ?string
    {
        $type = $column['type_name'];
        $unsigned = $this->hasUnsignedType($column);

        if ($type === 'tinyint' && str_starts_with($column['type'], 'tinyint(1)')) {
            return 'boolean';
        }

        // A UUID and a ULID are fixed-width strings here, recognisable only by their width.
        if ($type === 'char') {
            return match ($this->parameters($column['type'])) {
                ['36'] => 'uuid',
                ['26'] => 'ulid',
                default => 'char',
            };
        }

        if ($unsigned && $column['auto_increment'] && isset(self::INCREMENTS[$type])) {
            return self::INCREMENTS[$type];
        }

        $method = self::METHODS[$type] ?? null;

        return $unsigned && in_array($type, self::INTEGERS, true) ? 'unsigned'.ucfirst((string) $method) : $method;
    }

    protected function arguments(array $column, string $method): array
    {
        $parameters = $this->parameters($column['type']);

        return match (true) {
            $method === 'string', $method === 'char' => $parameters === ['255'] ? [] : [(int) $parameters[0]],
            $method === 'decimal' => $parameters === ['8', '2'] ? [] : array_map(intval(...), $parameters),
            // Without a precision float() creates a double; 24 is the widest single-precision float.
            $method === 'float' => [24],
            $method === 'enum', $method === 'set' => [$this->options($column['type'])],
            $method === 'binary' && $column['type_name'] === 'binary' => [(int) $parameters[0], true],
            $method === 'binary' && $column['type_name'] === 'varbinary' => [(int) $parameters[0]],
            in_array($method, self::WITH_PRECISION, true) && $parameters !== [] => [(int) $parameters[0]],
            default => [],
        };
    }

    protected function isUnsigned(array $column, string $method): bool
    {
        return $this->hasUnsignedType($column) && in_array($method, ['decimal', 'float', 'double'], true);
    }

    protected function default(array $column, string $method, TableContext $table): string|int|float|bool|Expression|null
    {
        $default = $column['default'];

        if ($default === null) {
            return null;
        }

        if ($this->isMariaDb($table)) {
            return $this->sqlDefault($default, $method);
        }

        return str_contains(strtoupper($this->extra($column, $table)), 'DEFAULT_GENERATED')
            ? $this->expression($default)
            : $this->literal($default, $method);
    }

    protected function usesCurrentOnUpdate(array $column, TableContext $table): bool
    {
        return in_array($column['type_name'], ['timestamp', 'datetime'], true)
            && str_contains(strtolower($this->extra($column, $table)), 'on update');
    }

    /**
     * @param  RawColumn  $column
     */
    private function hasUnsignedType(array $column): bool
    {
        return str_contains($column['type'], ' unsigned');
    }

    /**
     * The values of an enum or a set: "enum('a','it''s')" gives ["a", "it's"].
     *
     * @return list<string>
     */
    private function options(string $type): array
    {
        preg_match_all("/'((?:[^']|'')*)'/", $type, $matches);

        return array_map(fn (string $option): string => str_replace("''", "'", $option), $matches[1]);
    }

    /**
     * What the server notes about the column besides its type and default, such as
     * "DEFAULT_GENERATED on update CURRENT_TIMESTAMP". Laravel does not pass it on.
     *
     * @param  RawColumn  $column
     */
    private function extra(array $column, TableContext $table): string
    {
        $extra = $table->connection->scalar(
            'select extra from information_schema.columns where table_schema = ? and table_name = ? and column_name = ?',
            [$table->connection->getDatabaseName(), $table->prefixedName(), $column['name']],
        );

        return is_string($extra) ? $extra : '';
    }

    private function isMariaDb(TableContext $table): bool
    {
        return $table->connection instanceof MySqlConnection && $table->connection->isMaria();
    }
}
