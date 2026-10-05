<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers\SqliteColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\TableContext;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\Schema\Expression;
use Illuminate\Database\SQLiteConnection;

/**
 * A column the way Schema::getColumns() reports it on SQLite.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function sqliteColumn(string $type, array $overrides = []): array
{
    return [
        'name' => 'col',
        'type_name' => preg_replace('/\(.*$/', '', $type),
        'type' => $type,
        'collation' => null,
        'nullable' => false,
        'default' => null,
        'auto_increment' => false,
        'comment' => null,
        'generation' => null,
        ...$overrides,
    ];
}

/**
 * Map a column of the table "things", which exists in the database only when its DDL is given.
 */
function mapSqlite(string $type, array $overrides = [], ?string $ddl = null, string $prefix = ''): Column
{
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', $prefix);

    if ($ddl !== null) {
        $connection->statement($ddl);
    }

    return (new SqliteColumnMapper)->map(sqliteColumn($type, $overrides), new TableContext($connection, 'things'));
}

it('maps each SQLite type to a Blueprint method', function (string $type, string $method) {
    expect(mapSqlite($type))->toEqual(new Column('col', $method));
})->with([
    ['integer', 'integer'],
    ['varchar', 'string'],
    ['text', 'text'],
    ['tinyint(1)', 'boolean'],
    ['numeric', 'decimal'],
    ['float', 'float'],
    ['double', 'double'],
    ['date', 'date'],
    ['datetime', 'timestamp'],
    ['time', 'time'],
    ['blob', 'binary'],
]);

describe('id', function () {
    // SQLite reports every integer primary key as auto-incrementing; only the
    // table's own definition tells an AUTOINCREMENT column from a plain one.
    $reported = ['name' => 'id', 'auto_increment' => true];

    it('maps an AUTOINCREMENT column to id', function () use ($reported) {
        $ddl = 'create table "things" ("id" integer primary key autoincrement not null, "name" varchar)';

        expect(mapSqlite('integer', $reported, $ddl))->toEqual(new Column('id', 'id'));
    });

    it('maps an integer primary key without AUTOINCREMENT to a plain integer', function (string $ddl) use ($reported) {
        expect(mapSqlite('integer', $reported, $ddl))->toEqual(new Column('id', 'integer'));
    })->with([
        'table-level key, as Blueprint writes it' => ['create table "things" ("id" integer not null, primary key ("id"))'],
        'column-level key' => ['create table "things" ("id" integer primary key not null)'],
        'the word only in a default' => ['create table "things" ("id" integer primary key, "kind" varchar default \'autoincrement\')'],
        'the word only in a column name' => ['create table "things" ("id" integer primary key, "autoincrement" integer)'],
        'the word only in a comment' => ["create table \"things\" (\"id\" integer primary key -- no autoincrement here\n)"],
    ]);

    it('finds AUTOINCREMENT whatever its case and spacing', function () use ($reported) {
        $ddl = "CREATE TABLE things (id INTEGER PRIMARY KEY\n  AUTOINCREMENT)";

        expect(mapSqlite('integer', $reported, $ddl)->method)->toBe('id');
    });

    it('looks the table up under its prefixed name', function () use ($reported) {
        $ddl = 'create table "app_things" ("id" integer primary key autoincrement not null)';

        expect(mapSqlite('integer', $reported, $ddl, prefix: 'app_')->method)->toBe('id');
    });

    it('maps to a plain integer when the table definition cannot be found', function () use ($reported) {
        expect(mapSqlite('integer', $reported)->method)->toBe('integer');
    });

    it('keeps the name of an id column that is not called id', function () {
        $ddl = 'create table "things" ("legacy_id" integer primary key autoincrement not null)';

        expect(mapSqlite('integer', ['name' => 'legacy_id', 'auto_increment' => true], $ddl))
            ->toEqual(new Column('legacy_id', 'id'));
    });
});

it('writes a type it does not know back verbatim', function () {
    expect(mapSqlite('geometry', ['nullable' => true]))
        ->toEqual(new Column('col', 'rawColumn', ['geometry'], nullable: true));
});

it('carries nullability over', function () {
    expect(mapSqlite('varchar', ['nullable' => true])->nullable)->toBe(true);
});

