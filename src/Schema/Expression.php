<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Schema;

/**
 * A raw SQL default such as CURRENT_TIMESTAMP, as opposed to a literal value.
 */
final readonly class Expression
{
    public function __construct(
        public string $sql,
    ) {
    }
}
