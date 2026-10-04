<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A schema does not stay as it was created: columns are added, renamed, changed
 * and dropped, tables are renamed, and migrations are rolled back. After each of
 * these the file has to describe the database as it is now.
 */

/**
 * @return list<string> every table named in the file
 */
function tablesIn(string $schema): array
{
    preg_match_all("/Schema::create\('(\w+)'/", $schema, $matches);

    return $matches[1];
}

describe('following migrations forward and back', function () {
    it('returns, with every rollback, to the file it wrote before that migration', function (string $driver) {
        $connection = useDriver($driver);
        $directory = dirname($this->migrationsPath()).'/evolution';
        $migrations = glob($directory.'/*.php');
        sort($migrations);

        config(['schema-file.enabled' => true, 'schema-file.connection' => $connection]);

        // What the file says before anything has been migrated.
        $this->artisan('schema:generate');
        $snapshots = [file_get_contents($this->schemaPath())];

        foreach ($migrations as $migration) {
            $this->artisan('migrate', ['--database' => $connection, '--path' => $migration, '--realpath' => true])
                ->assertExitCode(0);

            $snapshots[] = file_get_contents($this->schemaPath());
        }

        // Every migration changed the schema, so every one changed the file.
        expect(array_unique($snapshots))->toHaveCount(count($migrations) + 1);

        for ($applied = count($migrations); $applied >= 1; $applied--) {
            $this->artisan('migrate:rollback', ['--database' => $connection, '--path' => $directory, '--realpath' => true, '--step' => 1])
                ->assertExitCode(0);

            expect(file_get_contents($this->schemaPath()))
                ->toBe($snapshots[$applied - 1], 'after rolling back '.basename($migrations[$applied - 1]));
        }

        expect(tablesIn(file_get_contents($this->schemaPath())))->toBe([]);
    })->with(ALL_DRIVERS);

    it('shows each step of the way', function (string $driver) {
        $connection = useDriver($driver);
        $directory = dirname($this->migrationsPath()).'/evolution';
        $migrations = glob($directory.'/*.php');
        sort($migrations);

        config(['schema-file.enabled' => true, 'schema-file.connection' => $connection]);

        $after = function (int $count) use ($connection, $migrations): string {
            foreach (array_slice($migrations, 0, $count) as $migration) {
                $this->artisan('migrate', ['--database' => $connection, '--path' => $migration, '--realpath' => true]);
            }

            return file_get_contents($this->schemaPath());
        };

        $file = $after(3);
        expect(tablesIn($file))->toBe(['teams', 'users'])
            ->and($file)->toContain("'full_name'")->not->toContain("string('name')\n")
            ->and(tableLines($file, 'users')[1])->toStartWith("\$table->string('full_name'");

        $file = $after(6);
        expect(tablesIn($file))->toBe(['members', 'teams'])
            ->and($file)->not->toContain("'users'")
            ->and(tableLines($file, 'members', 'table'))->toHaveCount(1);

        $file = $after(8);
        expect(tablesIn($file))->toBe(['members', 'posts', 'teams'])
            ->and(tableLines($file, 'posts', 'table'))->toBe([
                "\$table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');",
            ])
            ->and(implode("\n", tableLines($file, 'members')))->not->toContain('users_age_index');
    })->with(ALL_DRIVERS);

    it('rolls everything back in one go', function (string $driver) {
        $connection = useDriver($driver);
        $directory = dirname($this->migrationsPath()).'/evolution';

        config(['schema-file.enabled' => true, 'schema-file.connection' => $connection]);

        $this->artisan('migrate', ['--database' => $connection, '--path' => $directory, '--realpath' => true]);
        expect(tablesIn(file_get_contents($this->schemaPath())))->toBe(['members', 'posts', 'teams']);

        $this->artisan('migrate:rollback', ['--database' => $connection, '--path' => $directory, '--realpath' => true])
            ->assertExitCode(0);

        expect(tablesIn(file_get_contents($this->schemaPath())))->toBe([]);
    })->with(ALL_DRIVERS);
});

