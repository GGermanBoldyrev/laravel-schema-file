<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Mapper;

use GGermanBoldyrev\SchemaFile\Contracts\ColumnMapper;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\Schema\Expression;

/**
 * What every driver's mapper does the same way: assembling the Column, falling back
 * to rawColumn() for a type no Blueprint method creates, and turning a default's
 * text into a PHP value. A driver only says how it names things.
 *
 * @phpstan-import-type RawColumn from ColumnMapper
 */
abstract readonly class AbstractColumnMapper implements ColumnMapper
{
    /**
     * The method that writes a column type back verbatim.
     */
    protected const string RAW = 'rawColumn';

    private const array INTEGERS = [
        'integer', 'tinyInteger', 'smallInteger', 'mediumInteger', 'bigInteger',
        'unsignedInteger', 'unsignedTinyInteger', 'unsignedSmallInteger', 'unsignedMediumInteger', 'unsignedBigInteger',
    ];

    private const array FRACTIONALS = ['decimal', 'float', 'double'];

    final public function map(array $column, TableContext $table): Column
    {
        if ($this->isId($column, $table)) {
            return new Column($column['name'], 'id');
        }

        $method = $this->method($column) ?? self::RAW;

        return new Column(
            name: $column['name'],
            method: $method,
            arguments: $method === self::RAW ? [$column['type']] : $this->arguments($column, $method),
            nullable: $column['nullable'],
            unsigned: $this->isUnsigned($column),
            default: $this->default($column, $method),
            comment: $column['comment'],
            virtualAs: $this->generation($column, 'virtual'),
            storedAs: $this->generation($column, 'stored'),
        );
    }

    /**
     * The expression the column is computed from, if it is a generated column of the given kind.
     *
     * @param  RawColumn  $column
     */
    private function generation(array $column, string $type): ?string
    {
        $generation = $column['generation'];

        return $generation !== null && $generation['type'] === $type ? $generation['expression'] : null;
    }

    /**
     * The Blueprint method that creates this column, or null when there is none.
     *
     * @param  RawColumn  $column
     */
    abstract protected function method(array $column): ?string;

    /**
     * The column's default as a PHP value, an Expression for raw SQL, or null for no default.
     *
     * @param  RawColumn  $column
     */
    abstract protected function default(array $column, string $method): string|int|float|bool|Expression|null;

    /**
     * Whether the column is exactly what $table->id() creates.
     *
     * @param  RawColumn  $column
     */
    abstract protected function isId(array $column, TableContext $table): bool;

    /**
     * What is passed to the Blueprint method after the column name: a length, a precision, enum values.
     *
     * @param  RawColumn  $column
     * @return list<mixed>
     */
    protected function arguments(array $column, string $method): array
    {
        return [];
    }

    /**
     * @param  RawColumn  $column
     */
    protected function isUnsigned(array $column): bool
    {
        return false;
    }

    /**
     * Give a default's literal text the PHP type that suits the column: "0" is 0 for
     * an integer, false for a boolean and still "0" for a string.
     */
    final protected function literal(string $value, string $method): string|int|float|bool
    {
        if (! is_numeric($value)) {
            return $value;
        }

        return match (true) {
            $method === 'boolean' => (bool) (int) $value,
            in_array($method, self::INTEGERS, true) => (int) $value,
            in_array($method, self::FRACTIONALS, true) => (float) $value,
            default => $value,
        };
    }
}
