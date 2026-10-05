<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader\Columns;

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
        'year',
    ];

    private const array FRACTIONALS = ['decimal', 'float', 'double'];

    private const array TEMPORALS = ['timestamp', 'timestampTz', 'dateTime', 'dateTimeTz'];

    /**
     * Methods that create an auto-incrementing primary key on their own.
     */
    private const array INCREMENTS = ['increments', 'tinyIncrements', 'smallIncrements', 'mediumIncrements', 'bigIncrements'];

    final public function map(array $column, TableContext $table): Column
    {
        if ($this->isId($column, $table)) {
            return new Column($column['name'], 'id');
        }

        $method = $this->method($column) ?? self::RAW;
        $default = $this->default($column, $method, $table);
        $useCurrent = $this->isCurrentTimestamp($default, $method);

        return new Column(
            name: $column['name'],
            method: $method,
            arguments: $method === self::RAW ? [$column['type']] : $this->arguments($column, $method),
            nullable: $column['nullable'],
            unsigned: $this->isUnsigned($column, $method),
            autoIncrement: ! in_array($method, self::INCREMENTS, true) && $this->isIncrementing($column, $table),
            default: $useCurrent ? null : $default,
            comment: ($column['comment'] ?? '') === '' ? null : $column['comment'],
            virtualAs: $this->generation($column, 'virtual'),
            storedAs: $this->generation($column, 'stored'),
            useCurrent: $useCurrent,
            useCurrentOnUpdate: $this->usesCurrentOnUpdate($column, $table),
        );
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
    abstract protected function default(array $column, string $method, TableContext $table): string|int|float|bool|Expression|null;

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
     * Whether ->unsigned() has to be chained, which is not the case when the method itself says so.
     *
     * @param  RawColumn  $column
     */
    protected function isUnsigned(array $column, string $method): bool
    {
        return false;
    }

    /**
     * @param  RawColumn  $column
     */
    protected function isIncrementing(array $column, TableContext $table): bool
    {
        return $column['auto_increment'];
    }

    /**
     * @param  RawColumn  $column
     */
    protected function usesCurrentOnUpdate(array $column, TableContext $table): bool
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

    /**
     * Read a default that the database reports as the SQL it was declared with:
     * 'draft', 42, NULL, or an expression such as CURRENT_TIMESTAMP.
     */
    final protected function sqlDefault(string $default, string $method): string|int|float|bool|Expression|null
    {
        if (strcasecmp($default, 'null') === 0) {
            return null;
        }

        // One string literal and nothing else: 'a' || 'b' also starts and ends with a quote.
        if (preg_match("/^'((?:[^']|'')*)'$/s", $default, $matches) === 1) {
            return $this->literal(str_replace("''", "'", $matches[1]), $method);
        }

        if (is_numeric($default)) {
            return $this->literal($default, $method);
        }

        return $this->expression($default);
    }

    /**
     * Raw SQL in a form that is valid after the word DEFAULT: anything but a bare
     * keyword has to be parenthesised.
     */
    final protected function expression(string $sql): Expression
    {
        if (preg_match('/^(\w+|current_timestamp\(\d*\))$/i', $sql) === 1 || $this->isParenthesised($sql)) {
            return new Expression($sql);
        }

        return new Expression("({$sql})");
    }

    /**
     * The values between the parentheses of a type: "decimal(8,2) unsigned" gives ["8", "2"].
     *
     * @return list<string>
     */
    final protected function parameters(string $type): array
    {
        if (preg_match('/\(([^)]*)\)/', $type, $matches) !== 1) {
            return [];
        }

        return array_map('trim', explode(',', $matches[1]));
    }

    /**
     * Whether one pair of parentheses wraps the whole expression, as in "(a + (b))" but not "(a) + (b)".
     */
    private function isParenthesised(string $sql): bool
    {
        if (! str_starts_with($sql, '(') || ! str_ends_with($sql, ')')) {
            return false;
        }

        $depth = 0;

        foreach (str_split(substr($sql, 0, -1)) as $character) {
            $depth += match ($character) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the default is what ->useCurrent() writes.
     */
    private function isCurrentTimestamp(string|int|float|bool|Expression|null $default, string $method): bool
    {
        return $default instanceof Expression
            && in_array($method, self::TEMPORALS, true)
            && preg_match('/^current_timestamp(\(\d*\))?$/i', $default->sql) === 1;
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
}
