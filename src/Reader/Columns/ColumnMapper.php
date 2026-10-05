<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader\Columns;

use GGermanBoldyrev\SchemaFile\Schema\Column;

/**
 * Translates a column as one database engine reports it into the Blueprint call that creates it.
 *
 * Implement this to support another database driver, then add the mapper to the
 * ColumnMapperRegistry. Extending AbstractColumnMapper is the shorter way.
 *
 * @phpstan-type RawColumn array{
 *     name: string,
 *     type_name: string,
 *     type: string,
 *     collation: string|null,
 *     nullable: bool,
 *     default: string|null,
 *     auto_increment: bool,
 *     comment: string|null,
 *     generation: array{type: string, expression: string|null}|null,
 * }
 */
interface ColumnMapper
{
    /**
     * @param  RawColumn  $column  One entry of Schema::getColumns().
     * @param  TableContext  $table  The table the column belongs to.
     */
    public function map(array $column, TableContext $table): Column;
}
