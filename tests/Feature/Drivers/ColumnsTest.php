<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * What you write in a migration, and what the schema file says after each
 * database has stored it. Where a database keeps less than the migration said,
 * the file shows what is really there.
 */

it('writes a column the way the database stores it', function (string $driver, Closure $define, string $expected) {
    $connection = useDriver($driver);

    Schema::connection($connection)->create('things', function (Blueprint $table) use ($define) {
        $define($table);
    });

    expect(tableLines(generatedSchema($connection), 'things'))->toBe(["\$table->{$expected};"]);
})->with(perDriver([
    // Strings
    ['string', fn (Blueprint $t) => $t->string('c'), ['*' => "string('c')"]],
    ['string with a length', fn (Blueprint $t) => $t->string('c', 50), ['*' => "string('c', 50)", 'sqlite' => "string('c')"]],
    ['char', fn (Blueprint $t) => $t->char('c', 4), ['*' => "char('c', 4)", 'sqlite' => "string('c')"]],
    ['text', fn (Blueprint $t) => $t->text('c'), ['*' => "text('c')"]],
    ['tinyText', fn (Blueprint $t) => $t->tinyText('c'), ['mysql|mariadb' => "tinyText('c')", 'sqlite' => "text('c')", 'pgsql' => "string('c')"]],
    ['mediumText', fn (Blueprint $t) => $t->mediumText('c'), ['mysql|mariadb' => "mediumText('c')", '*' => "text('c')"]],
    ['longText', fn (Blueprint $t) => $t->longText('c'), ['mysql|mariadb' => "longText('c')", '*' => "text('c')"]],

    // Integers
    ['integer', fn (Blueprint $t) => $t->integer('c'), ['*' => "integer('c')"]],
    ['tinyInteger', fn (Blueprint $t) => $t->tinyInteger('c'), ['mysql|mariadb' => "tinyInteger('c')", 'sqlite' => "integer('c')", 'pgsql' => "smallInteger('c')"]],
    ['smallInteger', fn (Blueprint $t) => $t->smallInteger('c'), ['*' => "smallInteger('c')", 'sqlite' => "integer('c')"]],
    ['mediumInteger', fn (Blueprint $t) => $t->mediumInteger('c'), ['mysql|mariadb' => "mediumInteger('c')", '*' => "integer('c')"]],
    ['bigInteger', fn (Blueprint $t) => $t->bigInteger('c'), ['*' => "bigInteger('c')", 'sqlite' => "integer('c')"]],
    ['unsignedTinyInteger', fn (Blueprint $t) => $t->unsignedTinyInteger('c'), ['mysql|mariadb' => "unsignedTinyInteger('c')", 'sqlite' => "integer('c')", 'pgsql' => "smallInteger('c')"]],
    ['unsignedSmallInteger', fn (Blueprint $t) => $t->unsignedSmallInteger('c'), ['mysql|mariadb' => "unsignedSmallInteger('c')", 'sqlite' => "integer('c')", 'pgsql' => "smallInteger('c')"]],
    ['unsignedMediumInteger', fn (Blueprint $t) => $t->unsignedMediumInteger('c'), ['mysql|mariadb' => "unsignedMediumInteger('c')", '*' => "integer('c')"]],
    ['unsignedInteger', fn (Blueprint $t) => $t->unsignedInteger('c'), ['mysql|mariadb' => "unsignedInteger('c')", '*' => "integer('c')"]],
    ['unsignedBigInteger', fn (Blueprint $t) => $t->unsignedBigInteger('c'), ['mysql|mariadb' => "unsignedBigInteger('c')", 'sqlite' => "integer('c')", 'pgsql' => "bigInteger('c')"]],
    ['foreignId', fn (Blueprint $t) => $t->foreignId('c'), ['mysql|mariadb' => "unsignedBigInteger('c')", 'sqlite' => "integer('c')", 'pgsql' => "bigInteger('c')"]],
    ['year', fn (Blueprint $t) => $t->year('c'), ['mysql|mariadb' => "year('c')", '*' => "integer('c')"]],

    // Numbers with a fraction
    ['decimal', fn (Blueprint $t) => $t->decimal('c'), ['*' => "decimal('c')"]],
    ['decimal with a precision', fn (Blueprint $t) => $t->decimal('c', 10, 4), ['*' => "decimal('c', 10, 4)", 'sqlite' => "decimal('c')"]],
    ['unsigned decimal', fn (Blueprint $t) => $t->decimal('c', 10, 4)->unsigned(), ['mysql|mariadb' => "decimal('c', 10, 4)->unsigned()", 'sqlite' => "decimal('c')", 'pgsql' => "decimal('c', 10, 4)"]],
    ['float, which is a double unless given a precision', fn (Blueprint $t) => $t->float('c'), ['*' => "double('c')", 'sqlite' => "float('c')"]],
    ['single-precision float', fn (Blueprint $t) => $t->float('c', 10), ['*' => "float('c', 24)", 'sqlite' => "float('c')"]],
    ['double', fn (Blueprint $t) => $t->double('c'), ['*' => "double('c')"]],

    // Booleans, JSON, identifiers
    ['boolean', fn (Blueprint $t) => $t->boolean('c'), ['*' => "boolean('c')"]],
    ['json', fn (Blueprint $t) => $t->json('c'), ['mysql|pgsql' => "json('c')", 'mariadb' => "longText('c')", 'sqlite' => "text('c')"]],
    ['jsonb', fn (Blueprint $t) => $t->jsonb('c'), ['pgsql' => "jsonb('c')", 'mysql' => "json('c')", 'mariadb' => "longText('c')", 'sqlite' => "text('c')"]],
    ['uuid', fn (Blueprint $t) => $t->uuid('c'), ['*' => "uuid('c')", 'sqlite' => "string('c')"]],
    ['ulid', fn (Blueprint $t) => $t->ulid('c'), ['*' => "ulid('c')", 'sqlite' => "string('c')"]],
    ['enum', fn (Blueprint $t) => $t->enum('c', ['draft', 'in review']), ['mysql|mariadb' => "enum('c', ['draft', 'in review'])", '*' => "string('c')"]],
    ['set', fn (Blueprint $t) => $t->set('c', ['a', 'b']), ['mysql|mariadb' => "set('c', ['a', 'b'])"]],
    ['ipAddress', fn (Blueprint $t) => $t->ipAddress('c'), ['pgsql' => "ipAddress('c')", 'mysql|mariadb' => "string('c', 45)", 'sqlite' => "string('c')"]],
    ['macAddress', fn (Blueprint $t) => $t->macAddress('c'), ['pgsql' => "macAddress('c')", 'mysql|mariadb' => "string('c', 17)", 'sqlite' => "string('c')"]],

    // Dates and times
    ['date', fn (Blueprint $t) => $t->date('c'), ['*' => "date('c')"]],
    ['time', fn (Blueprint $t) => $t->time('c'), ['*' => "time('c')"]],
    ['time with a precision', fn (Blueprint $t) => $t->time('c', 3), ['*' => "time('c', 3)", 'sqlite' => "time('c')"]],
    ['timeTz', fn (Blueprint $t) => $t->timeTz('c'), ['pgsql' => "timeTz('c')", '*' => "time('c')"]],
    ['dateTime', fn (Blueprint $t) => $t->dateTime('c'), ['mysql|mariadb' => "dateTime('c')", '*' => "timestamp('c')"]],
    ['dateTime with a precision', fn (Blueprint $t) => $t->dateTime('c', 3), ['mysql|mariadb' => "dateTime('c', 3)", 'pgsql' => "timestamp('c', 3)", 'sqlite' => "timestamp('c')"]],
    ['dateTimeTz', fn (Blueprint $t) => $t->dateTimeTz('c'), ['mysql|mariadb' => "dateTime('c')", 'pgsql' => "timestampTz('c')", 'sqlite' => "timestamp('c')"]],
    ['timestamp', fn (Blueprint $t) => $t->timestamp('c'), ['*' => "timestamp('c')"]],
    ['timestamp with a precision', fn (Blueprint $t) => $t->timestamp('c', 6), ['*' => "timestamp('c', 6)", 'sqlite' => "timestamp('c')"]],
    ['timestampTz', fn (Blueprint $t) => $t->timestampTz('c'), ['pgsql' => "timestampTz('c')", '*' => "timestamp('c')"]],
    ['softDeletes', fn (Blueprint $t) => $t->softDeletes('c'), ['*' => "timestamp('c')->nullable()"]],

    // Binary
    ['binary', fn (Blueprint $t) => $t->binary('c'), ['*' => "binary('c')"]],
    ['binary with a length', fn (Blueprint $t) => $t->binary('c', 16), ['mysql|mariadb' => "binary('c', 16)", '*' => "binary('c')"]],
    ['fixed-length binary', fn (Blueprint $t) => $t->binary('c', 16, true), ['mysql|mariadb' => "binary('c', 16, true)", '*' => "binary('c')"]],

    // Nullability and comments
    ['nullable', fn (Blueprint $t) => $t->string('c')->nullable(), ['*' => "string('c')->nullable()"]],
    ['nullable with a null default', fn (Blueprint $t) => $t->string('c')->nullable()->default(null), ['*' => "string('c')->nullable()"]],
    ['comment', fn (Blueprint $t) => $t->string('c')->comment("The user's name"), ['*' => "string('c')->comment('The user\\'s name')", 'sqlite' => "string('c')"]],

    // Defaults
    ['string default', fn (Blueprint $t) => $t->string('c')->default('draft'), ['*' => "string('c')->default('draft')"]],
    ['empty string default', fn (Blueprint $t) => $t->string('c')->default(''), ['*' => "string('c')->default('')"]],
    ['default with a quote', fn (Blueprint $t) => $t->string('c')->default("it's"), ['*' => "string('c')->default('it\\'s')"]],
    // Laravel sends a default to MySQL without escaping it, and MySQL reads a backslash as
    // an escape character: what the migration asked for is not what ends up in the database.
    ['default with a backslash', fn (Blueprint $t) => $t->string('c')->default('App\\Models\\User'), [
        '*' => "string('c')->default('App\\\\Models\\\\User')",
        'mysql|mariadb' => "string('c')->default('AppModelsUser')",
    ]],
    ['numeric string default', fn (Blueprint $t) => $t->string('c')->default('007'), ['*' => "string('c')->default('007')"]],
    ['string default that reads like a keyword', fn (Blueprint $t) => $t->string('c')->default('CURRENT_TIMESTAMP'), ['*' => "string('c')->default('CURRENT_TIMESTAMP')"]],
    ['string default that reads like null', fn (Blueprint $t) => $t->string('c')->default('NULL'), ['*' => "string('c')->default('NULL')"]],
    ['integer default', fn (Blueprint $t) => $t->integer('c')->default(0), ['*' => "integer('c')->default(0)"]],
    ['negative integer default', fn (Blueprint $t) => $t->integer('c')->default(-5), ['*' => "integer('c')->default(-5)"]],
    ['big integer default', fn (Blueprint $t) => $t->bigInteger('c')->default(9000000000), ['*' => "bigInteger('c')->default(9000000000)", 'sqlite' => "integer('c')->default(9000000000)"]],
    ['boolean default false', fn (Blueprint $t) => $t->boolean('c')->default(false), ['*' => "boolean('c')->default(false)"]],
    ['boolean default true', fn (Blueprint $t) => $t->boolean('c')->default(true), ['*' => "boolean('c')->default(true)"]],
    ['decimal default', fn (Blueprint $t) => $t->decimal('c')->default(1.5), ['*' => "decimal('c')->default(1.5)"]],
    ['double default', fn (Blueprint $t) => $t->double('c')->default(0.25), ['*' => "double('c')->default(0.25)"]],
    ['date default', fn (Blueprint $t) => $t->date('c')->default('2024-01-31'), ['*' => "date('c')->default('2024-01-31')"]],
    ['enum default', fn (Blueprint $t) => $t->enum('c', ['a', 'b'])->default('b'), ['mysql|mariadb' => "enum('c', ['a', 'b'])->default('b')", '*' => "string('c')->default('b')"]],
    ['expression default', fn (Blueprint $t) => $t->integer('c')->default(DB::raw('(1 + 1)')), ['*' => "integer('c')->default(DB::raw('(1 + 1)'))"]],

    // The current time
    ['useCurrent', fn (Blueprint $t) => $t->timestamp('c')->useCurrent(), ['*' => "timestamp('c')->useCurrent()"]],
    ['useCurrent on a dateTime', fn (Blueprint $t) => $t->dateTime('c')->useCurrent(), ['mysql|mariadb' => "dateTime('c')->useCurrent()", '*' => "timestamp('c')->useCurrent()"]],
    ['useCurrent with a precision', fn (Blueprint $t) => $t->timestamp('c', 6)->useCurrent(), ['*' => "timestamp('c', 6)->useCurrent()", 'sqlite' => "timestamp('c')->useCurrent()"]],
    ['useCurrentOnUpdate', fn (Blueprint $t) => $t->timestamp('c')->useCurrent()->useCurrentOnUpdate(), ['mysql|mariadb' => "timestamp('c')->useCurrent()->useCurrentOnUpdate()", '*' => "timestamp('c')->useCurrent()"]],
    ['useCurrentOnUpdate alone', fn (Blueprint $t) => $t->timestamp('c')->nullable()->useCurrentOnUpdate(), ['mysql|mariadb' => "timestamp('c')->nullable()->useCurrentOnUpdate()", '*' => "timestamp('c')->nullable()"]],
]));

