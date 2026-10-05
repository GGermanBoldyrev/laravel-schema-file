<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Console\Operations\Modes\CheckSchemaFile;
use GGermanBoldyrev\SchemaFile\Console\Operations\Modes\WriteSchemaFile;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('users', fn (Blueprint $table) => $table->id());
});

describe('writing', function () {
    it('supports any command line', function () {
        $write = app(WriteSchemaFile::class);

        expect($write->supports([]))->toBeTrue()
            ->and($write->supports(['check' => false]))->toBeTrue()
            ->and($write->supports(['check' => true]))->toBeTrue();
    });

    it('writes the file and says where', function () {
        $result = app(WriteSchemaFile::class)->run(app(SchemaFileConfig::class));

        expect($result->successful)->toBeTrue()
            ->and($result->message)->toBe("Schema file written to [{$this->schemaPath()}].")
            ->and(file_get_contents($this->schemaPath()))->toContain("Schema::create('users'");
    });

    it('says so when the file is already up to date', function () {
        app(WriteSchemaFile::class)->run(app(SchemaFileConfig::class));

        $result = app(WriteSchemaFile::class)->run(app(SchemaFileConfig::class));

        expect($result->successful)->toBeTrue()
            ->and($result->message)->toBe("Schema file [{$this->schemaPath()}] is already up to date.");
    });
});

describe('checking', function () {
    it('supports only a command line that asks for a check', function () {
        $check = app(CheckSchemaFile::class);

        expect($check->supports(['check' => true]))->toBeTrue()
            ->and($check->supports(['check' => false]))->toBeFalse()
            ->and($check->supports([]))->toBeFalse();
    });

    it('takes any truthy value for the flag, as Artisan::call() may pass one', function () {
        $check = app(CheckSchemaFile::class);

        expect($check->supports(['check' => 1]))->toBeTrue()
            ->and($check->supports(['check' => 'yes']))->toBeTrue()
            ->and($check->supports(['check' => 0]))->toBeFalse()
            ->and($check->supports(['check' => null]))->toBeFalse();
    });

    it('fails and writes nothing when there is no file', function () {
        $result = app(CheckSchemaFile::class)->run(app(SchemaFileConfig::class));

        expect($result->successful)->toBeFalse()
            ->and($result->message)->toBe("Schema file [{$this->schemaPath()}] is out of date. Run [php artisan schema:generate] to update it.")
            ->and($this->schemaPath())->not->toBeFile();
    });

    it('fails and leaves the file alone when the database has changed', function () {
        app(WriteSchemaFile::class)->run(app(SchemaFileConfig::class));
        $before = file_get_contents($this->schemaPath());

        Schema::table('users', fn (Blueprint $table) => $table->string('name')->nullable());

        $result = app(CheckSchemaFile::class)->run(app(SchemaFileConfig::class));

        expect($result->successful)->toBeFalse()
            ->and($result->message)->toContain('is out of date')
            ->and(file_get_contents($this->schemaPath()))->toBe($before);
    });

    it('passes when the file is up to date', function () {
        app(WriteSchemaFile::class)->run(app(SchemaFileConfig::class));

        $result = app(CheckSchemaFile::class)->run(app(SchemaFileConfig::class));

        expect($result->successful)->toBeTrue()
            ->and($result->message)->toBe("Schema file [{$this->schemaPath()}] is up to date.");
    });
});
