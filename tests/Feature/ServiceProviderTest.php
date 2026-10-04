<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Console\ConsoleNotifier;
use GGermanBoldyrev\SchemaFile\Contracts\ColumnMapper;
use GGermanBoldyrev\SchemaFile\Mapper\ColumnMapperRegistry;
use GGermanBoldyrev\SchemaFile\Mapper\Drivers\MySqlColumnMapper;
use GGermanBoldyrev\SchemaFile\Mapper\Drivers\PostgresColumnMapper;
use GGermanBoldyrev\SchemaFile\Mapper\Drivers\SqliteColumnMapper;
use GGermanBoldyrev\SchemaFile\Mapper\TableContext;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use GGermanBoldyrev\SchemaFile\SchemaFileGenerator;
use GGermanBoldyrev\SchemaFile\SchemaFileServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

it('merges the package defaults into the configuration', function () {
    expect(config('schema-file'))->toHaveKeys(['enabled', 'path', 'connection', 'except'])
        ->and(config('schema-file.connection'))->toBeNull()
        ->and(config('schema-file.except'))->toBe([]);
});

it('defaults the path to database/schema.php', function () {
    $defaults = require __DIR__.'/../../config/schema-file.php';

    expect($defaults['path'])->toBe(database_path('schema.php'));
});

it('offers its config file for publishing', function () {
    $paths = ServiceProvider::pathsToPublish(SchemaFileServiceProvider::class, 'schema-file-config');

    expect($paths)->toHaveCount(1)
        ->and(realpath(array_key_first($paths)))->toBe(realpath(__DIR__.'/../../config/schema-file.php'))
        ->and(array_values($paths))->toBe([config_path('schema-file.php')]);
});

it('builds the settings from the current configuration every time', function () {
    $before = app(SchemaFileConfig::class);

    config(['schema-file.path' => '/somewhere/else.php']);

    expect(app(SchemaFileConfig::class)->path)->toBe('/somewhere/else.php')
        ->and($before->path)->toBe($this->schemaPath());
});

it('shares one console notifier', function () {
    expect(app(ConsoleNotifier::class))->toBe(app(ConsoleNotifier::class));
});

it('shares one registry that knows every supported driver', function () {
    $registry = app(ColumnMapperRegistry::class);

    expect($registry)->toBe(app(ColumnMapperRegistry::class))
        ->and($registry->drivers())->toBe(['sqlite', 'mysql', 'mariadb', 'pgsql'])
        ->and($registry->for('sqlite'))->toBeInstanceOf(SqliteColumnMapper::class)
        ->and($registry->for('mysql'))->toBeInstanceOf(MySqlColumnMapper::class)
        ->and($registry->for('mariadb'))->toBeInstanceOf(MySqlColumnMapper::class)
        ->and($registry->for('pgsql'))->toBeInstanceOf(PostgresColumnMapper::class);
});

it('lets an application replace the mapper of a driver', function () {
    app()->extend(ColumnMapperRegistry::class, fn (ColumnMapperRegistry $registry) => $registry->with(
        'sqlite',
        new class implements ColumnMapper
        {
            public function map(array $column, TableContext $table): Column
            {
                return new Column($column['name'], 'custom', [$table->name]);
            }
        },
    ));

    Schema::create('users', fn (Blueprint $table) => $table->string('email'));

    app(SchemaFileGenerator::class)->generate(app(SchemaFileConfig::class));

    expect(file_get_contents($this->schemaPath()))->toContain("\$table->custom('email', 'users');");
});