it('writes a key column the way the database stores it', function (string $driver, Closure $define, string $expected) {
    $connection = useDriver($driver);

    Schema::connection($connection)->create('things', function (Blueprint $table) use ($define) {
        $define($table);
    });

    expect(tableLines(generatedSchema($connection), 'things'))->toBe(["\$table->{$expected};"]);
})->with(perDriver([
    ['id', fn (Blueprint $t) => $t->id(), ['*' => 'id()']],
    ['id under another name', fn (Blueprint $t) => $t->id('uid'), ['*' => "id('uid')"]],
    ['bigIncrements', fn (Blueprint $t) => $t->bigIncrements('n'), ['*' => "id('n')"]],
    ['increments', fn (Blueprint $t) => $t->increments('n'), ['*' => "increments('n')", 'sqlite' => "id('n')"]],
    ['mediumIncrements', fn (Blueprint $t) => $t->mediumIncrements('n'), ['mysql|mariadb' => "mediumIncrements('n')", 'pgsql' => "increments('n')", 'sqlite' => "id('n')"]],
    ['smallIncrements', fn (Blueprint $t) => $t->smallIncrements('n'), ['*' => "smallIncrements('n')", 'sqlite' => "id('n')"]],
    ['tinyIncrements', fn (Blueprint $t) => $t->tinyIncrements('n'), ['mysql|mariadb' => "tinyIncrements('n')", 'pgsql' => "smallIncrements('n')", 'sqlite' => "id('n')"]],
    ['signed auto-incrementing key', fn (Blueprint $t) => $t->bigInteger('n')->autoIncrement(), ['mysql|mariadb' => "bigInteger('n')->autoIncrement()", '*' => "id('n')"]],
    ['integer key that does not increment', fn (Blueprint $t) => $t->integer('id')->primary(), ['*' => "integer('id')->primary()"]],
    ['string key', fn (Blueprint $t) => $t->string('code', 20)->primary(), ['*' => "string('code', 20)->primary()", 'sqlite' => "string('code')->primary()"]],
    ['uuid key', fn (Blueprint $t) => $t->uuid('id')->primary(), ['*' => "uuid('id')->primary()", 'sqlite' => "string('id')->primary()"]],
    ['ulid key', fn (Blueprint $t) => $t->ulid('id')->primary(), ['*' => "ulid('id')->primary()", 'sqlite' => "string('id')->primary()"]],
]));

