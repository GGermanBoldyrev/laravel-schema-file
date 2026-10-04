<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\GenerationResult;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use GGermanBoldyrev\SchemaFile\SchemaFileGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->generator = app(SchemaFileGenerator::class);
    $this->config = app(SchemaFileConfig::class);

    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
    });
});

it('writes the schema of the database to the configured path', function () {
    expect($this->generator->generate($this->config))->toBe(GenerationResult::Written)
        ->and(file_get_contents($this->schemaPath()))
        ->toStartWith("<?php\n")
        ->toContain("Schema::create('users', function (Blueprint \$table) {\n    \$table->id();\n    \$table->string('email')->unique();\n});");
});

it('leaves an up-to-date file untouched', function () {
    $this->generator->generate($this->config);
    touch($this->schemaPath(), $then = time() - 3600);
    clearstatcache();

    expect($this->generator->generate($this->config))->toBe(GenerationResult::Unchanged)
        ->and(filemtime($this->schemaPath()))->toBe($then);
});

it('rewrites the file when the database has changed', function () {
    $this->generator->generate($this->config);

    Schema::table('users', fn (Blueprint $table) => $table->string('name')->nullable());

    expect($this->generator->generate($this->config))->toBe(GenerationResult::Written)
        ->and(file_get_contents($this->schemaPath()))->toContain("\$table->string('name')->nullable();");
});

it('rewrites a file that was edited by hand', function () {
    $this->generator->generate($this->config);
    $generated = file_get_contents($this->schemaPath());

    file_put_contents($this->schemaPath(), $generated."\n// edited");

    expect($this->generator->generate($this->config))->toBe(GenerationResult::Written)
        ->and(file_get_contents($this->schemaPath()))->toBe($generated);
});

it('creates the directories leading to the file', function () {
    $path = $this->workspace.'/deep/er/still/schema.php';

    $this->generator->generate($this->config->with(path: $path));

    expect($path)->toBeFile();
});

it('writes a regular, non-executable file and leaves nothing else behind', function () {
    $this->generator->generate($this->config);
    clearstatcache();

    expect(fileperms($this->schemaPath()) & 0777)->toBe(0644)
        ->and(array_values(array_diff(scandir($this->workspace), ['.', '..'])))->toBe(['schema.php']);
});

it('writes a file for an empty database', function () {
    Schema::drop('users');

    expect($this->generator->generate($this->config))->toBe(GenerationResult::Written)
        ->and(file_get_contents($this->schemaPath()))->not->toContain('Schema::create');
});

it('leaves out the migrations table and the configured tables', function () {
    Schema::create('migrations', fn (Blueprint $table) => $table->id());
    Schema::create('telescope_entries', fn (Blueprint $table) => $table->id());
    config(['schema-file.except' => ['telescope_*']]);

    $this->generator->generate(app(SchemaFileConfig::class));

    expect(file_get_contents($this->schemaPath()))
        ->toContain("Schema::create('users'")
        ->not->toContain('migrations')
        ->not->toContain('telescope_entries');
});

it('leaves out a migrations table that has been renamed in the database config', function () {
    Schema::create('schema_versions', fn (Blueprint $table) => $table->id());
    Schema::create('migrations', fn (Blueprint $table) => $table->id());
    config(['database.migrations.table' => 'schema_versions']);

    $this->generator->generate(app(SchemaFileConfig::class));

    expect(file_get_contents($this->schemaPath()))
        ->not->toContain('schema_versions')
        ->toContain("Schema::create('migrations'");
});

it('reads the connection named in the settings', function () {
    Schema::connection('secondary')->create('reports', fn (Blueprint $table) => $table->id());

    $this->generator->generate($this->config->with(connection: 'secondary'));

    expect(file_get_contents($this->schemaPath()))
        ->toContain("Schema::create('reports'")
        ->not->toContain("Schema::create('users'");
});

it('fails for a connection that is not configured', function () {
    expect(fn () => $this->generator->generate($this->config->with(connection: 'nope')))
        ->toThrow(InvalidArgumentException::class, 'Database connection [nope] not configured.');

    expect($this->schemaPath())->not->toBeFile();
});

describe('isUpToDate', function () {
    it('is false when there is no file', function () {
        expect($this->generator->isUpToDate($this->config))->toBeFalse();
    });

    it('is true right after generating', function () {
        $this->generator->generate($this->config);

        expect($this->generator->isUpToDate($this->config))->toBeTrue();
    });

    it('is false once the database has changed', function () {
        $this->generator->generate($this->config);

        Schema::table('users', fn (Blueprint $table) => $table->string('name')->nullable());

        expect($this->generator->isUpToDate($this->config))->toBeFalse();
    });

    it('is false for a file that differs only in whitespace', function () {
        $this->generator->generate($this->config);
        file_put_contents($this->schemaPath(), file_get_contents($this->schemaPath())."\n");

        expect($this->generator->isUpToDate($this->config))->toBeFalse();
    });

    it('is false for an empty file', function () {
        file_put_contents($this->schemaPath(), '');

        expect($this->generator->isUpToDate($this->config))->toBeFalse();
    });

    it('never writes', function () {
        $this->generator->isUpToDate($this->config);

        expect($this->schemaPath())->not->toBeFile();
    });
});
