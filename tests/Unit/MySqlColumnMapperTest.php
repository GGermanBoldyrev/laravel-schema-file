<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers\MySqlColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\TableContext;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\Schema\Expression;
use Illuminate\Database\MySqlConnection;

/*
 * The mapper against columns exactly as Schema::getColumns() reports them on
 * MySQL 8.4 and MariaDB 11.4. The same cases run against real servers in
 * tests/Feature/Drivers; here they need no server, and reach the reports a
 * migration cannot easily produce.
 */

/**
 * A column the way Schema::getColumns() reports it. The type name is what precedes the size.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mysqlColumn(string $type, array $overrides = []): array
{
    return [
        'name' => 'col',
        'type_name' => preg_replace('/[( ].*$/', '', $type),
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
 * Map a column of the table "things".
 *
 * @param  string  $extra  What information_schema notes about the column, which the mapper asks for itself.
 */
function mapMysql(string $type, array $overrides = [], string $extra = '', bool $mariaDb = false, string $prefix = ''): Column
{
    $connection = Mockery::mock(MySqlConnection::class);
    $connection->allows('isMaria')->andReturn($mariaDb);
    $connection->allows('getDatabaseName')->andReturn('app');
    $connection->allows('getTablePrefix')->andReturn($prefix);
    $connection->allows('scalar')->andReturn($extra);

    return (new MySqlColumnMapper)->map(mysqlColumn($type, $overrides), new TableContext($connection, 'things'));
}

function mapMaria(string $type, array $overrides = [], string $extra = ''): Column
{
    return mapMysql($type, $overrides, $extra, mariaDb: true);
}