it('writes a generated column the way the database stores it', function (string $driver, Closure $define, string $expected) {
    $connection = useDriver($driver);

    Schema::connection($connection)->create('things', function (Blueprint $table) use ($define) {
        $table->integer('price');
        $define($table);
    });

    expect(tableLines(generatedSchema($connection), 'things'))->toBe(["\$table->integer('price');", "\$table->{$expected};"]);
})->with(perDriver([
    // PostgreSQL has no virtual columns before version 18, and keeps a stored one NOT NULL unless told otherwise.
    ['virtual', fn (Blueprint $t) => $t->integer('c')->virtualAs('price * 2'), [
        'sqlite' => "integer('c')->virtualAs('price * 2')->nullable()",
        'mysql' => "integer('c')->virtualAs('(`price` * 2)')->nullable()",
        'mariadb' => "integer('c')->virtualAs('`price` * 2')->nullable()",
    ]],
    ['stored', fn (Blueprint $t) => $t->integer('c')->storedAs('price * 2'), [
        'sqlite' => "integer('c')->storedAs('price * 2')->nullable()",
        'mysql' => "integer('c')->storedAs('(`price` * 2)')->nullable()",
        'mariadb' => "integer('c')->storedAs('`price` * 2')->nullable()",
        'pgsql' => "integer('c')->storedAs('(price * 2)')->nullable(false)",
    ]],
    ['stored and nullable', fn (Blueprint $t) => $t->integer('c')->storedAs('price * 2')->nullable(), [
        'sqlite' => "integer('c')->storedAs('price * 2')->nullable()",
        'mysql' => "integer('c')->storedAs('(`price` * 2)')->nullable()",
        'mariadb' => "integer('c')->storedAs('`price` * 2')->nullable()",
        'pgsql' => "integer('c')->storedAs('(price * 2)')->nullable()",
    ]],
]));

