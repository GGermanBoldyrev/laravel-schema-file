<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Schema;

final readonly class Column
{
    /**
     * @param  string  $method  The Blueprint method that creates the column, e.g. "string".
     * @param  list<mixed>  $arguments  Arguments passed to that method after the column name.
     * @param  string|null  $virtualAs  The expression of a column computed on every read.
     * @param  string|null  $storedAs  The expression of a column computed on write and stored.
     */
    public function __construct(
        public string $name,
        public string $method,
        public array $arguments = [],
        public bool $nullable = false,
        public bool $unsigned = false,
        public bool $autoIncrement = false,
        public string|int|float|bool|Expression|null $default = null,
        public ?string $comment = null,
        public ?string $virtualAs = null,
        public ?string $storedAs = null,
    ) {
    }

    public function isGenerated(): bool
    {
        return $this->virtualAs !== null || $this->storedAs !== null;
    }
}