it('reads defaults as SQLite reports them', function (string $type, string $default, mixed $expected) {
    expect(mapSqlite($type, ['default' => $default])->default)->toEqual($expected);
})->with([
    'quoted string' => ['varchar', "'draft'", 'draft'],
    'empty string' => ['varchar', "''", ''],
    'escaped quote' => ['varchar', "'it''s'", "it's"],
    'numeric string stays a string' => ['varchar', "'5'", '5'],
    'integer' => ['integer', "'0'", 0],
    'unquoted integer' => ['integer', '42', 42],
    'negative integer' => ['integer', "'-1'", -1],
    'boolean false' => ['tinyint(1)', "'0'", false],
    'boolean true' => ['tinyint(1)', "'1'", true],
    'decimal' => ['numeric', "'1.5'", 1.5],
    'float' => ['float', "'0.25'", 0.25],
    'keyword' => ['integer', 'CURRENT_TIMESTAMP', new Expression('CURRENT_TIMESTAMP')],
    'expression' => ['integer', '1 + 1', new Expression('(1 + 1)')],
]);

it('reads the current time as a default of a timestamp as useCurrent', function () {
    $column = mapSqlite('datetime', ['default' => 'CURRENT_TIMESTAMP']);

    expect($column)->useCurrent->toBeTrue()->default->toBeNull()->useCurrentOnUpdate->toBeFalse();
});

it('does not mark a column without that default as useCurrent', function (string $type, ?string $default) {
    expect(mapSqlite($type, ['default' => $default])->useCurrent)->toBeFalse();
})->with([
    'no default' => ['datetime', null],
    'a fixed moment' => ['datetime', "'2024-01-01 00:00:00'"],
    'the words as a string' => ['datetime', "'CURRENT_TIMESTAMP'"],
    'not a timestamp' => ['varchar', 'CURRENT_TIMESTAMP'],
]);

it('treats a missing or NULL default as no default', function (?string $default) {
    expect(mapSqlite('varchar', ['nullable' => true, 'default' => $default])->default)->toBeNull();
})->with([null, 'NULL', 'null']);

it('reads unusual defaults', function (string $type, string $default, mixed $expected) {
    expect(mapSqlite($type, ['default' => $default])->default)->toEqual($expected);
})->with([
    'string concatenation is an expression, not a literal' => ['varchar', "'a' || 'b'", new Expression("('a' || 'b')")],
    'function call' => ['datetime', "datetime('now')", new Expression("(datetime('now'))")],
    'boolean keyword' => ['tinyint(1)', 'TRUE', new Expression('TRUE')],
    'string that looks like a keyword' => ['varchar', "'CURRENT_TIMESTAMP'", 'CURRENT_TIMESTAMP'],
    'string holding only a quote' => ['varchar', "''''", "'"],
    'multi-line string' => ['text', "'a\nb'", "a\nb"],
    'unquoted number on a string column' => ['varchar', '5', '5'],
    'number on an unknown type' => ['geometry', "'7'", '7'],
    'zero decimal' => ['numeric', "'0'", 0.0],
    'negative decimal' => ['numeric', '-2.50', -2.5],
    'non-numeric text on an integer column' => ['integer', "'abc'", 'abc'],
    'a lone quote' => ['varchar', "'", new Expression("(')")],
]);

it('maps a tinyint of any width to boolean', function () {
    expect(mapSqlite('tinyint')->method)->toBe('boolean');
});

it('does not treat a non-incrementing integer as id', function () {
    expect(mapSqlite('integer', ['name' => 'id'])->method)->toBe('integer');
});

it('ignores nullability and defaults of an id column', function () {
    $ddl = 'create table "things" ("id" integer primary key autoincrement)';

    expect(mapSqlite('integer', ['name' => 'id', 'auto_increment' => true, 'nullable' => true, 'default' => "'1'"], $ddl))
        ->toEqual(new Column('id', 'id'));
});

it('keeps the full type of an unknown column, including its size', function () {
    expect(mapSqlite('geometry(point)')->arguments)->toBe(['geometry(point)']);
});

it('carries a comment over', function () {
    expect(mapSqlite('varchar', ['comment' => 'Public name'])->comment)->toBe('Public name');
});

it('reads a generated column', function (string $type, string $property) {
    $column = mapSqlite('integer', ['nullable' => true, 'generation' => ['type' => $type, 'expression' => 'price * 2']]);

    expect($column->{$property})->toBe('price * 2')
        ->and($column->method)->toBe('integer')
        ->and($column->nullable)->toBeTrue();
})->with([['virtual', 'virtualAs'], ['stored', 'storedAs']]);

it('reads a column as ordinary when its generation has no expression', function () {
    $column = mapSqlite('integer', ['generation' => ['type' => 'virtual', 'expression' => null]]);

    expect($column)->virtualAs->toBeNull()->storedAs->toBeNull();
});

it('never marks a column unsigned', function () {
    expect(mapSqlite('integer')->unsigned)->toBeFalse();
});