it('folds the timestamps of a table on every database', function (string $driver) {
    $connection = useDriver($driver);

    Schema::connection($connection)->create('things', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    expect(tableLines(generatedSchema($connection), 'things'))->toBe(['$table->id();', '$table->timestamps();']);
})->with(ALL_DRIVERS);

it('writes a type Blueprint has no method for back verbatim', function (string $driver, string $type, string $expected) {
    $connection = useDriver($driver);

    DB::connection($connection)->statement("create table things (c {$type} not null)");

    expect(tableLines(generatedSchema($connection), 'things'))->toBe(["\$table->rawColumn('c', '{$expected}');"]);
})->with([
    'mysql: bit' => ['mysql', 'bit(4)', 'bit(4)'],
    'mysql: mediumblob' => ['mysql', 'mediumblob', 'mediumblob'],
    'mariadb: inet6' => ['mariadb', 'inet6', 'inet6'],
    'pgsql: interval' => ['pgsql', 'interval', 'interval'],
    'pgsql: unsized varchar' => ['pgsql', 'varchar', 'character varying'],
    'pgsql: unsized numeric' => ['pgsql', 'numeric', 'numeric'],
    'pgsql: array' => ['pgsql', 'integer[]', 'integer[]'],
    'sqlite: made-up type' => ['sqlite', 'geometry', 'geometry'],
]);
