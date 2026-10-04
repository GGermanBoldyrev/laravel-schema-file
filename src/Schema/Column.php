<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Schema;

final readonly class Column
{
    /**
     * @param  string  $method  The Blueprint method that creates the column, e.g. "string".
     * @param  list<mixed>  $arguments  Arguments passed to that method after the column name.
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
    ) {
    }
}