describe('types', function () {
    it('maps a type to its Blueprint method and arguments', function (string $type, string $method, array $arguments = []) {
        expect(mapMysql($type))->toEqual(new Column('col', $method, $arguments));
    })->with([
        'varchar(255)' => ['varchar(255)', 'string'],
        'varchar(50)' => ['varchar(50)', 'string', [50]],
        'char(255)' => ['char(255)', 'char'],
        'char(4)' => ['char(4)', 'char', [4]],
        'char(36) is a uuid' => ['char(36)', 'uuid'],
        'char(26) is a ulid' => ['char(26)', 'ulid'],
        'text' => ['text', 'text'],
        'tinytext' => ['tinytext', 'tinyText'],
        'mediumtext' => ['mediumtext', 'mediumText'],
        'longtext' => ['longtext', 'longText'],
        'int' => ['int', 'integer'],
        'tinyint' => ['tinyint', 'tinyInteger'],
        'smallint' => ['smallint', 'smallInteger'],
        'mediumint' => ['mediumint', 'mediumInteger'],
        'bigint' => ['bigint', 'bigInteger'],
        'int unsigned' => ['int unsigned', 'unsignedInteger'],
        'tinyint unsigned' => ['tinyint unsigned', 'unsignedTinyInteger'],
        'smallint unsigned' => ['smallint unsigned', 'unsignedSmallInteger'],
        'mediumint unsigned' => ['mediumint unsigned', 'unsignedMediumInteger'],
        'bigint unsigned' => ['bigint unsigned', 'unsignedBigInteger'],
        'tinyint(1) is a boolean' => ['tinyint(1)', 'boolean'],
        'decimal(8,2) is the default precision' => ['decimal(8,2)', 'decimal'],
        'decimal(10,4)' => ['decimal(10,4)', 'decimal', [10, 4]],
        'decimal(10,0)' => ['decimal(10,0)', 'decimal', [10, 0]],
        'float' => ['float', 'float', [24]],
        'double' => ['double', 'double'],
        'json' => ['json', 'json'],
        'enum' => ["enum('a','b')", 'enum', [['a', 'b']]],
        'set' => ["set('a','b')", 'set', [['a', 'b']]],
        'date' => ['date', 'date'],
        'datetime' => ['datetime', 'dateTime'],
        'datetime(3)' => ['datetime(3)', 'dateTime', [3]],
        'timestamp' => ['timestamp', 'timestamp'],
        'timestamp(6)' => ['timestamp(6)', 'timestamp', [6]],
        'time' => ['time', 'time'],
        'time(3)' => ['time(3)', 'time', [3]],
        'year' => ['year', 'year'],
        'blob' => ['blob', 'binary'],
        'binary(16)' => ['binary(16)', 'binary', [16, true]],
        'varbinary(16)' => ['varbinary(16)', 'binary', [16]],
    ]);

    it('ignores the display widths MariaDB adds to integer types', function (string $type, string $method) {
        expect(mapMaria($type))->toEqual(new Column('col', $method));
    })->with([
        ['int(11)', 'integer'],
        ['tinyint(4)', 'tinyInteger'],
        ['smallint(6)', 'smallInteger'],
        ['mediumint(9)', 'mediumInteger'],
        ['bigint(20)', 'bigInteger'],
        ['int(10) unsigned', 'unsignedInteger'],
        ['tinyint(3) unsigned', 'unsignedTinyInteger'],
        ['bigint(20) unsigned', 'unsignedBigInteger'],
        ['year(4)', 'year'],
        ['tinyint(1)', 'boolean'],
    ]);

    it('maps the uuid type MariaDB has of its own', function () {
        expect(mapMaria('uuid'))->toEqual(new Column('col', 'uuid'));
    });

    it('reads the options of an enum with awkward characters', function (string $type, array $options) {
        expect(mapMysql($type)->arguments)->toBe([$options]);
    })->with([
        'a quote' => ["enum('it''s','plain')", ["it's", 'plain']],
        'a comma' => ["enum('a,b','c')", ['a,b', 'c']],
        'a parenthesis' => ["enum('f(x)','g')", ['f(x)', 'g']],
        'a space' => ["enum('in review','done')", ['in review', 'done']],
        'an empty option' => ["enum('','a')", ['', 'a']],
        'a single option' => ["enum('only')", ['only']],
    ]);

    it('writes a type Blueprint has no method for back verbatim', function (string $type) {
        expect(mapMysql($type, ['nullable' => true]))->toEqual(new Column('col', 'rawColumn', [$type], nullable: true));
    })->with(['bit(4)', 'mediumblob', 'longblob', 'tinyblob', 'geometry', 'point', 'inet6']);

    it('chains unsigned() onto a fractional type, which has no unsigned method', function (string $type, string $method, array $arguments) {
        expect(mapMysql($type))->toEqual(new Column('col', $method, $arguments, unsigned: true));
    })->with([
        ['decimal(10,4) unsigned', 'decimal', [10, 4]],
        ['double unsigned', 'double', []],
        ['float unsigned', 'float', [24]],
    ]);

    it('does not chain unsigned() onto a method that already says so', function () {
        expect(mapMysql('int unsigned')->unsigned)->toBeFalse();
    });
});

describe('keys', function () {
    it('maps an unsigned auto-incrementing bigint to id', function () {
        expect(mapMysql('bigint unsigned', ['name' => 'id', 'auto_increment' => true]))->toEqual(new Column('id', 'id'));
    });

    it('maps the smaller unsigned auto-incrementing types to their increments method', function (string $type, string $method) {
        expect(mapMysql($type, ['name' => 'n', 'auto_increment' => true]))->toEqual(new Column('n', $method));
    })->with([
        ['int unsigned', 'increments'],
        ['mediumint unsigned', 'mediumIncrements'],
        ['smallint unsigned', 'smallIncrements'],
        ['tinyint unsigned', 'tinyIncrements'],
        ['int(10) unsigned', 'increments'],
    ]);

    it('marks a signed auto-incrementing column, which no single method creates', function (string $type, string $method) {
        expect(mapMysql($type, ['auto_increment' => true]))->toEqual(new Column('col', $method, autoIncrement: true));
    })->with([
        ['bigint', 'bigInteger'],
        ['int', 'integer'],
        ['smallint', 'smallInteger'],
    ]);

    it('does not take an unsigned bigint that does not increment for an id', function () {
        expect(mapMysql('bigint unsigned', ['name' => 'id'])->method)->toBe('unsignedBigInteger');
    });
});