describe('tables', function () {
    it('shows a renamed table under its new name only', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });

        $schema->rename('users', 'members');

        $file = generatedSchema($connection);

        expect(tablesIn($file))->toBe(['members'])
            ->and(tableLines($file, 'members'))->toBe(['$table->id();', "\$table->string('email');"]);
    })->with(ALL_DRIVERS);

    it('points a foreign key at a renamed table', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', fn (Blueprint $table) => $table->id());
        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        });

        $schema->rename('users', 'members');

        expect(tableLines(generatedSchema($connection), 'posts', 'table'))->toBe([
            "\$table->foreign('user_id')->references('id')->on('members');",
        ]);
    })->with(ALL_DRIVERS);

    it('keeps the names indexes had before their table was renamed', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
        });

        $schema->rename('users', 'members');

        // The index is still called users_email_unique, which is no longer the name Laravel would give it.
        expect(tableLines(generatedSchema($connection), 'members'))->toBe([
            '$table->id();',
            "\$table->string('email');",
            '',
            "\$table->unique('email', 'users_email_unique');",
        ]);
    })->with(ALL_DRIVERS);

    it('no longer shows a dropped table', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', fn (Blueprint $table) => $table->id());
        $schema->create('temporary', fn (Blueprint $table) => $table->id());

        $schema->drop('temporary');
        $schema->dropIfExists('never_existed');

        expect(tablesIn(generatedSchema($connection)))->toBe(['users']);
    })->with(ALL_DRIVERS);

    it('shows a table that was dropped and created again as it is now', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', fn (Blueprint $table) => $table->string('old'));

        $schema->drop('users');
        $schema->create('users', fn (Blueprint $table) => $table->integer('new'));

        expect(tableLines(generatedSchema($connection), 'users'))->toBe(["\$table->integer('new');"]);
    })->with(ALL_DRIVERS);
});

describe('columns', function () {
    beforeEach(function () {
        $this->alter = function (string $driver, Closure $change): array {
            $connection = useDriver($driver);
            $schema = Schema::connection($connection);

            $schema->create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->integer('age')->default(18);
            });
            $schema->table('users', function (Blueprint $table) use ($change) {
                $change($table);
            });

            return tableLines(generatedSchema($connection), 'users');
        };
    });

    it('shows an added column after the ones that were there', function (string $driver) {
        $lines = ($this->alter)($driver, fn (Blueprint $table) => $table->string('nickname')->nullable());

        expect($lines)->toBe([
            '$table->id();',
            "\$table->string('name');",
            "\$table->string('email')->unique();",
            "\$table->integer('age')->default(18);",
            "\$table->string('nickname')->nullable();",
        ]);
    })->with(ALL_DRIVERS);

    it('shows a column added in the middle where the database put it', function (string $driver) {
        $lines = ($this->alter)($driver, fn (Blueprint $table) => $table->string('nickname')->nullable()->after('name'));

        expect($lines[2])->toBe("\$table->string('nickname')->nullable();");
    })->with(['mysql', 'mariadb']);

    it('shows a renamed column under its new name, in its old place', function (string $driver) {
        $lines = ($this->alter)($driver, fn (Blueprint $table) => $table->renameColumn('name', 'full_name'));

        expect($lines)->toBe([
            '$table->id();',
            "\$table->string('full_name');",
            "\$table->string('email')->unique();",
            "\$table->integer('age')->default(18);",
        ]);
    })->with(ALL_DRIVERS);

    it('keeps the index of a renamed column, under the name it already had', function (string $driver) {
        $lines = ($this->alter)($driver, fn (Blueprint $table) => $table->renameColumn('email', 'mail'));

        // The index is still called users_email_unique, though the column is now "mail".
        expect($lines)->toBe([
            '$table->id();',
            "\$table->string('name');",
            "\$table->string('mail');",
            "\$table->integer('age')->default(18);",
            '',
            "\$table->unique('mail', 'users_email_unique');",
        ]);
    })->with(ALL_DRIVERS);

    it('shows a column whose type was changed', function (string $driver) {
        $lines = ($this->alter)($driver, fn (Blueprint $table) => $table->text('name')->change());

        expect($lines[1])->toBe("\$table->text('name');");
    })->with(ALL_DRIVERS);

    it('shows a column that became nullable, and one that stopped being so', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', function (Blueprint $table) {
            $table->string('a');
            $table->string('b')->nullable();
        });

        $schema->table('users', function (Blueprint $table) {
            $table->string('a')->nullable()->change();
            $table->string('b')->nullable(false)->change();
        });

        expect(tableLines(generatedSchema($connection), 'users'))->toBe([
            "\$table->string('a')->nullable();",
            "\$table->string('b');",
        ]);
    })->with(ALL_DRIVERS);

    it('shows a default that was changed, and one that was removed', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', function (Blueprint $table) {
            $table->integer('a')->default(1);
            $table->integer('b')->default(1);
            $table->integer('c');
        });

        $schema->table('users', function (Blueprint $table) {
            $table->integer('a')->default(2)->change();
            $table->integer('b')->change();
            $table->integer('c')->default(3)->change();
        });

        expect(tableLines(generatedSchema($connection), 'users'))->toBe([
            "\$table->integer('a')->default(2);",
            "\$table->integer('b');",
            "\$table->integer('c')->default(3);",
        ]);
    })->with(ALL_DRIVERS);

    it('no longer shows a dropped column or the index that went with it', function (string $driver) {
        $lines = ($this->alter)($driver, fn (Blueprint $table) => $table->dropColumn('email'));

        expect($lines)->toBe(['$table->id();', "\$table->string('name');", "\$table->integer('age')->default(18);"]);
    })->with(['mysql', 'mariadb', 'pgsql']);

    it('shows a column that was dropped and added again at the end', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', function (Blueprint $table) {
            $table->string('a');
            $table->string('b');
        });

        $schema->table('users', fn (Blueprint $table) => $table->dropColumn('a'));
        $schema->table('users', fn (Blueprint $table) => $table->string('a')->nullable());

        expect(tableLines(generatedSchema($connection), 'users'))->toBe([
            "\$table->string('b');",
            "\$table->string('a')->nullable();",
        ]);
    })->with(ALL_DRIVERS);
});

