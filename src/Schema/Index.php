<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Schema;

final readonly class Index
{
    /**
     * @param  list<string>  $columns
     */
    public function __construct(
        public IndexType $type,
        public array $columns,
        public ?string $name = null,
    ) {
    }
}
