<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('users', fn (Blueprint $table) => $table->id());
});

it('is registered under its name and its alias', function () {
    $commands = Artisan::all();

    expect($commands)->toHaveKey('schema:generate')
        ->and($commands['schema:generate']->getAliases())->toBe(['migrate:schema'])
        ->and($commands['schema:generate']->getDescription())->toBe('Write the current database schema to a single PHP file');
});

it('writes the schema file', function () {
    $this->artisan('schema:generate')
        ->expectsOutputToContain('Schema file written to')
        ->assertExitCode(0);

    expect(file_get_contents($this->schemaPath()))->toContain("Schema::create('users'");
});

it('runs under its alias', function () {
    $this->artisan('migrate:schema')->assertExitCode(0);

    expect($this->schemaPath())->toBeFile();
});

it('says so when the file is already up to date', function () {
    $this->artisan('schema:generate');

    $this->artisan('schema:generate')
        ->expectsOutputToContain('is already up to date')
        ->assertExitCode(0);
});

it('works when automatic generation is disabled', function () {
    config(['schema-file.enabled' => false]);

    $this->artisan('schema:generate')->assertExitCode(0);

    expect($this->schemaPath())->toBeFile();
});

describe('--path', function () {
    it('writes to the given path instead of the configured one', function () {
        $path = $this->workspace.'/elsewhere/custom.php';

        $this->artisan('schema:generate', ['--path' => $path])
            ->expectsOutputToContain('custom.php')
            ->assertExitCode(0);

        expect($path)->toBeFile()
            ->and($this->schemaPath())->not->toBeFile();
    });

    it('falls back to the configured path when given empty', function () {
        $this->artisan('schema:generate', ['--path' => ''])->assertExitCode(0);

        expect($this->schemaPath())->toBeFile();
    });
});

describe('--database', function () {
    beforeEach(function () {
        Schema::connection('secondary')->create('reports', fn (Blueprint $table) => $table->id());
    });

    it('reads the given connection', function () {
        $this->artisan('schema:generate', ['--database' => 'secondary'])->assertExitCode(0);

        expect(file_get_contents($this->schemaPath()))
            ->toContain("Schema::create('reports'")
            ->not->toContain("Schema::create('users'");
    });

    it('takes precedence over the configured connection', function () {
        config(['schema-file.connection' => 'secondary']);

        $this->artisan('schema:generate', ['--database' => 'testing'])->assertExitCode(0);

        expect(file_get_contents($this->schemaPath()))->toContain("Schema::create('users'");
    });

    it('uses the configured connection when not given', function () {
        config(['schema-file.connection' => 'secondary']);

        $this->artisan('schema:generate')->assertExitCode(0);

        expect(file_get_contents($this->schemaPath()))->toContain("Schema::create('reports'");
    });

    it('fails for a connection that does not exist', function () {
        $this->artisan('schema:generate', ['--database' => 'nope']);
    })->throws(InvalidArgumentException::class, 'Database connection [nope] not configured.');

    it('combines with --path', function () {
        $path = $this->workspace.'/secondary-schema.php';

        $this->artisan('schema:generate', ['--database' => 'secondary', '--path' => $path])->assertExitCode(0);

        expect(file_get_contents($path))->toContain("Schema::create('reports'")
            ->and($this->schemaPath())->not->toBeFile();
    });
});

describe('--check', function () {
    it('fails and writes nothing when there is no file', function () {
        $this->artisan('schema:generate', ['--check' => true])
            ->expectsOutputToContain('is out of date. Run [php artisan schema:generate] to update it.')
            ->assertExitCode(1);

        expect($this->schemaPath())->not->toBeFile();
    });

    it('passes when the file is up to date', function () {
        $this->artisan('schema:generate');

        $this->artisan('schema:generate', ['--check' => true])
            ->expectsOutputToContain('is up to date')
            ->assertExitCode(0);
    });

    it('fails and leaves the file alone when the database has changed', function () {
        $this->artisan('schema:generate');
        $before = file_get_contents($this->schemaPath());

        Schema::table('users', fn (Blueprint $table) => $table->string('name')->nullable());

        $this->artisan('schema:generate', ['--check' => true])->assertExitCode(1);

        expect(file_get_contents($this->schemaPath()))->toBe($before);
    });

    it('fails and leaves the file alone when it was edited by hand', function () {
        $this->artisan('schema:generate');
        file_put_contents($this->schemaPath(), $edited = file_get_contents($this->schemaPath()).'// edited');

        $this->artisan('schema:generate', ['--check' => true])->assertExitCode(1);

        expect(file_get_contents($this->schemaPath()))->toBe($edited);
    });

    it('checks the file given with --path', function () {
        $path = $this->workspace.'/other.php';
        $this->artisan('schema:generate', ['--path' => $path]);

        $this->artisan('schema:generate', ['--check' => true, '--path' => $path])->assertExitCode(0);
        $this->artisan('schema:generate', ['--check' => true])->assertExitCode(1);
    });

    it('checks against the connection given with --database', function () {
        Schema::connection('secondary')->create('reports', fn (Blueprint $table) => $table->id());
        $this->artisan('schema:generate');

        $this->artisan('schema:generate', ['--check' => true, '--database' => 'secondary'])->assertExitCode(1);
        $this->artisan('schema:generate', ['--check' => true, '--database' => 'testing'])->assertExitCode(0);
    });
});

describe('unsupported database driver', function () {
    beforeEach(function () {
        app()->extend(
            GGermanBoldyrev\SchemaFile\Mapper\ColumnMapperRegistry::class,
            fn () => new GGermanBoldyrev\SchemaFile\Mapper\ColumnMapperRegistry,
        );
    });

    it('prints an error and fails without writing anything', function () {
        $this->artisan('schema:generate')
            ->expectsOutputToContain('The schema file cannot be generated for the [sqlite] database driver.')
            ->assertExitCode(1);

        expect($this->schemaPath())->not->toBeFile();
    });

    it('prints the same error and fails with --check', function () {
        $this->artisan('schema:generate', ['--check' => true])
            ->expectsOutputToContain('The schema file cannot be generated for the [sqlite] database driver.')
            ->doesntExpectOutputToContain('out of date')
            ->assertExitCode(1);
    });
});

it('fails on an invalid setting instead of guessing', function () {
    config(['schema-file.path' => null]);

    $this->artisan('schema:generate');
})->throws(InvalidArgumentException::class, 'schema-file.path');
