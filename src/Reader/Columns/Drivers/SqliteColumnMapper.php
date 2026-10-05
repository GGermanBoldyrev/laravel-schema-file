<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers;

use GGermanBoldyrev\SchemaFile\Reader\Columns\AbstractColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\ColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\TableContext;
use GGermanBoldyrev\SchemaFile\Schema\Expression;

/**
 * SQLite keeps only a handful of storage types, so several Blueprint methods collapse
 * into one: string, char, uuid and enum all come back as "varchar", json as "text",
 * every integer size as "integer". Each type is mapped to the most common method
 * that produces it, which recreates an identical SQLite table.
 *
 * @phpstan-import-type RawColumn from ColumnMapper
 */
final readonly class SqliteColumnMapper extends AbstractColumnMapper
{
    /**
     * SQLite type name => Blueprint method.
     *
     * "datetime" is what both timestamp() and dateTime() create; timestamp is chosen
     * because it is what timestamps() and softDeletes() use.
     */
    private const array METHODS = [
        'integer' => 'integer',
        'varchar' => 'string',
        'text' => 'text',
        'tinyint' => 'boolean',
        'numeric' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'date' => 'date',
        'datetime' => 'timestamp',
        'time' => 'time',
        'blob' => 'binary',
    ];

    protected function method(array $column): ?string
    {
        return self::METHODS[$column['type_name']] ?? null;
    }

    /**
     * SQLite reports every integer primary key as auto-incrementing, because each one
     * aliases the row id. What $table->id() creates is narrower: a column declared
     * AUTOINCREMENT, and only the table's own definition says whether it was.
     */
    protected function isId(array $column, TableContext $table): bool
    {
        return $column['type_name'] === 'integer' && $this->isIncrementing($column, $table);
    }

    protected function isIncrementing(array $column, TableContext $table): bool
    {
        return $column['auto_increment'] && $this->declaresAutoIncrement($table);
    }

    /**
     * A table can have at most one AUTOINCREMENT column, so finding the keyword
     * anywhere in its definition, outside of names, strings and comments, is enough.
     */
    private function declaresAutoIncrement(TableContext $table): bool
    {
        $sql = $table->connection->scalar(
            "select sql from sqlite_master where type = 'table' and name = ?",
            [$table->prefixedName()],
        );

        if (! is_string($sql)) {
            return false;
        }

        $code = preg_replace(
            ['/--[^\n]*/', '~/\*.*?\*/~s', "/'(?:[^']|'')*'/", '/"(?:[^"]|"")*"/', '/`[^`]*`/', '/\[[^\]]*\]/'],
            ' ',
            $sql,
        );

        return preg_match('/\bautoincrement\b/i', (string) $code) === 1;
    }

    /**
     * SQLite reports a default as the SQL it was declared with: 'draft', '0', CURRENT_TIMESTAMP.
     */
    protected function default(array $column, string $method, TableContext $table): string|int|float|bool|Expression|null
    {
        return $column['default'] === null ? null : $this->sqlDefault($column['default'], $method);
    }
}
