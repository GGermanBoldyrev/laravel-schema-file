<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Mapper\Drivers\SqliteColumnMapper;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\Schema\Expression;

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

function mapSqlite(string $type, array $overrides = []): Column
{
    return (new SqliteColumnMapper)->map(sqliteColumn($type, $overrides));
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

it('maps an auto-incrementing integer to id', function () {
    expect(mapSqlite('integer', ['name' => 'id', 'auto_increment' => true]))->toEqual(new Column('id', 'id'));
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
    'keyword' => ['datetime', 'CURRENT_TIMESTAMP', new Expression('CURRENT_TIMESTAMP')],
    'expression' => ['integer', '1 + 1', new Expression('(1 + 1)')],
]);

it('treats a missing or NULL default as no default', function (?string $default) {
    expect(mapSqlite('varchar', ['nullable' => true, 'default' => $default])->default)->toBeNull();
})->with([null, 'NULL', 'null']);
