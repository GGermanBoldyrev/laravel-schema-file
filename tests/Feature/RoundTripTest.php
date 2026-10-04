<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\SchemaFileConfig;
use GGermanBoldyrev\SchemaFile\SchemaFileGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generate from the default connection, load the file into the empty secondary
 * connection, generate from that, and return both files.
 *
 * @return array{string, string}
 */
function roundTrip(): array
{
    $generator = app(SchemaFileGenerator::class);
    $config = app(SchemaFileConfig::class);
    $first = test()->workspace.'/first.php';
    $second = test()->workspace.'/second.php';

    $generator->generate($config->with(path: $first));

    DB::setDefaultConnection('secondary');
    require $first;
    DB::setDefaultConnection('testing');

    $generator->generate($config->with(path: $second, connection: 'secondary'));

    return [file_get_contents($first), file_get_contents($second)];
}

it('recreates an identical schema from the file it generated', function () {
    Schema::create('teams', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    Schema::create('everything', function (Blueprint $table) {
        $table->id();
        $table->string('plain');
        $table->string('sized', 50)->default('draft');
        $table->char('code', 4);
        $table->text('body')->nullable();
        $table->longText('long_body');
        $table->integer('count')->default(0);
        $table->bigInteger('big');
        $table->unsignedBigInteger('unsigned_big');
        $table->tinyInteger('tiny');
        $table->boolean('flag')->default(false);
        $table->boolean('other_flag')->default(true);
        $table->decimal('price', 8, 2)->default(1.5);
        $table->float('ratio');
        $table->double('precise');
        $table->json('payload');
        $table->uuid('uuid');
        $table->ulid('ulid');
        $table->enum('status', ['a', 'b'])->default('a');
        $table->date('day');
        $table->dateTime('moment');
        $table->time('clock');
        $table->timestamp('seen_at')->useCurrent();
        $table->timestamp('maybe_at')->nullable();
        $table->binary('blob');
        $table->year('year');
        $table->ipAddress('ip');
        $table->string('quoted')->default("it's");
        $table->string('slashed')->default('App\\Models\\User');
        $table->string('empty')->default('');
        $table->string('numeric_text')->default('007');
        $table->integer('computed')->default(DB::raw('(1 + 1)'));
        $table->integer('negative')->default(-5);
        $table->integer('doubled')->virtualAs('count * 2');
        $table->integer('tripled')->storedAs('count * 3');
        $table->foreignId('team_id')->constrained()->cascadeOnDelete();
        $table->foreignId('other_team_id')->nullable()->constrained('teams')->nullOnDelete();
        $table->softDeletes();
        $table->rememberToken();
        $table->timestamps();

        $table->unique(['plain', 'code']);
        $table->index('count', 'custom_count_idx');
        $table->index(['day', 'clock']);
    });

    Schema::create('settings', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->text('value');
    });

    Schema::create('plain_keys', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->string('label');
    });

    Schema::create('role_user', function (Blueprint $table) {
        $table->integer('role_id');
        $table->integer('user_id');
        $table->primary(['role_id', 'user_id']);
    });

    Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
    });

    [$first, $second] = roundTrip();

    expect($second)->toBe($first)
        ->and(substr_count($first, 'Schema::create('))->toBe(6)
        ->and($first)->toContain("Schema::create('plain_keys', function (Blueprint \$table) {\n    \$table->integer('id')->primary();")
        ->and(DB::connection('secondary')->scalar("select sql from sqlite_master where name = 'plain_keys'"))->not->toContain('autoincrement')
        ->and(DB::connection('secondary')->scalar("select sql from sqlite_master where name = 'teams'"))->toContain('autoincrement')
        ->and($first)
        ->toContain("\$table->integer('doubled')->virtualAs('count * 2')->nullable();")
        ->toContain("\$table->integer('tripled')->storedAs('count * 3')->nullable();");
});

it('recreates an identical schema for an empty database', function () {
    [$first, $second] = roundTrip();

    expect($second)->toBe($first);
});

it('recreates tables that reference each other', function () {
    Schema::create('a', fn (Blueprint $table) => $table->id());
    Schema::create('b', function (Blueprint $table) {
        $table->id();
        $table->foreignId('a_id')->constrained('a');
    });
    Schema::table('a', fn (Blueprint $table) => $table->foreignId('b_id')->nullable()->constrained('b'));

    [$first, $second] = roundTrip();

    expect($second)->toBe($first)
        ->and(substr_count($first, 'Schema::table('))->toBe(2);
});

it('recreates the schema the fixture migrations build', function () {
    $this->artisan('migrate', ['--path' => $this->migrationsPath(), '--realpath' => true]);

    [$first, $second] = roundTrip();

    expect($second)->toBe($first)
        ->and($first)->toContain('$table->timestamps();');
});

it('recreates a prefixed schema on a prefixed connection', function () {
    $schema = Schema::connection('prefixed');
    $schema->create('teams', fn (Blueprint $table) => $table->id());
    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('name')->index('custom_name');
        $table->foreignId('team_id')->constrained();
        $table->index(['email', 'name']);
    });

    $generator = app(SchemaFileGenerator::class);
    $config = app(SchemaFileConfig::class)->with(connection: 'prefixed');
    $generator->generate($config->with(path: $first = $this->workspace.'/first.php'));

    expect(file_get_contents($first))
        ->toContain("\$table->string('email')->unique();")
        ->toContain("\$table->index(['email', 'name']);")
        ->toContain("\$table->index('name', 'custom_name');")
        ->not->toContain('app_');

    $schema->drop('users');
    $schema->drop('teams');
    DB::setDefaultConnection('prefixed');
    require $first;
    DB::setDefaultConnection('testing');

    $generator->generate($config->with(path: $second = $this->workspace.'/second.php'));

    expect(file_get_contents($second))->toBe(file_get_contents($first))
        ->and($schema->hasTable('users'))->toBeTrue();
});

it('generates a file that is valid PHP', function () {
    // Laravel itself cannot inspect a table whose name holds a quote, so the
    // awkward characters go where it can: a column name and a default.
    Schema::create('odd', function (Blueprint $table) {
        $table->string("col'umn")->default("va'lue\\");
    });

    app(SchemaFileGenerator::class)->generate(app(SchemaFileConfig::class));

    token_get_all(file_get_contents($this->schemaPath()), TOKEN_PARSE);

    expect(file_get_contents($this->schemaPath()))
        ->toContain("\$table->string('col\\'umn')->default('va\\'lue\\\\');");
});