describe('defaults on MySQL, which reports the bare value', function () {
    it('reads a literal', function (string $type, string $default, mixed $expected) {
        expect(mapMysql($type, ['default' => $default])->default)->toBe($expected);
    })->with([
        'string' => ['varchar(255)', 'draft', 'draft'],
        'empty string' => ['varchar(255)', '', ''],
        'string with a quote' => ['varchar(255)', "it's", "it's"],
        'numeric string' => ['varchar(255)', '007', '007'],
        'string that reads like a keyword' => ['varchar(255)', 'CURRENT_TIMESTAMP', 'CURRENT_TIMESTAMP'],
        'string that reads like null' => ['varchar(255)', 'NULL', 'NULL'],
        'string that reads like SQL' => ['varchar(255)', "'quoted'", "'quoted'"],
        'integer' => ['int', '0', 0],
        'negative integer' => ['int', '-5', -5],
        'unsigned integer' => ['int unsigned', '7', 7],
        'big integer' => ['bigint', '9000000000', 9000000000],
        'boolean false' => ['tinyint(1)', '0', false],
        'boolean true' => ['tinyint(1)', '1', true],
        'decimal' => ['decimal(8,2)', '1.50', 1.5],
        'double' => ['double', '0.25', 0.25],
        'year' => ['year', '2024', 2024],
        'date' => ['date', '2024-01-31', '2024-01-31'],
        'enum' => ["enum('a','b')", 'b', 'b'],
    ]);

    it('reads a default the server marks as generated as an expression', function (string $default, string $expected) {
        expect(mapMysql('int', ['default' => $default], extra: 'DEFAULT_GENERATED')->default)->toEqual(new Expression($expected));
    })->with([
        'already parenthesised' => ['(1 + 1)', '(1 + 1)'],
        'a function call' => ['uuid()', '(uuid())'],
        'two parenthesised parts' => ['(1) + (2)', '((1) + (2))'],
        'nested parentheses' => ['((1 + 1) * 2)', '((1 + 1) * 2)'],
    ]);

    it('has no default when none is reported', function () {
        expect(mapMysql('varchar(255)', ['nullable' => true])->default)->toBeNull();
    });
});

describe('defaults on MariaDB, which reports the SQL', function () {
    it('reads a default', function (string $type, string $default, mixed $expected) {
        expect(mapMaria($type, ['default' => $default])->default)->toEqual($expected);
    })->with([
        'string' => ['varchar(255)', "'draft'", 'draft'],
        'empty string' => ['varchar(255)', "''", ''],
        'string with a quote' => ['varchar(255)', "'it''s'", "it's"],
        'numeric string' => ['varchar(255)', "'007'", '007'],
        'string that reads like a keyword' => ['varchar(255)', "'CURRENT_TIMESTAMP'", 'CURRENT_TIMESTAMP'],
        'string that reads like null' => ['varchar(255)', "'NULL'", 'NULL'],
        'integer' => ['int(11)', '0', 0],
        'negative integer' => ['int(11)', '-5', -5],
        'boolean' => ['tinyint(1)', '1', true],
        'decimal' => ['decimal(8,2)', '1.50', 1.5],
        'enum' => ["enum('a','b')", "'b'", 'b'],
        'expression' => ['int(11)', '1 + 1', new Expression('(1 + 1)')],
        'function call' => ['varchar(255)', 'uuid()', new Expression('(uuid())')],
    ]);

    it('has no default when the server says NULL', function (string $default) {
        expect(mapMaria('varchar(255)', ['nullable' => true, 'default' => $default])->default)->toBeNull();
    })->with(['NULL', 'null']);

    it('does not ask the server for more when reading a default', function () {
        $connection = Mockery::mock(MySqlConnection::class);
        $connection->allows('isMaria')->andReturn(true);
        $connection->allows('getTablePrefix')->andReturn('');
        $connection->expects('scalar')->never();

        (new MySqlColumnMapper)->map(mysqlColumn('varchar(255)', ['default' => "'draft'"]), new TableContext($connection, 'things'));
    });

    it('is recognised behind a connection that uses the mysql driver', function () {
        // Before Laravel had a mariadb driver, MariaDB was reached through the mysql one.
        expect(mapMysql('varchar(255)', ['default' => "'draft'"], mariaDb: true)->default)->toBe('draft');
    });
});

