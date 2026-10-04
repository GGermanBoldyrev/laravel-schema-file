<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Schema;

final readonly class Table
{
    /**
     * @param  list<Column>  $columns  In the order they appear in the database.
     * @param  list<Index>  $indexes
     * @param  list<ForeignKey>  $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public array $indexes = [],
        public array $foreignKeys = [],
    ) {
    }
}
