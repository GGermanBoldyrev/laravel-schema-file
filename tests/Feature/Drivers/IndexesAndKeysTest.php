<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lines of the "things" table after the given definition, on the given driver.
 *
 * @return array{list<string>, list<string>} the table's own lines, and its foreign keys
 */
function thingsOn(string $driver, Closure $define, bool $prefixed = false): array
{
    $connection = useDriver($driver, $prefixed);
    $schema = Schema::connection($connection);

    $schema->create('teams', fn (Blueprint $table) => $table->id());
    $schema->create('things', function (Blueprint $table) use ($define) {
        $define($table);
    });

    $file = generatedSchema($connection);

    return [tableLines($file, 'things'), tableLines($file, 'things', 'table')];
}

describe('indexes', function () {
    it('chains an index Laravel named itself onto its column', function (string $driver) {
        [$lines] = thingsOn($driver, function (Blueprint $table) {
            $table->string('email')->unique();
            $table->integer('age')->index();
        });

        expect($lines)->toBe(["\$table->string('email')->unique();", "\$table->integer('age')->index();"]);
    })->with(ALL_DRIVERS);

    it('writes an index with a name of its own on a separate line', function (string $driver) {
        [$lines] = thingsOn($driver, function (Blueprint $table) {
            $table->string('email')->unique('uniq_email');
            $table->integer('age')->index('idx_age');
        });

        expect($lines)->toBe([
            "\$table->string('email');",
            "\$table->integer('age');",
            '',
            "\$table->unique('email', 'uniq_email');",
            "\$table->index('age', 'idx_age');",
        ]);
    })->with(ALL_DRIVERS);

    it('writes a composite index with its columns in index order', function (string $driver) {
        [$lines] = thingsOn($driver, function (Blueprint $table) {
            $table->integer('a');
            $table->integer('b');
            $table->unique(['b', 'a']);
            $table->index(['a', 'b'], 'lookup');
        });

        expect(array_slice($lines, 3))->toBe(["\$table->unique(['b', 'a']);", "\$table->index(['a', 'b'], 'lookup');"]);
    })->with(ALL_DRIVERS);

    it('writes a composite primary key', function (string $driver) {
        [$lines] = thingsOn($driver, function (Blueprint $table) {
            $table->integer('role_id');
            $table->integer('user_id');
            $table->primary(['role_id', 'user_id']);
        });

        expect($lines)->toBe([
            "\$table->integer('role_id');",
            "\$table->integer('user_id');",
            '',
            "\$table->primary(['role_id', 'user_id']);",
        ]);
    })->with(ALL_DRIVERS);

    it('does not repeat the primary key of an auto-incrementing column', function (string $driver) {
        [$lines] = thingsOn($driver, fn (Blueprint $table) => $table->id());

        expect($lines)->toBe(['$table->id();']);
    })->with(ALL_DRIVERS);

    it('writes a full-text index where the database has one over columns', function (string $driver) {
        [$lines] = thingsOn($driver, fn (Blueprint $table) => $table->text('body')->fullText());

        expect($lines)->toBe(["\$table->text('body')->fullText();"]);
    })->with(['mysql', 'mariadb']);

    it('leaves out an index over an expression, which has no columns to name', function () {
        // On PostgreSQL a full-text index is built over to_tsvector(...), not over the column itself.
        [$lines] = thingsOn('pgsql', fn (Blueprint $table) => $table->text('body')->fullText());

        expect($lines)->toBe(["\$table->text('body');"]);
    });

    it('recognises the names Laravel gives on a prefixed connection', function (string $driver) {
        [$lines, $foreignKeys] = thingsOn($driver, function (Blueprint $table) {
            $table->string('email')->unique();
            $table->string('name')->index('custom_name');
            $table->foreignId('team_id')->constrained();
            $table->index(['email', 'name']);
        }, prefixed: true);

        expect(array_slice($lines, 0, 2))->toBe(["\$table->string('email')->unique();", "\$table->string('name');"])
            ->and(array_slice($lines, 3))->toBe(['', "\$table->index(['email', 'name']);", "\$table->index('name', 'custom_name');"])
            ->and($foreignKeys)->toBe(["\$table->foreign('team_id')->references('id')->on('teams');"]);
    })->with(ALL_DRIVERS);
});

