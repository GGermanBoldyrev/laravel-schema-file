<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers;

use GGermanBoldyrev\SchemaFile\Reader\Columns\AbstractColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\ColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\TableContext;
use GGermanBoldyrev\SchemaFile\Schema\Expression;

/**
 * PostgreSQL has a type of its own for most Blueprint methods. What it does not
 * keep apart is written with the more common method: tinyInteger comes back as
 * smallInteger, dateTime as timestamp, enum as a string with a check constraint.
 *
 * @phpstan-import-type RawColumn from ColumnMapper
 */
final readonly class PostgresColumnMapper extends AbstractColumnMapper
{
    /**
     * PostgreSQL type name => Blueprint method.
     */
    private const array METHODS = [
        'int8' => 'bigInteger',
        'int4' => 'integer',
        'int2' => 'smallInteger',
        'varchar' => 'string',
        'bpchar' => 'char',
        'text' => 'text',
        'bool' => 'boolean',
        'numeric' => 'decimal',
        'float8' => 'double',
        'float4' => 'float',
        'json' => 'json',
        'jsonb' => 'jsonb',
        'uuid' => 'uuid',
        'date' => 'date',
        'timestamp' => 'timestamp',
        'timestamptz' => 'timestampTz',
        'time' => 'time',
        'timetz' => 'timeTz',
        'bytea' => 'binary',
        'inet' => 'ipAddress',
        'macaddr' => 'macAddress',
    ];

    /**
     * Auto-incrementing type => the Blueprint method that creates it.
     */
    private const array INCREMENTS = [
        'int4' => 'increments',
        'int2' => 'smallIncrements',
    ];

    private const array WITH_PRECISION = ['timestamp', 'timestampTz', 'time', 'timeTz'];

    protected function isId(array $column, TableContext $table): bool
    {
        return $column['type_name'] === 'int8' && $column['auto_increment'];
    }

    protected function method(array $column): ?string
    {
        $type = $column['type_name'];
        $parameters = $this->parameters($column['type']);

        if ($column['auto_increment'] && isset(self::INCREMENTS[$type])) {
            return self::INCREMENTS[$type];
        }

        // Blueprint always gives these a size; without one they are a different type.
        if (in_array($type, ['varchar', 'numeric'], true) && $parameters === []) {
            return null;
        }

        if ($type === 'bpchar' && $parameters === ['26']) {
            return 'ulid';
        }

        return self::METHODS[$type] ?? null;
    }

    protected function arguments(array $column, string $method): array
    {
        $parameters = $this->parameters($column['type']);

        return match (true) {
            $method === 'string', $method === 'char' => $parameters === ['255'] ? [] : [(int) ($parameters[0] ?? 1)],
            $method === 'decimal' => $parameters === ['8', '2'] ? [] : array_map(intval(...), $parameters),
            // Without a precision float() creates a double; 24 is the widest single-precision float.
            $method === 'float' => [24],
            in_array($method, self::WITH_PRECISION, true) => match ($parameters) {
                // No precision at all is not the same as Blueprint's default of 0.
                [] => [null],
                ['0'] => [],
                default => [(int) $parameters[0]],
            },
            default => [],
        };
    }

    /**
     * PostgreSQL reports a default as SQL with a cast: 'draft'::character varying, '-5'::integer.
     */
    protected function default(array $column, string $method, TableContext $table): string|int|float|bool|Expression|null
    {
        $default = $column['default'];

        // The sequence behind an auto-incrementing column is implied by its method.
        if ($default === null || $column['auto_increment']) {
            return null;
        }

        $value = preg_replace('/^(\'(?:[^\']|\'\')*\'|null)::[\w\s"\[\]().]+$/is', '$1', $default) ?? $default;

        if ($method === 'boolean' && in_array(strtolower($value), ['true', 'false'], true)) {
            return strtolower($value) === 'true';
        }

        return $this->sqlDefault($value, $method);
    }
}
