<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader;

use GGermanBoldyrev\SchemaFile\Contracts\ColumnMapper;
use GGermanBoldyrev\SchemaFile\Mapper\ColumnMapperRegistry;
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
                $tables[] = $this->table($name, $schema, $mapper, $prefix);
            }
        }

        return $tables;
    }

    private function table(string $name, Builder $schema, ColumnMapper $mapper, string $prefix): Table
    {
        return new Table(
            name: $name,
            columns: array_map($mapper->map(...), array_values($schema->getColumns($name))),
            indexes: array_map($this->index(...), array_values($schema->getIndexes($name))),
            foreignKeys: array_map(
                fn (array $foreignKey): ForeignKey => $this->foreignKey($foreignKey, $prefix),
                array_values($schema->getForeignKeys($name)),
            ),
        );
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
    private function foreignKey(array $foreignKey, string $prefix): ForeignKey
    {
        return new ForeignKey(
            columns: $foreignKey['columns'],
            foreignTable: $this->withoutPrefix($foreignKey['foreign_table'], $prefix) ?? $foreignKey['foreign_table'],
            foreignColumns: $foreignKey['foreign_columns'],
            name: $foreignKey['name'],
            onUpdate: $this->referentialAction($foreignKey['on_update']),
            onDelete: $this->referentialAction($foreignKey['on_delete']),
        );
    }

    /**
     * "No action" is what a database does when nothing was asked for, so it is not an action to write down.
     */
    private function referentialAction(?string $action): ?string
    {
        $action = strtolower($action ?? '');

        return in_array($action, ['', 'no action'], true) ? null : $action;
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
