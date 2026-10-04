<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Exceptions\UnsupportedDriverException;
use GGermanBoldyrev\SchemaFile\Reader\SchemaReader;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\Schema\ForeignKey;
use GGermanBoldyrev\SchemaFile\Schema\Index;
use GGermanBoldyrev\SchemaFile\Schema\IndexType;
use GGermanBoldyrev\SchemaFile\Schema\Table;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * @param  list<string>  $except
 * @return array<string, Table> keyed by table name
 */
function readSchema(array $except = [], ?string $connection = null): array
{
    $tables = app(SchemaReader::class)->read(DB::connection($connection), $except);

    return array_combine(array_map(fn (Table $table) => $table->name, $tables), $tables);
}

it('returns nothing for an empty database', function () {
    expect(readSchema())->toBe([]);
});

it('reads a table with its columns in database order', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('zeta');
        $table->text('alpha')->nullable();
        $table->boolean('active')->default(true);
    });

    expect(readSchema()['users']->columns)->toEqual([
        new Column('id', 'id'),
        new Column('zeta', 'string'),
        new Column('alpha', 'text', nullable: true),
        new Column('active', 'boolean', default: true),
    ]);
});

it('tells an auto-incrementing key from a plain integer primary key', function () {
    Schema::create('incrementing', fn (Blueprint $table) => $table->id());
    Schema::create('named', fn (Blueprint $table) => $table->increments('legacy_id'));
    Schema::create('plain', fn (Blueprint $table) => $table->integer('id')->primary());

    $tables = readSchema();

    expect($tables['incrementing']->columns)->toEqual([new Column('id', 'id')])
        ->and($tables['named']->columns)->toEqual([new Column('legacy_id', 'id')])
        ->and($tables['plain']->columns)->toEqual([new Column('id', 'integer')])
        ->and($tables['plain']->indexes[0]->type)->toBe(IndexType::Primary);
});

it('reads a column added later after the original ones', function () {
    Schema::create('users', fn (Blueprint $table) => $table->id());
    Schema::table('users', fn (Blueprint $table) => $table->string('nickname')->nullable());

    expect(array_map(fn (Column $column) => $column->name, readSchema()['users']->columns))->toBe(['id', 'nickname']);
});

it('no longer reads a dropped table or column', function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('legacy');
    });
    Schema::create('temp', fn (Blueprint $table) => $table->id());

    Schema::drop('temp');
    Schema::table('users', fn (Blueprint $table) => $table->dropColumn('legacy'));

    expect(readSchema())->toHaveKeys(['users'])->toHaveCount(1)
        ->and(readSchema()['users']->columns)->toEqual([new Column('id', 'id')]);
});

it('does not read views', function () {
    Schema::create('users', fn (Blueprint $table) => $table->id());
    DB::statement('create view active_users as select * from users');

    expect(array_keys(readSchema()))->toBe(['users']);
});

describe('except', function () {
    beforeEach(function () {
        foreach (['users', 'jobs', 'job_batches', 'failed_jobs', 'cache'] as $name) {
            Schema::create($name, fn (Blueprint $table) => $table->id());
        }
    });

    it('leaves tables out by exact name and by pattern', function (array $except, array $remaining) {
        expect(array_keys(readSchema($except)))->toEqualCanonicalizing($remaining);
    })->with([
        'nothing excluded' => [[], ['users', 'jobs', 'job_batches', 'failed_jobs', 'cache']],
        'exact name' => [['jobs'], ['users', 'job_batches', 'failed_jobs', 'cache']],
        'prefix pattern' => [['job*'], ['users', 'failed_jobs', 'cache']],
        'suffix pattern' => [['*jobs'], ['users', 'job_batches', 'cache']],
        'middle pattern' => [['*job*'], ['users', 'cache']],
        'several entries' => [['cache', '*_jobs', 'users'], ['jobs', 'job_batches']],
        'everything' => [['*'], []],
        'a part of a name is not a match' => [['job', 'user'], ['users', 'jobs', 'job_batches', 'failed_jobs', 'cache']],
        'case matters' => [['USERS'], ['users', 'jobs', 'job_batches', 'failed_jobs', 'cache']],
        'unknown table' => [['nope'], ['users', 'jobs', 'job_batches', 'failed_jobs', 'cache']],
    ]);
});

