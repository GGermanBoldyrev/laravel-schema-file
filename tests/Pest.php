<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use GGermanBoldyrev\SchemaFile\SchemaFileGenerator;
use GGermanBoldyrev\SchemaFile\Tests\Support\Servers;
use GGermanBoldyrev\SchemaFile\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

pest()->extend(TestCase::class)->in('Feature');

const ALL_DRIVERS = ['sqlite', 'mysql', 'mariadb', 'pgsql'];

/**
 * The name of an empty connection for the driver. Tests of a server that
 * TEST_DATABASES does not name are skipped: `composer test:all` runs them all.
 */
function useDriver(string $driver, bool $prefixed = false): string
{
    if ($driver === 'sqlite') {
        return $prefixed ? 'prefixed' : 'testing';
    }

    if (! in_array($driver, Servers::enabled(), true)) {
        test()->markTestSkipped("The {$driver} tests need a server: run `composer test:all`.");
    }

    Schema::connection($driver)->dropAllTables();

    return $prefixed ? $driver.'_prefixed' : $driver;
}

/**
 * The schema file generated from the connection.
 */
function generatedSchema(string $connection, string $file = 'schema.php'): string
{
    $path = test()->workspace.'/'.$file;

    app(SchemaFileGenerator::class)->generate(
        app(SchemaFileConfig::class)->with(path: $path, connection: $connection),
    );

    return (string) file_get_contents($path);
}

/**
 * The lines inside a table's Schema::create() block, or its Schema::table() block of foreign keys.
 *
 * @return list<string>
 */
function tableLines(string $schema, string $table, string $block = 'create'): array
{
    $opening = "Schema::{$block}('{$table}', function (Blueprint \$table) {\n";
    $start = strpos($schema, $opening);

    if ($start === false) {
        return [];
    }

    $start += strlen($opening);
    $body = rtrim(substr($schema, $start, strpos($schema, '});', $start) - $start), "\n");

    return $body === '' ? [] : array_map('trim', explode("\n", $body));
}

/**
 * Generate the schema file, empty the database, rebuild it from the file and
 * generate again. A faithful file gives the same file twice.
 *
 * @return array{string, string}
 */
function roundTripOn(string $connection): array
{
    $first = generatedSchema($connection, 'first.php');

    Schema::connection($connection)->dropAllTables();

    $default = DB::getDefaultConnection();
    DB::setDefaultConnection($connection);

    try {
        require test()->workspace.'/first.php';
    } finally {
        DB::setDefaultConnection($default);
    }

    return [$first, generatedSchema($connection, 'second.php')];
}

/**
 * Turn rows of [label, definition, expectations] into one dataset entry per driver.
 *
 * Expectations are keyed by driver, by several drivers joined with "|", or by
 * "*" for every driver not named otherwise. A driver whose expectation is false
 * has no such case.
 *
 * @param  list<array{string, Closure, array<string, string|list<string>|false>}>  $rows
 * @return array<string, array{string, Closure, string|list<string>}>
 */
function perDriver(array $rows): array
{
    $cases = [];

    foreach ($rows as [$label, $define, $expectations]) {
        foreach (ALL_DRIVERS as $driver) {
            $expected = $expectations['*'] ?? false;

            foreach ($expectations as $drivers => $value) {
                if (in_array($driver, explode('|', $drivers), true)) {
                    $expected = $value;
                }
            }

            if ($expected !== false) {
                $cases["{$driver}: {$label}"] = [$driver, $define, $expected];
            }
        }
    }

    return $cases;
}
