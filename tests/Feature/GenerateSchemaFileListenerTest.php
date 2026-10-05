<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Reader\Columns\ColumnMapperRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * @return array<string, mixed>
 */
function fixtureMigrations(array $options = []): array
{
    return ['--path' => test()->migrationsPath(), '--realpath' => true, ...$options];
}

beforeEach(function () {
    config(['schema-file.enabled' => true]);
});

it('writes the schema file after migrate and says so', function () {
    $this->artisan('migrate', fixtureMigrations())
        ->expectsOutputToContain('Schema file written to')
        ->assertExitCode(0);

    expect(file_get_contents($this->schemaPath()))
        ->toContain("Schema::create('teams'")
        ->toContain("Schema::create('users'")
        ->toContain("\$table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');")
        ->not->toContain('migrations');
});

it('rewrites the schema file after a rollback', function () {
    $this->artisan('migrate', fixtureMigrations());

    $this->artisan('migrate:rollback', fixtureMigrations(['--step' => 1]))
        ->expectsOutputToContain('Schema file written to')
        ->assertExitCode(0);

    expect(file_get_contents($this->schemaPath()))
        ->toContain("Schema::create('teams'")
        ->not->toContain("Schema::create('users'");
});

it('writes an empty schema after everything is rolled back', function () {
    $this->artisan('migrate', fixtureMigrations());
    $this->artisan('migrate:reset', fixtureMigrations())->assertExitCode(0);

    expect(file_get_contents($this->schemaPath()))->not->toContain('Schema::create');
});

it('writes the schema file after migrate:fresh', function () {
    // migrate:fresh only wipes a database that has been migrated before.
    $this->artisan('migrate', fixtureMigrations());
    Schema::create('leftover', fn (Blueprint $table) => $table->id());
    unlink($this->schemaPath());

    $this->artisan('migrate:fresh', fixtureMigrations())
        ->expectsOutputToContain('Schema file written to')
        ->assertExitCode(0);

    expect(file_get_contents($this->schemaPath()))
        ->toContain("Schema::create('users'")
        ->not->toContain('leftover');
});

it('does nothing when there was nothing to migrate', function () {
    $this->artisan('migrate', fixtureMigrations());
    unlink($this->schemaPath());

    $this->artisan('migrate', fixtureMigrations())
        ->doesntExpectOutputToContain('Schema file')
        ->assertExitCode(0);

    expect($this->schemaPath())->not->toBeFile();
});

it('says nothing when migrations left the schema as it was', function () {
    $this->artisan('migrate', fixtureMigrations());
    $this->artisan('migrate:rollback', fixtureMigrations(['--step' => 1]));
    $this->artisan('schema:generate', ['--path' => $ahead = $this->workspace.'/ahead.php']);

    // Put the file ahead of the database: exactly what the next migrate will produce.
    $this->artisan('migrate', fixtureMigrations());
    copy($this->schemaPath(), $ahead);
    $this->artisan('migrate:rollback', fixtureMigrations(['--step' => 1]));
    copy($ahead, $this->schemaPath());

    $this->artisan('migrate', fixtureMigrations())
        ->doesntExpectOutputToContain('Schema file')
        ->assertExitCode(0);
});

it('ignores a pretended migration', function () {
    $this->artisan('migrate', fixtureMigrations(['--pretend' => true]))->assertExitCode(0);

    expect($this->schemaPath())->not->toBeFile();
});

describe('enabled', function () {
    it('does nothing when disabled', function () {
        config(['schema-file.enabled' => false]);

        $this->artisan('migrate', fixtureMigrations())
            ->doesntExpectOutputToContain('Schema file')
            ->assertExitCode(0);

        expect($this->schemaPath())->not->toBeFile();
    });

    it('is off outside the local environment unless configured', function () {
        config(['schema-file.enabled' => null]);

        $this->artisan('migrate', fixtureMigrations())->assertExitCode(0);

        expect(app()->environment())->toBe('testing')
            ->and($this->schemaPath())->not->toBeFile();
    });

    it('is on in the local environment unless configured', function () {
        config(['schema-file.enabled' => null]);
        app()->detectEnvironment(fn () => 'local');

        $this->artisan('migrate', fixtureMigrations())->assertExitCode(0);

        expect($this->schemaPath())->toBeFile();
    });

    it('can be turned off in the local environment', function () {
        config(['schema-file.enabled' => false]);
        app()->detectEnvironment(fn () => 'local');

        $this->artisan('migrate', fixtureMigrations())->assertExitCode(0);

        expect($this->schemaPath())->not->toBeFile();
    });
});

describe('connections', function () {
    it('ignores migrations run on another connection', function () {
        $this->artisan('migrate', fixtureMigrations(['--database' => 'secondary']))
            ->doesntExpectOutputToContain('Schema file')
            ->assertExitCode(0);

        expect($this->schemaPath())->not->toBeFile()
            ->and(Schema::connection('secondary')->hasTable('users'))->toBeTrue();
    });

    it('writes when the default connection is named explicitly', function () {
        $this->artisan('migrate', fixtureMigrations(['--database' => 'testing']))->assertExitCode(0);

        expect($this->schemaPath())->toBeFile();
    });

    it('writes when the configured connection is the one migrated', function () {
        config(['schema-file.connection' => 'secondary']);

        $this->artisan('migrate', fixtureMigrations(['--database' => 'secondary']))->assertExitCode(0);

        expect(file_get_contents($this->schemaPath()))->toContain("Schema::create('users'");
    });

    it('ignores the default connection when the file is configured for another', function () {
        config(['schema-file.connection' => 'secondary']);

        $this->artisan('migrate', fixtureMigrations())->assertExitCode(0);

        expect($this->schemaPath())->not->toBeFile();
    });

    it('leaves the default connection as it was', function () {
        $this->artisan('migrate', fixtureMigrations(['--database' => 'secondary']));

        expect(config('database.default'))->toBe('testing');
    });
});

describe('failure', function () {
    beforeEach(function () {
        // A file where a directory is needed, so the schema file cannot be written.
        file_put_contents($this->workspace.'/blocker', '');
        config(['schema-file.path' => $this->workspace.'/blocker/schema.php']);
    });

    it('lets the migration succeed and prints a warning', function () {
        $this->artisan('migrate', fixtureMigrations())
            ->expectsOutputToContain('The schema file was not written.')
            ->assertExitCode(0);

        expect(Schema::hasTable('users'))->toBeTrue();
    });

    it('logs the failure with its exception', function () {
        Log::spy();

        $this->artisan('migrate', fixtureMigrations());

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => $message === 'The schema file could not be written after running migrations.'
                && $context['exception'] instanceof Throwable,
        );
    });

    it('lets the migration succeed and prints a warning when a setting is invalid', function (string $key, mixed $value) {
        config(["schema-file.{$key}" => $value]);

        $this->artisan('migrate', fixtureMigrations())
            ->expectsOutputToContain("The schema file was not written. Configuration value for key [schema-file.{$key}]")
            ->assertExitCode(0);

        expect(Schema::hasTable('users'))->toBeTrue();
    })->with([
        ['path', null],
        ['enabled', 'yes'],
        ['except', 'jobs'],
    ]);

    it('warns about a database driver that is not supported', function () {
        config(['schema-file.path' => $this->schemaPath()]);
        app()->extend(
            ColumnMapperRegistry::class,
            fn () => new ColumnMapperRegistry,
        );

        $this->artisan('migrate', fixtureMigrations())
            ->expectsOutputToContain('The schema file cannot be generated for the [sqlite] database driver')
            ->assertExitCode(0);

        expect($this->schemaPath())->not->toBeFile();
    });
});