describe('indexes', function () {
    it('reads primary, unique and plain indexes', function () {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->integer('age')->index();
        });

        expect(readSchema()['users']->indexes)->toEqualCanonicalizing([
            new Index(IndexType::Primary, ['id'], 'primary'),
            new Index(IndexType::Unique, ['email'], 'users_email_unique'),
            new Index(IndexType::Index, ['age'], 'users_age_index'),
        ]);
    });

    it('reads composite indexes with their columns in index order', function () {
        Schema::create('posts', function (Blueprint $table) {
            $table->integer('b');
            $table->integer('a');
            $table->unique(['b', 'a']);
            $table->index(['a', 'b'], 'lookup');
        });

        expect(readSchema()['posts']->indexes)->toEqualCanonicalizing([
            new Index(IndexType::Unique, ['b', 'a'], 'posts_b_a_unique'),
            new Index(IndexType::Index, ['a', 'b'], 'lookup'),
        ]);
    });

    it('reads a primary key on a string column', function () {
        Schema::create('cache', fn (Blueprint $table) => $table->string('key')->primary());

        $indexes = readSchema()['cache']->indexes;

        expect($indexes)->toHaveCount(1)
            ->and($indexes[0]->type)->toBe(IndexType::Primary)
            ->and($indexes[0]->columns)->toBe(['key']);
    });

    it('reads a composite primary key', function () {
        Schema::create('role_user', function (Blueprint $table) {
            $table->integer('role_id');
            $table->integer('user_id');
            $table->primary(['role_id', 'user_id']);
        });

        $indexes = readSchema()['role_user']->indexes;

        expect($indexes)->toHaveCount(1)
            ->and($indexes[0]->type)->toBe(IndexType::Primary)
            ->and($indexes[0]->columns)->toBe(['role_id', 'user_id']);
    });

    it('returns a plain list whatever keys the database driver used', function () {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->integer('expiration')->index();
        });

        expect(array_is_list(readSchema()['cache']->indexes))->toBeTrue();
    });

    it('reads no indexes from a table that has none', function () {
        Schema::create('logs', fn (Blueprint $table) => $table->text('line'));

        expect(readSchema()['logs']->indexes)->toBe([]);
    });
});

describe('foreign keys', function () {
    beforeEach(function () {
        Schema::create('teams', fn (Blueprint $table) => $table->id());
    });

    it('reads a foreign key without an action as having none', function () {
        Schema::create('users', fn (Blueprint $table) => $table->foreignId('team_id')->constrained());

        expect(readSchema()['users']->foreignKeys)->toEqual([
            new ForeignKey(['team_id'], 'teams', ['id']),
        ]);
    });

    it('reads the actions of a foreign key', function (string $action) {
        Schema::create('users', function (Blueprint $table) use ($action) {
            $table->foreignId('team_id')->nullable()->constrained()->onUpdate($action)->onDelete($action);
        });

        expect(readSchema()['users']->foreignKeys)->toEqual([
            new ForeignKey(['team_id'], 'teams', ['id'], onUpdate: $action, onDelete: $action),
        ]);
    })->with(['cascade', 'set null', 'restrict', 'set default']);

    it('reads an explicit "no action" as having none', function () {
        Schema::create('users', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->onUpdate('no action')->onDelete('no action');
        });

        expect(readSchema()['users']->foreignKeys[0])
            ->onUpdate->toBeNull()
            ->onDelete->toBeNull();
    });

    it('reads different actions for update and delete', function () {
        Schema::create('users', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
        });

        expect(readSchema()['users']->foreignKeys[0])
            ->onUpdate->toBe('cascade')
            ->onDelete->toBe('restrict');
    });

    it('reads a composite foreign key', function () {
        Schema::create('tenants', function (Blueprint $table) {
            $table->integer('region');
            $table->integer('number');
            $table->primary(['region', 'number']);
        });
        Schema::create('users', function (Blueprint $table) {
            $table->integer('tenant_region');
            $table->integer('tenant_number');
            $table->foreign(['tenant_region', 'tenant_number'])->references(['region', 'number'])->on('tenants');
        });

        expect(readSchema()['users']->foreignKeys)->toEqual([
            new ForeignKey(['tenant_region', 'tenant_number'], 'tenants', ['region', 'number']),
        ]);
    });

    it('reads a self-referencing foreign key', function () {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
        });

        expect(readSchema()['categories']->foreignKeys)->toEqual([
            new ForeignKey(['parent_id'], 'categories', ['id'], onDelete: 'set null'),
        ]);
    });

    it('reads several foreign keys of one table', function () {
        Schema::create('users', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained();
            $table->foreignId('backup_team_id')->nullable()->constrained('teams')->cascadeOnDelete();
        });

        expect(readSchema()['users']->foreignKeys)->toEqualCanonicalizing([
            new ForeignKey(['team_id'], 'teams', ['id']),
            new ForeignKey(['backup_team_id'], 'teams', ['id'], onDelete: 'cascade'),
        ]);
    });

    it('keeps a foreign key whose target table is excluded, as the file is there to be read', function (array $except) {
        Schema::create('users', fn (Blueprint $table) => $table->foreignId('team_id')->constrained());

        $tables = readSchema($except);

        expect(array_keys($tables))->toBe(['users'])
            ->and($tables['users']->foreignKeys)->toEqual([new ForeignKey(['team_id'], 'teams', ['id'])]);
    })->with([
        'by name' => [['teams']],
        'by pattern' => [['team*']],
    ]);
});

