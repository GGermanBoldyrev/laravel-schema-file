<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Reader\Columns\Drivers\PostgresColumnMapper;
use GGermanBoldyrev\SchemaFile\Reader\Columns\TableContext;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\Schema\Expression;
use Illuminate\Database\PostgresConnection;

/*
 * The mapper against columns exactly as Schema::getColumns() reports them on
 * PostgreSQL 17. The same cases run against a real server in
 * tests/Feature/Drivers; here they need no server, and reach the reports a
 * migration cannot easily produce.
 */

/**
 * A column the way Schema::getColumns() reports it: PostgreSQL gives a full
 * type ("character varying(255)") and a short internal name ("varchar").
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pgColumn(string $type, string $typeName, array $overrides = []): array
{
    return [
        'name' => 'col',
        'type_name' => $typeName,
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

function mapPg(string $type, string $typeName, array $overrides = []): Column
{
    // The mapper never talks to the database, so the connection only has to exist.
    $context = new TableContext(Mockery::mock(PostgresConnection::class), 'things');

    return (new PostgresColumnMapper)->map(pgColumn($type, $typeName, $overrides), $context);
}

describe('types', function () {
    it('maps a type to its Blueprint method and arguments', function (string $type, string $typeName, string $method, array $arguments = []) {
        expect(mapPg($type, $typeName))->toEqual(new Column('col', $method, $arguments));
    })->with([
        'varchar(255)' => ['character varying(255)', 'varchar', 'string'],
        'varchar(50)' => ['character varying(50)', 'varchar', 'string', [50]],
        'char(255)' => ['character(255)', 'bpchar', 'char'],
        'char(4)' => ['character(4)', 'bpchar', 'char', [4]],
        'char(26) is a ulid' => ['character(26)', 'bpchar', 'ulid'],
        'char(36) is just a char' => ['character(36)', 'bpchar', 'char', [36]],
        'text' => ['text', 'text', 'text'],
        'integer' => ['integer', 'int4', 'integer'],
        'smallint' => ['smallint', 'int2', 'smallInteger'],
        'bigint' => ['bigint', 'int8', 'bigInteger'],
        'boolean' => ['boolean', 'bool', 'boolean'],
        'numeric(8,2) is the default precision' => ['numeric(8,2)', 'numeric', 'decimal'],
        'numeric(10,4)' => ['numeric(10,4)', 'numeric', 'decimal', [10, 4]],
        'numeric(10,0)' => ['numeric(10,0)', 'numeric', 'decimal', [10, 0]],
        'double precision' => ['double precision', 'float8', 'double'],
        'real' => ['real', 'float4', 'float', [24]],
        'json' => ['json', 'json', 'json'],
        'jsonb' => ['jsonb', 'jsonb', 'jsonb'],
        'uuid' => ['uuid', 'uuid', 'uuid'],
        'date' => ['date', 'date', 'date'],
        'timestamp(0)' => ['timestamp(0) without time zone', 'timestamp', 'timestamp'],
        'timestamp(6)' => ['timestamp(6) without time zone', 'timestamp', 'timestamp', [6]],
        'timestamp without a precision' => ['timestamp without time zone', 'timestamp', 'timestamp', [null]],
        'timestamptz(0)' => ['timestamp(0) with time zone', 'timestamptz', 'timestampTz'],
        'timestamptz(3)' => ['timestamp(3) with time zone', 'timestamptz', 'timestampTz', [3]],
        'time(0)' => ['time(0) without time zone', 'time', 'time'],
        'time(3)' => ['time(3) without time zone', 'time', 'time', [3]],
        'time without a precision' => ['time without time zone', 'time', 'time', [null]],
        'timetz(0)' => ['time(0) with time zone', 'timetz', 'timeTz'],
        'bytea' => ['bytea', 'bytea', 'binary'],
        'inet' => ['inet', 'inet', 'ipAddress'],
        'macaddr' => ['macaddr', 'macaddr', 'macAddress'],
    ]);

    it('writes a type Blueprint has no method for back verbatim', function (string $type, string $typeName) {
        expect(mapPg($type, $typeName, ['nullable' => true]))->toEqual(new Column('col', 'rawColumn', [$type], nullable: true));
    })->with([
        'interval' => ['interval', 'interval'],
        'array' => ['integer[]', '_int4'],
        'unsized varchar' => ['character varying', 'varchar'],
        'unsized numeric' => ['numeric', 'numeric'],
        'a type of the application' => ['order_status', 'order_status'],
        'money' => ['money', 'money'],
        'xml' => ['xml', 'xml'],
        'tsvector' => ['tsvector', 'tsvector'],
        'cidr' => ['cidr', 'cidr'],
    ]);

    it('never marks a column unsigned, as PostgreSQL has no such thing', function () {
        expect(mapPg('integer', 'int4')->unsigned)->toBeFalse();
    });
});

describe('keys', function () {
    it('maps an auto-incrementing bigint to id, whatever its default says', function (?string $default) {
        $column = mapPg('bigint', 'int8', ['name' => 'id', 'auto_increment' => true, 'default' => $default]);

        expect($column)->toEqual(new Column('id', 'id'));
    })->with([
        'a serial column' => ["nextval('things_id_seq'::regclass)"],
        'an identity column' => [null],
    ]);

    it('maps the smaller auto-incrementing types to their increments method, without the sequence as a default', function (string $type, string $typeName, string $method) {
        $column = mapPg($type, $typeName, ['name' => 'n', 'auto_increment' => true, 'default' => "nextval('things_n_seq'::regclass)"]);

        expect($column)->toEqual(new Column('n', $method));
    })->with([
        ['integer', 'int4', 'increments'],
        ['smallint', 'int2', 'smallIncrements'],
    ]);

    it('does not take a bigint that does not increment for an id', function () {
        expect(mapPg('bigint', 'int8', ['name' => 'id'])->method)->toBe('bigInteger');
    });
});

describe('defaults, which PostgreSQL reports as SQL with a cast', function () {
    it('reads a default', function (string $type, string $typeName, string $default, mixed $expected) {
        expect(mapPg($type, $typeName, ['default' => $default])->default)->toEqual($expected);
    })->with([
        'string' => ['character varying(255)', 'varchar', "'draft'::character varying", 'draft'],
        'empty string' => ['character varying(255)', 'varchar', "''::character varying", ''],
        'string with a quote' => ['character varying(255)', 'varchar', "'it''s'::character varying", "it's"],
        'string with a cast in it' => ['character varying(255)', 'varchar', "'a::b'::character varying", 'a::b'],
        'numeric string' => ['character varying(255)', 'varchar', "'007'::character varying", '007'],
        'string that reads like a keyword' => ['character varying(255)', 'varchar', "'CURRENT_TIMESTAMP'::character varying", 'CURRENT_TIMESTAMP'],
        'string that reads like null' => ['character varying(255)', 'varchar', "'NULL'::character varying", 'NULL'],
        'string that reads like a boolean' => ['character varying(255)', 'varchar', "'true'::character varying", 'true'],
        'text' => ['text', 'text', "'long'::text", 'long'],
        'char' => ['character(4)', 'bpchar', "'abcd'::bpchar", 'abcd'],
        'integer' => ['integer', 'int4', '0', 0],
        'negative integer, which is cast' => ['integer', 'int4', "'-5'::integer", -5],
        'big integer' => ['bigint', 'int8', "'9000000000'::bigint", 9000000000],
        'boolean false' => ['boolean', 'bool', 'false', false],
        'boolean true' => ['boolean', 'bool', 'true', true],
        'decimal' => ['numeric(8,2)', 'numeric', '1.5', 1.5],
        'negative decimal' => ['numeric(8,2)', 'numeric', "'-1.5'::numeric", -1.5],
        'double' => ['double precision', 'float8', "'0.25'::double precision", 0.25],
        'date' => ['date', 'date', "'2024-01-31'::date", '2024-01-31'],
        'json' => ['jsonb', 'jsonb', "'{}'::jsonb", '{}'],
        'uuid' => ['uuid', 'uuid', "'00000000-0000-0000-0000-000000000000'::uuid", '00000000-0000-0000-0000-000000000000'],
        'a function call' => ['uuid', 'uuid', 'gen_random_uuid()', new Expression('(gen_random_uuid())')],
        'a parenthesised expression' => ['integer', 'int4', '(1 + 1)', new Expression('(1 + 1)')],
        'two cast strings joined' => ['text', 'text', "('a'::text || 'b'::text)", new Expression("('a'::text || 'b'::text)")],
        'a keyword' => ['date', 'date', 'CURRENT_DATE', new Expression('CURRENT_DATE')],
        'a boolean keyword on a column that is not a boolean' => ['integer', 'int4', 'true', new Expression('true')],
    ]);

    it('has no default when the server says NULL', function (string $default) {
        expect(mapPg('character varying(255)', 'varchar', ['nullable' => true, 'default' => $default])->default)->toBeNull();
    })->with(['NULL', 'NULL::character varying', 'null::text']);

    it('has no default when none is reported', function () {
        expect(mapPg('integer', 'int4')->default)->toBeNull();
    });
});

describe('the current time', function () {
    it('reads the current time as a default as useCurrent', function (string $type, string $typeName, string $default) {
        expect(mapPg($type, $typeName, ['default' => $default]))->useCurrent->toBeTrue()->default->toBeNull();
    })->with([
        'timestamp' => ['timestamp(0) without time zone', 'timestamp', 'CURRENT_TIMESTAMP'],
        'timestamptz' => ['timestamp(0) with time zone', 'timestamptz', 'CURRENT_TIMESTAMP'],
        'with a precision' => ['timestamp(6) without time zone', 'timestamp', 'CURRENT_TIMESTAMP(6)'],
    ]);

    it('keeps now() as the expression it is', function () {
        $column = mapPg('timestamp(0) without time zone', 'timestamp', ['default' => 'now()']);

        expect($column)->useCurrent->toBeFalse()->default->toEqual(new Expression('(now())'));
    });

    it('never reads an update rule, as PostgreSQL has none', function () {
        expect(mapPg('timestamp(0) without time zone', 'timestamp')->useCurrentOnUpdate)->toBeFalse();
    });
});

describe('everything else', function () {
    it('carries nullability and a comment over', function () {
        $column = mapPg('text', 'text', ['nullable' => true, 'comment' => "The user's note"]);

        expect($column)->nullable->toBeTrue()->comment->toBe("The user's note");
    });

    it('reads a stored generated column, NOT NULL as PostgreSQL makes it', function () {
        $column = mapPg('integer', 'int4', ['generation' => ['type' => 'stored', 'expression' => '(price * 2)']]);

        expect($column)->storedAs->toBe('(price * 2)')->virtualAs->toBeNull()->nullable->toBeFalse();
    });
});
