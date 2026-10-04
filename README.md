<p align="center">
  <img src="art/social-card.png" alt="Laravel Schema File">
</p>

# Laravel Schema File

One always up-to-date file with your whole database schema, regenerated after every migration.
Inspired by Rails' `schema.rb`.

## Why

In Rails, every `db:migrate` rewrites `db/schema.rb`: a single readable file with every table, column, index and foreign key. You commit it, you read it instead of digging through a hundred migrations, and every schema change shows up in code review as a diff of one file.

Laravel has no such thing. `schema:dump` produces a raw SQL dump, is run by hand, and is meant for squashing migrations rather than for reading.

Laravel Schema File brings the Rails approach to Laravel:

- **One file** — `database/schema.php`, written in the same Blueprint syntax you use in migrations.
- **Always current** — regenerated from the real database after every `migrate` and `migrate:rollback`.
- **Made for git** — stable ordering, so a schema change is a small, reviewable diff.

## Requirements

| | Version |
| --- | --- |
| PHP | 8.3+ |
| Laravel | 13.x |
| Database | MySQL, MariaDB, PostgreSQL, SQLite |

The package relies on Laravel's native schema introspection (
  `Schema::getTables()`,
  `getColumns()`,
  `getIndexes()`,
  `getForeignKeys()`,
), so it needs no `doctrine/dbal`. Only Laravel versions that still receive fixes are supported.

## Installation

```bash
composer require --dev ggermanboldyrev/laravel-schema-file
```

That is all: Laravel discovers the package's service provider automatically.

If you have disabled package discovery, register the provider yourself in `bootstrap/providers.php`:

```php
return [
    App\Providers\AppServiceProvider::class,
    GGermanBoldyrev\SchemaFile\SchemaFileServiceProvider::class,
];
```

## Usage

```bash
php artisan schema:generate
```

Writes the schema file. The file is left untouched when it already matches the database. The command is also available as `migrate:schema`.

| Option | What it does |
| --- | --- |
| `--check` | Writes nothing. Exits with code `1` if the schema file is missing or out of date, `0` otherwise. |
| `--path=` | Where to write the schema file, for this run only. |
| `--database=` | The database connection to read the schema from, for this run only. |

Use `--check` in CI to catch a migration that was committed without the updated schema file:

```bash
php artisan migrate
php artisan schema:generate --check
```

For an application with more than one database, generate a file per connection:

```bash
php artisan schema:generate --database=analytics --path=database/analytics-schema.php
```

## Configuration

The defaults work without any setup. Each setting can be changed in `.env`:

| Variable | Default | Meaning |
| --- | --- | --- |
| `SCHEMA_FILE_PATH` | `database/schema.php` | Where the schema file is written. |
| `SCHEMA_FILE_CONNECTION` | the default connection | The connection whose schema is written. |

The `--path` and `--database` options take precedence over these for a single run.

To edit the config file itself, publish it to `config/schema-file.php`:

```bash
php artisan vendor:publish --tag=schema-file-config
```

The published file has one more setting, `except`: the tables to leave out of the schema file, by exact name or by a pattern where `*` matches anything. The table Laravel tracks migrations in is always left out.

```php
'except' => ['failed_jobs', 'telescope_*', 'pulse_*'],
```

## License

MIT. See [LICENSE](LICENSE).