describe('table prefix', function () {
    beforeEach(function () {
        $schema = Schema::connection('prefixed');

        $schema->create('teams', fn (Blueprint $table) => $table->id());
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->foreignId('team_id')->constrained();
        });

        DB::connection('prefixed')->statement('create table "outsider" ("id" integer)');
    });

    it('names tables the way migrations do, without the prefix', function () {
        expect(array_keys(readSchema(connection: 'prefixed')))->toEqualCanonicalizing(['teams', 'users']);
    });

    it('skips tables that do not carry the prefix', function () {
        expect(readSchema(connection: 'prefixed'))->not->toHaveKey('outsider');
    });

    it('strips the prefix from the table a foreign key points to', function () {
        expect(readSchema(connection: 'prefixed')['users']->foreignKeys[0]->foreignTable)->toBe('teams');
    });

    it('matches except against the name without the prefix', function () {
        expect(array_keys(readSchema(['teams'], 'prefixed')))->toBe(['users'])
            ->and(array_keys(readSchema(['app_teams'], 'prefixed')))->toEqualCanonicalizing(['teams', 'users']);
    });

    it('recognises an id column of a prefixed table', function () {
        expect(readSchema(connection: 'prefixed')['teams']->columns)->toEqual([new Column('id', 'id')]);
    });

    it('reads the columns of a prefixed table', function () {
        expect(array_map(fn (Column $column) => $column->name, readSchema(connection: 'prefixed')['users']->columns))
            ->toBe(['id', 'email', 'team_id']);
    });
});

describe('generated columns', function () {
    beforeEach(function () {
        Schema::create('orders', function (Blueprint $table) {
            $table->integer('price');
            $table->integer('doubled')->virtualAs('price * 2');
            $table->integer('tripled')->storedAs('price * 3');
        });
    });

    it('reads the expression of a virtual and of a stored column', function () {
        [, $doubled, $tripled] = readSchema()['orders']->columns;

        expect($doubled)->virtualAs->toBe('price * 2')->storedAs->toBeNull()
            ->and($tripled)->storedAs->toBe('price * 3')->virtualAs->toBeNull();
    });

    it('reads an ordinary column as not generated', function () {
        expect(readSchema()['orders']->columns[0])->virtualAs->toBeNull()->storedAs->toBeNull();
    });
});

it('reads each connection separately', function () {
    Schema::create('on_default', fn (Blueprint $table) => $table->id());
    Schema::connection('secondary')->create('on_secondary', fn (Blueprint $table) => $table->id());

    expect(array_keys(readSchema()))->toBe(['on_default'])
        ->and(array_keys(readSchema(connection: 'secondary')))->toBe(['on_secondary']);
});

it('refuses a database driver it has no mapper for, before running any query', function () {
    config(['database.connections.postgres' => ['driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'nowhere']]);

    expect(fn () => readSchema(connection: 'postgres'))->toThrow(
        UnsupportedDriverException::class,
        'The schema file cannot be generated for the [pgsql] database driver. Supported drivers: sqlite.',
    );
});