describe('the current time', function () {
    it('reads the current time as a default as useCurrent', function (bool $mariaDb, string $type, string $default, string $method) {
        $column = mapMysql($type, ['default' => $default], extra: 'DEFAULT_GENERATED', mariaDb: $mariaDb);

        expect($column)->method->toBe($method)->useCurrent->toBeTrue()->default->toBeNull();
    })->with([
        'mysql timestamp' => [false, 'timestamp', 'CURRENT_TIMESTAMP', 'timestamp'],
        'mysql datetime' => [false, 'datetime', 'CURRENT_TIMESTAMP', 'dateTime'],
        'mysql with a precision' => [false, 'timestamp(6)', 'CURRENT_TIMESTAMP(6)', 'timestamp'],
        'mariadb timestamp' => [true, 'timestamp', 'current_timestamp()', 'timestamp'],
        'mariadb with a precision' => [true, 'timestamp(6)', 'current_timestamp(6)', 'timestamp'],
    ]);

    it('reads an update to the current time as useCurrentOnUpdate', function (bool $mariaDb, string $extra) {
        $column = mapMysql('timestamp', ['nullable' => true], extra: $extra, mariaDb: $mariaDb);

        expect($column)->useCurrentOnUpdate->toBeTrue()->useCurrent->toBeFalse()->default->toBeNull();
    })->with([
        'mysql' => [false, 'on update CURRENT_TIMESTAMP'],
        'mysql with a default as well' => [false, 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP'],
        'mariadb' => [true, 'on update current_timestamp()'],
    ]);

    it('reads neither on an ordinary timestamp', function () {
        expect(mapMysql('timestamp', ['nullable' => true]))->useCurrent->toBeFalse()->useCurrentOnUpdate->toBeFalse();
    });

    it('does not read a fixed moment as the current time', function () {
        $column = mapMysql('timestamp', ['default' => '2024-01-01 00:00:00']);

        expect($column)->useCurrent->toBeFalse()->default->toBe('2024-01-01 00:00:00');
    });

    it('does not look for an update rule on a column that is not a timestamp', function () {
        expect(mapMysql('varchar(255)', extra: 'on update CURRENT_TIMESTAMP')->useCurrentOnUpdate)->toBeFalse();
    });
});

it('asks about the column under its real, prefixed table name', function () {
    $connection = Mockery::mock(MySqlConnection::class);
    $connection->allows('isMaria')->andReturn(false);
    $connection->allows('getDatabaseName')->andReturn('app');
    $connection->allows('getTablePrefix')->andReturn('app_');
    $connection->expects('scalar')
        ->withArgs(fn (string $sql, array $bindings) => $bindings === ['app', 'app_things', 'col'])
        ->andReturn('DEFAULT_GENERATED');

    $column = (new MySqlColumnMapper)->map(mysqlColumn('int', ['default' => 'rand()']), new TableContext($connection, 'things'));

    expect($column->default)->toEqual(new Expression('(rand())'));
});

it('treats a missing note from the server as none', function () {
    $connection = Mockery::mock(MySqlConnection::class);
    $connection->allows('isMaria')->andReturn(false);
    $connection->allows('getDatabaseName')->andReturn('app');
    $connection->allows('getTablePrefix')->andReturn('');
    $connection->allows('scalar')->andReturn(null);

    $column = (new MySqlColumnMapper)->map(mysqlColumn('int', ['default' => '5']), new TableContext($connection, 'things'));

    expect($column->default)->toBe(5);
});

describe('everything else', function () {
    it('carries nullability over', function () {
        expect(mapMysql('varchar(255)', ['nullable' => true])->nullable)->toBeTrue();
    });

    it('carries a comment over, and reads an empty one as none', function () {
        expect(mapMysql('varchar(255)', ['comment' => "The user's name"])->comment)->toBe("The user's name")
            ->and(mapMysql('varchar(255)', ['comment' => ''])->comment)->toBeNull();
    });

    it('reads a generated column', function (string $kind, string $property) {
        $column = mapMysql('int', ['nullable' => true, 'generation' => ['type' => $kind, 'expression' => '(`price` * 2)']]);

        expect($column->{$property})->toBe('(`price` * 2)')->and($column->default)->toBeNull();
    })->with([['virtual', 'virtualAs'], ['stored', 'storedAs']]);
});