describe('indexes and foreign keys', function () {
    it('shows an index that was added later, and no longer one that was dropped', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', function (Blueprint $table) {
            $table->string('email')->unique();
            $table->integer('age');
        });

        $schema->table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->index('age');
        });

        expect(tableLines(generatedSchema($connection), 'users'))->toBe([
            "\$table->string('email');",
            "\$table->integer('age')->index();",
        ]);
    })->with(ALL_DRIVERS);

    it('shows a renamed index under its new name', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', fn (Blueprint $table) => $table->integer('age')->index());

        $schema->table('users', fn (Blueprint $table) => $table->renameIndex('users_age_index', 'by_age'));

        expect(tableLines(generatedSchema($connection), 'users'))->toBe([
            "\$table->integer('age');",
            '',
            "\$table->index('age', 'by_age');",
        ]);
    })->with(ALL_DRIVERS);

    it('shows a primary key that was added later', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('users', fn (Blueprint $table) => $table->string('code', 20));

        $schema->table('users', fn (Blueprint $table) => $table->primary('code'));

        expect(tableLines(generatedSchema($connection), 'users')[0])->toEndWith('->primary();');
    })->with(['mysql', 'mariadb', 'pgsql']);

    it('shows a foreign key that was added later, and no longer one that was dropped', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('teams', fn (Blueprint $table) => $table->id());
        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained();
            $table->foreignId('backup_team_id')->nullable();
        });

        $schema->table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
            $table->foreign('backup_team_id')->references('id')->on('teams')->nullOnDelete();
        });

        expect(tableLines(generatedSchema($connection), 'users', 'table'))->toBe([
            "\$table->foreign('backup_team_id')->references('id')->on('teams')->onDelete('set null');",
        ]);
    })->with(['mysql', 'mariadb', 'pgsql']);

    it('shows a foreign key whose action was changed', function (string $driver) {
        $connection = useDriver($driver);
        $schema = Schema::connection($connection);
        $schema->create('teams', fn (Blueprint $table) => $table->id());
        $schema->create('users', fn (Blueprint $table) => $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete());

        $schema->table('users', function (Blueprint $table) {
            $table->dropForeign(['team_id']);
        });
        $schema->table('users', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });

        expect(tableLines(generatedSchema($connection), 'users', 'table'))->toBe([
            "\$table->foreign('team_id')->references('id')->on('teams')->onDelete('set null');",
        ]);
    })->with(['mysql', 'mariadb', 'pgsql']);
});
