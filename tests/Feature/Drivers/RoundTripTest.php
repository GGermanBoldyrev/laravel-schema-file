<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The strictest check there is: build a schema, write the file, empty the
 * database, rebuild it from the file alone, write the file again. If the two
 * files are the same, the file said everything the database holds.
 */

it('rebuilds an identical schema from the file', function (string $driver) {
    $connection = useDriver($driver);
    $schema = Schema::connection($connection);

    $schema->create('teams', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $schema->create('everything', function (Blueprint $table) use ($driver) {
        $table->id();
        $table->string('plain');
        $table->string('sized', 50)->default('draft');
        $table->char('code', 4);
        $table->text('body')->nullable();
        $table->tinyText('tiny_body');
        $table->mediumText('medium_body');
        $table->longText('long_body');
        $table->integer('count')->default(0);
        $table->tinyInteger('tiny');
        $table->smallInteger('small');
        $table->mediumInteger('medium');
        $table->bigInteger('big');
        $table->unsignedInteger('unsigned');
        $table->unsignedBigInteger('unsigned_big');
        $table->boolean('flag')->default(false);
        $table->boolean('other_flag')->default(true);
        $table->decimal('price', 10, 4)->default(1.5);
        $table->decimal('amount');
        $table->float('ratio');
        $table->float('single', 10);
        $table->double('precise');
        $table->json('payload');
        $table->jsonb('binary_payload');
        $table->uuid('uuid');
        $table->ulid('ulid');
        $table->enum('status', ['draft', 'in review'])->default('draft');
        $table->date('day');
        $table->dateTime('moment');
        $table->dateTime('fine_moment', 3);
        $table->dateTimeTz('zoned_moment');
        $table->time('clock');
        $table->timeTz('zoned_clock');
        $table->timestamp('seen_at')->useCurrent();
        $table->timestamp('touched_at')->useCurrent()->useCurrentOnUpdate();
        $table->timestamp('maybe_at', 6)->nullable();
        $table->timestampTz('zoned_at')->nullable();
        $table->binary('blob');
        $table->year('year');
        $table->ipAddress('ip');
        $table->macAddress('mac');
        $table->string('quoted')->default("it's");
        $table->string('empty')->default('');
        $table->string('numeric_text')->default('007');
        $table->string('keyword_text')->default('CURRENT_TIMESTAMP');
        $table->integer('computed')->default(DB::raw('(1 + 1)'));
        $table->integer('negative')->default(-5);
        $table->string('described')->comment("The user's note");
        $table->integer('tripled')->storedAs('count * 3');

        if ($driver !== 'pgsql') {
            $table->integer('doubled')->virtualAs('count * 2');
        }

        $table->foreignId('team_id')->constrained()->cascadeOnDelete();
        $table->foreignId('other_team_id')->nullable()->constrained('teams')->nullOnDelete();
        $table->softDeletes();
        $table->rememberToken();
        $table->timestamps();

        $table->unique(['plain', 'code']);
        $table->index('count', 'custom_count_idx');
        $table->index(['day', 'clock']);
    });

    $schema->create('settings', function (Blueprint $table) {
        $table->string('key')->primary();
        $table->text('value');
    });

    $schema->create('plain_keys', function (Blueprint $table) {
        $table->integer('id')->primary();
        $table->string('label');
    });

    $schema->create('counters', function (Blueprint $table) {
        $table->increments('n');
        $table->string('code')->unique();
    });

    $schema->create('role_user', function (Blueprint $table) {
        $table->integer('role_id');
        $table->integer('user_id');
        $table->primary(['role_id', 'user_id']);
    });

    $schema->create('categories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
    });

    [$first, $second] = roundTripOn($connection);

    expect($second)->toBe($first)
        ->and(substr_count($first, 'Schema::create('))->toBe(7)
        ->and(substr_count($first, '$table->foreign('))->toBe(3);
})->with(ALL_DRIVERS);

it('rebuilds an identical schema on a prefixed connection', function (string $driver) {
    $connection = useDriver($driver, prefixed: true);
    $schema = Schema::connection($connection);

    $schema->create('teams', fn (Blueprint $table) => $table->id());
    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->unique();
        $table->string('name')->index('custom_name');
        $table->foreignId('team_id')->constrained()->cascadeOnDelete();
        $table->index(['email', 'name']);
    });

    [$first, $second] = roundTripOn($connection);

    expect($second)->toBe($first)
        ->and($first)->not->toContain('app_')
        ->and($schema->hasTable('users'))->toBeTrue();
})->with(ALL_DRIVERS);

it('rebuilds tables that reference each other', function (string $driver) {
    $connection = useDriver($driver);
    $schema = Schema::connection($connection);

    $schema->create('a', fn (Blueprint $table) => $table->id());
    $schema->create('b', function (Blueprint $table) {
        $table->id();
        $table->foreignId('a_id')->constrained('a');
    });
    $schema->table('a', fn (Blueprint $table) => $table->foreignId('b_id')->nullable()->constrained('b'));

    [$first, $second] = roundTripOn($connection);

    expect($second)->toBe($first)
        ->and(substr_count($first, 'Schema::table('))->toBe(2);
})->with(ALL_DRIVERS);

it('rebuilds the schema migrations leave behind', function (string $driver) {
    $connection = useDriver($driver);

    $this->artisan('migrate', ['--database' => $connection, '--path' => $this->migrationsPath(), '--realpath' => true]);

    [$first, $second] = roundTripOn($connection);

    expect($second)->toBe($first)
        ->and($first)->toContain("Schema::create('users'")->not->toContain('migrations');
})->with(ALL_DRIVERS);
