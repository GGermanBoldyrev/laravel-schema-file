<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Mapper\Drivers;

use GGermanBoldyrev\SchemaFile\Contracts\ColumnMapper;
use GGermanBoldyrev\SchemaFile\Mapper\AbstractColumnMapper;
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

    protected function isId(array $column): bool
    {
        return $column['type_name'] === 'integer' && $column['auto_increment'];
    }

    /**
     * SQLite reports a default as the SQL it was declared with: 'draft', '0', CURRENT_TIMESTAMP.
     */
    protected function default(array $column, string $method): string|int|float|bool|Expression|null
    {
        $default = $column['default'];

        if ($default === null || strcasecmp($default, 'null') === 0) {
            return null;
        }

        if (strlen($default) >= 2 && str_starts_with($default, "'") && str_ends_with($default, "'")) {
            return $this->literal(str_replace("''", "'", substr($default, 1, -1)), $method);
        }

        if (is_numeric($default)) {
            return $this->literal($default, $method);
        }

        // Anything but a bare keyword must be parenthesised to be a valid default again.
        return new Expression(preg_match('/^\w+$/', $default) === 1 ? $default : "({$default})");
    }
}
