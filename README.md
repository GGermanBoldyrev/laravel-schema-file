<p align="center">
  <img src="art/social-card.png" alt="Laravel Schema File">
</p>

# Laravel Schema File

One always up-to-date file with your whole database schema, regenerated after every migration. Inspired by Rails' `schema.rb`.

## Why

In Rails, every `db:migrate` rewrites `db/schema.rb`: a single readable file with every table, column, index and foreign key. You commit it, you read it instead of digging through a hundred migrations, and every schema change shows up in code review as a diff of one file.

Laravel has no such thing. `schema:dump` produces a raw SQL dump, is run by hand, and is meant for squashing migrations rather than for reading.

Laravel Schema File brings the Rails approach to Laravel:

- **One file** — `database/schema.php`, written in the same Blueprint syntax you use in migrations.
- **Always current** — regenerated from the real database after every `migrate` and `migrate:rollback`.
- **Made for git** — stable ordering, so a schema change is a small, reviewable diff.

## Requirements

| | Version |
|---|---|
| PHP | 8.2+ |
| Laravel | 12.x, 13.x |
| Database | MySQL, MariaDB, PostgreSQL, SQLite |

The package relies on Laravel's native schema introspection (`Schema::getTables()`, `getColumns()`, `getIndexes()`, `getForeignKeys()`), so it needs no `doctrine/dbal`. Only Laravel versions that still receive fixes are supported.
