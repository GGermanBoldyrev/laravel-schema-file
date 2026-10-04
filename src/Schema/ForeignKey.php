<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Schema;

final readonly class ForeignKey
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $foreignColumns
     * @param  string|null  $onUpdate  Null means the database default (no action).
     * @param  string|null  $onDelete  Null means the database default (no action).
     */
    public function __construct(
        public array $columns,
        public string $foreignTable,
        public array $foreignColumns,
        public ?string $name = null,
        public ?string $onUpdate = null,
        public ?string $onDelete = null,
    ) {
    }
}
