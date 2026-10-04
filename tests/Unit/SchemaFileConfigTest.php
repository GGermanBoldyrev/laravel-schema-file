<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use Illuminate\Config\Repository;

/**
 * @param  array<string, mixed>  $schemaFile
 * @param  array<string, mixed>  $database
 */
function repository(array $schemaFile = [], array $database = ['migrations' => ['table' => 'migrations']]): Repository
{
    return new Repository([
        'schema-file' => ['enabled' => null, 'path' => '/app/database/schema.php', 'connection' => null, 'except' => [], ...$schemaFile],
        'database' => $database,
    ]);
}

it('reads the defaults', function () {
    expect(SchemaFileConfig::fromRepository(repository()))->toEqual(new SchemaFileConfig(
        path: '/app/database/schema.php',
        connection: null,
        except: ['migrations'],
        enabled: false,
    ));
});

describe('path', function () {
    it('must be a string', function (mixed $path) {
        expect(fn () => SchemaFileConfig::fromRepository(repository(['path' => $path])))
            ->toThrow(InvalidArgumentException::class, 'schema-file.path');
    })->with([null, 123, true, [['a']]]);
});

describe('connection', function () {
    it('reads a connection name', function () {
        expect(SchemaFileConfig::fromRepository(repository(['connection' => 'analytics']))->connection)->toBe('analytics');
    });

    it('treats an empty value as the default connection', function (mixed $connection) {
        expect(SchemaFileConfig::fromRepository(repository(['connection' => $connection]))->connection)->toBeNull();
    })->with([null, '']);

    it('must be a string when set', function () {
        expect(fn () => SchemaFileConfig::fromRepository(repository(['connection' => 5])))
            ->toThrow(InvalidArgumentException::class, 'schema-file.connection');
    });
});

describe('enabled', function () {
    it('follows the environment when not configured', function (mixed $enabled, bool $local) {
        expect(SchemaFileConfig::fromRepository(repository(['enabled' => $enabled]), $local)->enabled)->toBe($local);
    })->with([null, ''])->with([true, false]);

    it('lets an explicit value win over the environment', function (bool $enabled, bool $local) {
        expect(SchemaFileConfig::fromRepository(repository(['enabled' => $enabled]), $local)->enabled)->toBe($enabled);
    })->with([true, false])->with([true, false]);

    it('must be a boolean when set', function (mixed $enabled) {
        expect(fn () => SchemaFileConfig::fromRepository(repository(['enabled' => $enabled])))
            ->toThrow(InvalidArgumentException::class, 'schema-file.enabled');
    })->with(['yes', 1, 0, 'true']);
});

describe('except', function () {
    it('puts the migrations table first, then the configured tables', function () {
        $config = SchemaFileConfig::fromRepository(repository(['except' => ['jobs', 'telescope_*']]));

        expect($config->except)->toBe(['migrations', 'jobs', 'telescope_*']);
    });

    it('uses the configured name of the migrations table', function () {
        $config = SchemaFileConfig::fromRepository(repository(database: ['migrations' => ['table' => 'schema_versions']]));

        expect($config->except)->toBe(['schema_versions']);
    });

    it('understands the migrations table given as a plain string', function () {
        $config = SchemaFileConfig::fromRepository(repository(database: ['migrations' => 'legacy_migrations']));

        expect($config->except)->toBe(['legacy_migrations']);
    });

    it('excludes nothing extra when the migrations table is not configured', function (array $database) {
        expect(SchemaFileConfig::fromRepository(repository(database: $database))->except)->toBe([]);
    })->with([
        'no database config' => [[]],
        'no table key' => [['migrations' => ['update_date_on_publish' => true]]],
        'table is not a string' => [['migrations' => ['table' => null]]],
    ]);

    it('ignores the keys of the configured list', function () {
        $config = SchemaFileConfig::fromRepository(repository(['except' => ['a' => 'jobs', 7 => 'cache']]));

        expect($config->except)->toBe(['migrations', 'jobs', 'cache']);
    });

    it('must be an array', function (mixed $except) {
        expect(fn () => SchemaFileConfig::fromRepository(repository(['except' => $except])))
            ->toThrow(InvalidArgumentException::class, 'schema-file.except');
    })->with(['jobs', null, 5]);

    it('must hold only table names', function (mixed $entry) {
        expect(fn () => SchemaFileConfig::fromRepository(repository(['except' => ['jobs', $entry]])))
            ->toThrow(InvalidArgumentException::class, 'Configuration value for key [schema-file.except] must be a list of table names.');
    })->with([5, null, true, [['nested']]]);
});

describe('with', function () {
    $config = new SchemaFileConfig('/a.php', 'main', ['jobs'], true);

    it('replaces only what is given', function () use ($config) {
        expect($config->with(path: '/b.php'))->toEqual(new SchemaFileConfig('/b.php', 'main', ['jobs'], true))
            ->and($config->with(connection: 'other'))->toEqual(new SchemaFileConfig('/a.php', 'other', ['jobs'], true))
            ->and($config->with('/b.php', 'other'))->toEqual(new SchemaFileConfig('/b.php', 'other', ['jobs'], true));
    });

    it('changes nothing when nothing is given', function () use ($config) {
        expect($config->with())->toEqual($config)->not->toBe($config);
    });

    it('leaves the original untouched', function () use ($config) {
        $config->with(path: '/b.php', connection: 'other');

        expect($config->path)->toBe('/a.php')->and($config->connection)->toBe('main');
    });

    it('cannot reset the connection to the default one', function () use ($config) {
        expect($config->with(connection: null)->connection)->toBe('main');
    });
});
