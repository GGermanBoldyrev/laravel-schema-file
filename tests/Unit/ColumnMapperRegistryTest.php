<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Reader\Columns\ColumnMapperRegistry;
use GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers\SqliteColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\UnsupportedDriverException;

it('returns the mapper registered for a driver', function () {
    $mapper = new SqliteColumnMapper;

    expect((new ColumnMapperRegistry(['sqlite' => $mapper]))->for('sqlite'))->toBe($mapper);
});

it('names the driver and the supported ones when a driver has no mapper', function () {
    $registry = new ColumnMapperRegistry(['sqlite' => new SqliteColumnMapper, 'mysql' => new SqliteColumnMapper]);

    expect(fn () => $registry->for('pgsql'))->toThrow(
        UnsupportedDriverException::class,
        'The schema file cannot be generated for the [pgsql] database driver. Supported drivers: sqlite, mysql.',
    );
});

it('throws for any driver when it is empty', function () {
    expect(fn () => (new ColumnMapperRegistry)->for('sqlite'))->toThrow(UnsupportedDriverException::class);
});

it('adds a driver without changing the original registry', function () {
    $original = new ColumnMapperRegistry(['sqlite' => new SqliteColumnMapper]);
    $extended = $original->with('mysql', new SqliteColumnMapper);

    expect($extended->drivers())->toBe(['sqlite', 'mysql'])
        ->and($original->drivers())->toBe(['sqlite']);
});

it('replaces the mapper of a driver that is already registered', function () {
    $replacement = new SqliteColumnMapper;
    $registry = (new ColumnMapperRegistry(['sqlite' => new SqliteColumnMapper]))->with('sqlite', $replacement);

    expect($registry->for('sqlite'))->toBe($replacement)
        ->and($registry->drivers())->toBe(['sqlite']);
});

it('matches driver names exactly', function () {
    $registry = new ColumnMapperRegistry(['sqlite' => new SqliteColumnMapper]);

    expect(fn () => $registry->for('SQLite'))->toThrow(UnsupportedDriverException::class);
});