describe('foreign keys', function () {
    it('writes a foreign key without an action, and no index of its own', function (string $driver) {
        [$lines, $foreignKeys] = thingsOn($driver, fn (Blueprint $table) => $table->foreignId('team_id')->constrained());

        // MySQL and MariaDB create an index for the key by themselves; it is not one somebody asked for.
        expect($lines)->toHaveCount(1)
            ->and($foreignKeys)->toBe(["\$table->foreign('team_id')->references('id')->on('teams');"]);
    })->with(ALL_DRIVERS);

    it('writes the actions of a foreign key', function (string $driver, string $action) {
        [, $foreignKeys] = thingsOn($driver, function (Blueprint $table) use ($action) {
            $table->foreignId('team_id')->nullable()->constrained()->onUpdate($action)->onDelete($action);
        });

        expect($foreignKeys)->toBe([
            "\$table->foreign('team_id')->references('id')->on('teams')->onUpdate('{$action}')->onDelete('{$action}');",
        ]);
    })->with(ALL_DRIVERS)->with(['cascade', 'set null']);

    it('writes "restrict" where the database tells it from no action at all', function (string $driver) {
        [, $foreignKeys] = thingsOn($driver, fn (Blueprint $table) => $table->foreignId('team_id')->constrained()->restrictOnDelete());

        expect($foreignKeys)->toBe(["\$table->foreign('team_id')->references('id')->on('teams')->onDelete('restrict');"]);
    })->with(['sqlite', 'mysql', 'pgsql']);

    it('cannot tell "restrict" from no action on MariaDB, where they are the same thing', function () {
        [, $foreignKeys] = thingsOn('mariadb', fn (Blueprint $table) => $table->foreignId('team_id')->constrained()->restrictOnDelete());

        expect($foreignKeys)->toBe(["\$table->foreign('team_id')->references('id')->on('teams');"]);
    });

    it('writes a foreign key with a name of its own', function (string $driver) {
        [, $foreignKeys] = thingsOn($driver, function (Blueprint $table) {
            $table->unsignedBigInteger('team_id');
            $table->foreign('team_id', 'fk_team')->references('id')->on('teams');
        });

        expect($foreignKeys)->toBe(["\$table->foreign('team_id', 'fk_team')->references('id')->on('teams');"]);
    })->with(['mysql', 'mariadb', 'pgsql']);

    it('keeps an index somebody put on a foreign key column', function (string $driver) {
        [$lines] = thingsOn($driver, function (Blueprint $table) {
            $table->foreignId('team_id')->constrained();
            $table->index('team_id', 'team_lookup');
        });

        expect(array_slice($lines, 1))->toBe(['', "\$table->index('team_id', 'team_lookup');"]);
    })->with(ALL_DRIVERS);

    it('writes a self-referencing and a composite foreign key', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);

        $schema->create('tenants', function (Blueprint $table) {
            $table->integer('region');
            $table->integer('number');
            $table->primary(['region', 'number']);
        });
        $schema->create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->integer('tenant_region');
            $table->integer('tenant_number');
            $table->foreign(['tenant_region', 'tenant_number'])->references(['region', 'number'])->on('tenants');
        });

        expect(tableLines(generatedSchema($connection), 'categories', 'table'))->toBe([
            "\$table->foreign('parent_id')->references('id')->on('categories')->onDelete('set null');",
            "\$table->foreign(['tenant_region', 'tenant_number'])->references(['region', 'number'])->on('tenants');",
        ]);
    })->with(ALL_DRIVERS);
});

it('reads only the tables of its own database', function (string $driver) {
    $connection = useDriver($driver);

    Schema::connection($connection)->create('only_here', fn (Blueprint $table) => $table->id());

    preg_match_all("/Schema::create\('(\w+)'/", generatedSchema($connection), $matches);

    expect($matches[1])->toBe(['only_here']);
})->with(['mysql', 'mariadb', 'pgsql']);

it('leaves out the migrations table and excluded tables', function (string $driver) {
    $connection = useDriver($driver);
    $schema = Schema::connection($connection);

    foreach (['migrations', 'users', 'telescope_entries', 'telescope_tags'] as $name) {
        $schema->create($name, fn (Blueprint $table) => $table->id());
    }
    config(['schema-file.except' => ['telescope_*']]);

    preg_match_all("/Schema::create\('(\w+)'/", generatedSchema($connection), $matches);

    expect($matches[1])->toBe(['users']);
})->with(ALL_DRIVERS);
