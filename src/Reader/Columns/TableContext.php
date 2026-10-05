<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader\Columns;

use Illuminate\Database\Connection;

/**
 * The table a column belongs to, for a mapper that has to ask the database
 * something the column's own description does not say.
 */
final readonly class TableContext
{
    /**
     * @param  string  $name  The table's name as migrations spell it, without the connection's prefix.
     */
    public function __construct(
        public Connection $connection,
        public string $name,
    ) {
    }

    /**
     * The table's name as the database knows it.
     */
    public function prefixedName(): string
    {
        return $this->connection->getTablePrefix().$this->name;
    }
}
