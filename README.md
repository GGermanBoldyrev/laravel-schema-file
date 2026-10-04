<p align="center">
  <img src="art/logo.svg" width="128" height="128" alt="laravel-schema-file">
</p>

<h1 align="center">laravel-schema-file</h1>

<p align="center">
  One always up-to-date file with your whole database schema.<br>
  Inspired by Rails' <code>schema.rb</code>.
</p>

> **Status: work in progress.** Nothing below is implemented yet — this describes what the package is being built to do.

## What it is

In Rails, every `db:migrate` rewrites `db/schema.rb`: a single readable file with every table, column, index and foreign key. You commit it, you read it instead of digging through a hundred migrations, and every schema change shows up in code review as a diff of one file.

Laravel has no such thing. `schema:dump` produces a raw SQL dump, is run by hand, and is meant for squashing migrations rather than for reading.

`laravel-schema-file` brings the Rails approach to Laravel:

- **One file** — `database/schema.php`, written in the same Blueprint syntax you use in migrations.
- **Always current** — regenerated from the real database after every `migrate` and `migrate:rollback`.
- **Made for git** — stable ordering, so a schema change is a small, reviewable diff.

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('email')->unique();
    $table->foreignId('team_id')->nullable();
    $table->timestamps();
});
```

## Requirements

- PHP 8.2+
- Laravel 11+
