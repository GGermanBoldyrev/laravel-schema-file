<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader;

use GGermanBoldyrev\SchemaFile\Reader\Columns\ColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\ColumnMapperRegistry;
use GGermanBoldyrev\SchemaFile\Reader\Columns\TableContext;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\Schema\ForeignKey;
use GGermanBoldyrev\SchemaFile\Schema\Index;
use GGermanBoldyrev\SchemaFile\Schema\IndexType;
use GGermanBoldyrev\SchemaFile\Schema\Table;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Str;

/**
 * Answers one question: what is in the database right now.
 *
 * It knows the structure — which tables exist and what they contain — and leaves
 * the meaning of each engine's column types to that engine's column mapper. What it
 * returns no longer shows which database it came from.
 */
final readonly class SchemaReader
{
    /**
     * Driver => what it reports for a foreign key declared without an action, where
     * that is not "no action". MariaDB says "restrict", which there means the same.
     */
    private const array NO_ACTION = [
        'mariadb' => 'restrict',
    ];

    public function __construct(
        private ColumnMapperRegistry $mappers,
    ) {
    }

    /**
     * @param  list<string>  $except  Tables to leave out: names without the connection's prefix, or patterns such as "telescope_*".
     * @return list<Table>
     */
    public function read(Connection $connection, array $except = []): array
    {
        $mapper = $this->mappers->for($connection->getDriverName());
        $schema = $connection->getSchemaBuilder();
        $prefix = $connection->getTablePrefix();

        $tables = [];

        foreach ($schema->getTables($schema->getCurrentSchemaListing()) as $table) {
            $name = $this->withoutPrefix($table['name'], $prefix);

            if ($name !== null && ! Str::is($except, $name)) {
                $tables[] = $this->table(new TableContext($connection, $name), $schema, $mapper);
            }
        }

        return $tables;
    }

    private function table(TableContext $table, Builder $schema, ColumnMapper $mapper): Table
    {
        $prefix = $table->connection->getTablePrefix();
        $noAction = self::NO_ACTION[$table->connection->getDriverName()] ?? 'no action';

        $foreignKeys = array_map(
            fn (array $foreignKey): ForeignKey => $this->foreignKey($foreignKey, $prefix, $noAction),
            array_values($schema->getForeignKeys($table->name)),
        );

        $indexes = array_map($this->index(...), array_values($schema->getIndexes($table->name)));

        return new Table(
            name: $table->name,
            columns: array_map(
                fn (array $column): Column => $mapper->map($column, $table),
                array_values($schema->getColumns($table->name)),
            ),
            indexes: array_values(array_filter(
                $indexes,
                fn (Index $index): bool => $index->columns !== [] && ! $this->backsForeignKey($index, $foreignKeys),
            )),
            foreignKeys: $foreignKeys,
        );
    }

    /**
     * Whether the index is the one MySQL creates by itself for a foreign key, under the key's name.
     * It comes back with the key, so writing it down as well would only be noise.
     *
     * An index over an expression rather than columns, which has no columns to name, is left out too.
     *
     * @param  list<ForeignKey>  $foreignKeys
     */
    private function backsForeignKey(Index $index, array $foreignKeys): bool
    {
        if ($index->type !== IndexType::Index) {
            return false;
        }

        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->name !== null && $foreignKey->name === $index->name && $foreignKey->columns === $index->columns) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{name: string, columns: list<string>, type: string|null, unique: bool, primary: bool}  $index
     */
    private function index(array $index): Index
    {
        $type = match (true) {
            $index['primary'] => IndexType::Primary,
            $index['unique'] => IndexType::Unique,
            $index['type'] === 'fulltext' => IndexType::FullText,
            $index['type'] === 'spatial' => IndexType::Spatial,
            default => IndexType::Index,
        };

        return new Index($type, $index['columns'], $index['name']);
    }

    /**
     * @param  array{name: string|null, columns: list<string>, foreign_table: string, foreign_columns: list<string>, on_update: string|null, on_delete: string|null}  $foreignKey
     */
    private function foreignKey(array $foreignKey, string $prefix, string $noAction): ForeignKey
    {
        return new ForeignKey(
            columns: $foreignKey['columns'],
            foreignTable: $this->withoutPrefix($foreignKey['foreign_table'], $prefix) ?? $foreignKey['foreign_table'],
            foreignColumns: $foreignKey['foreign_columns'],
            name: $foreignKey['name'],
            onUpdate: $this->referentialAction($foreignKey['on_update'], $noAction),
            onDelete: $this->referentialAction($foreignKey['on_delete'], $noAction),
        );
    }

    /**
     * What a database reports when no action was asked for is not an action to write down.
     */
    private function referentialAction(?string $action, string $noAction): ?string
    {
        $action = strtolower($action ?? '');

        return in_array($action, ['', 'no action', $noAction], true) ? null : $action;
    }

    /**
     * The table name as migrations spell it, or null for a table outside the connection's prefix.
     */
    private function withoutPrefix(string $table, string $prefix): ?string
    {
        if ($prefix === '') {
            return $table;
        }

        return str_starts_with($table, $prefix) ? substr($table, strlen($prefix)) : null;
    }
}
