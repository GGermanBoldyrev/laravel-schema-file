<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Renderer\SchemaRenderer;
use GGermanBoldyrev\SchemaFile\Schema\Column;
use GGermanBoldyrev\SchemaFile\Schema\Expression;
use GGermanBoldyrev\SchemaFile\Schema\ForeignKey;
use GGermanBoldyrev\SchemaFile\Schema\Index;
use GGermanBoldyrev\SchemaFile\Schema\IndexType;
use GGermanBoldyrev\SchemaFile\Schema\Table;

function renderEdge(Table ...$tables): string
{
    return (new SchemaRenderer)->render($tables);
}

/**
 * The lines between the braces of a Schema::create() / Schema::table() block.
 *
 * @return list<string>
 */
function blockLines(string $output, string $opening): array
{
    $start = strpos($output, $opening);
    $bodyStart = strpos($output, "{\n", $start) + 2;
    $bodyEnd = strpos($output, "});", $bodyStart);
    $body = rtrim(substr($output, $bodyStart, $bodyEnd - $bodyStart), "\n");

    return $body === '' ? [] : array_map('trim', explode("\n", $body));
}

function assertValidPhp(string $source): void
{
    token_get_all($source, TOKEN_PARSE);

    expect(true)->toBeTrue();
}

it('always produces valid PHP', function (Table $table) {
    assertValidPhp(renderEdge($table));
})->with([
    'empty table' => [new Table('empty')],
    'quotes and backslashes everywhere' => [new Table("it's", [
        new Column("col'umn", 'string', ["arg'ument"], default: "a\\'b", comment: "c\\"),
    ], [new Index(IndexType::Unique, ["col'umn"], "na'me")], [
        new ForeignKey(["col'umn"], "oth'er", ["i'd"], "f'k", "casc'ade", "set'null"),
    ])],
    'new lines and dollars' => [new Table('t', [
        new Column('a', 'text', default: "line one\nline two \$notAVariable {\$neither}", comment: "tab\there"),
    ])],
    'php closing tag in a string' => [new Table('t', [new Column('a', 'string', default: '?>')])],
    'unicode' => [new Table('таблица', [new Column('名前', 'string', default: 'значение ✓')])],
    'raw expression with quotes' => [new Table('t', [
        new Column('a', 'timestamp', default: new Expression("(datetime('now', 'localtime'))")),
    ])],
]);

it('renders a table without columns as an empty block', function () {
    expect(renderEdge(new Table('empty')))->toContain("Schema::create('empty', function (Blueprint \$table) {\n});");
});

it('ends with exactly one new line and has no trailing whitespace', function () {
    $output = renderEdge(
        new Table('a', [new Column('id', 'id')], [new Index(IndexType::Index, ['x', 'y'])], [new ForeignKey(['x'], 'b', ['id'])]),
        new Table('b', [new Column('id', 'id')]),
    );

    expect($output)->toEndWith("});\n")->not->toEndWith("\n\n")
        ->and(preg_match('/[ \t]+$/m', $output))->toBe(0);
});

it('separates blocks with exactly one blank line', function () {
    $output = renderEdge(new Table('a'), new Table('b'), new Table('c', foreignKeys: [new ForeignKey(['x'], 'a', ['id'])]));

    expect($output)->not->toContain("\n\n\n")
        ->and(substr_count($output, "});\n\nSchema::"))->toBe(3);
});

it('does not reorder the tables it was given', function () {
    $tables = [new Table('b'), new Table('a')];

    (new SchemaRenderer)->render($tables);

    expect($tables[0]->name)->toBe('b');
});

it('renders identical output on every call', function () {
    $renderer = new SchemaRenderer;
    $tables = [new Table('t', [new Column('a', 'timestamp', default: new Expression('NOW()'))])];

    expect($renderer->render($tables))->toBe($renderer->render($tables));
});

it('sorts tables by byte order, upper case first', function () {
    $output = renderEdge(new Table('users'), new Table('Users'), new Table('_meta'), new Table('accounts'));

    preg_match_all("/Schema::create\('(\w+)'/", $output, $matches);

    expect($matches[1])->toBe(['Users', '_meta', 'accounts', 'users']);
});

describe('values', function () {
    it('renders numbers exactly', function (int|float $default, string $expected) {
        $output = renderEdge(new Table('t', [new Column('a', 'decimal', default: $default)]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(["\$table->decimal('a')->default({$expected});"]);
    })->with([
        'zero' => [0, '0'],
        'negative integer' => [-5, '-5'],
        'whole float keeps its fraction' => [1.0, '1.0'],
        'negative float' => [-0.5, '-0.5'],
        'small float' => [0.001, '0.001'],
    ]);

    it('renders an empty array argument', function () {
        $output = renderEdge(new Table('t', [new Column('a', 'enum', [[]])]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(["\$table->enum('a', []);"]);
    });

    it('renders nested arrays and mixed argument types', function () {
        $output = renderEdge(new Table('t', [new Column('a', 'custom', [['x', 1, true, ['y']], 2.5, false])]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(["\$table->custom('a', ['x', 1, true, ['y']], 2.5, false);"]);
    });

    it('treats false and zero and the empty string as real defaults', function () {
        $output = renderEdge(new Table('t', [
            new Column('a', 'boolean', default: false),
            new Column('b', 'integer', default: 0),
            new Column('c', 'string', default: ''),
            new Column('d', 'string', default: '0'),
        ]));

        expect(blockLines($output, "Schema::create('t'"))->toBe([
            "\$table->boolean('a')->default(false);",
            "\$table->integer('b')->default(0);",
            "\$table->string('c')->default('');",
            "\$table->string('d')->default('0');",
        ]);
    });

    it('imports the DB facade for a raw default in any table', function () {
        $output = renderEdge(
            new Table('a', [new Column('x', 'string')]),
            new Table('z', [new Column('x', 'string'), new Column('y', 'timestamp', default: new Expression('NOW()'))]),
        );

        expect(substr_count($output, 'use Illuminate\Support\Facades\DB;'))->toBe(1);
    });

    it('does not import the DB facade because a string mentions DB::raw', function () {
        $output = renderEdge(new Table('t', [new Column('a', 'string', default: 'DB::raw(')]));

        expect($output)->not->toContain('use Illuminate\Support\Facades\DB;');
    });
});

describe('id columns', function () {
    it('ignores modifiers that id() already implies', function () {
        $output = renderEdge(new Table('t', [new Column('id', 'id', unsigned: true, autoIncrement: true)]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(['$table->id();']);
    });

    it('drops the primary index of an id column whatever it is called', function (?string $name) {
        $output = renderEdge(new Table('t', [new Column('id', 'id')], [new Index(IndexType::Primary, ['id'], $name)]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(['$table->id();']);
    })->with(['primary', 'PRIMARY', 't_pkey', 'sqlite_autoindex_t_1', null]);

    it('still renders other indexes of an id column', function () {
        $output = renderEdge(new Table('t', [new Column('id', 'id')], [
            new Index(IndexType::Primary, ['id']),
            new Index(IndexType::Index, ['id'], 'custom'),
        ]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(['$table->id();', '', "\$table->index('id', 'custom');"]);
    });
});

describe('index placement', function () {
    it('gives an index on a column that is not in the table its own line', function () {
        $output = renderEdge(new Table('t', [new Column('a', 'string')], [new Index(IndexType::Index, ['ghost'])]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(["\$table->string('a');", '', "\$table->index('ghost');"]);
    });

    it('renders indexes of a table without columns', function () {
        $output = renderEdge(new Table('t', indexes: [new Index(IndexType::Unique, ['a', 'b'])]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(["\$table->unique(['a', 'b']);"]);
    });

    it('prefers the primary key, then unique, when one column has several indexes', function () {
        $output = renderEdge(new Table('t', [new Column('code', 'string')], [
            new Index(IndexType::Index, ['code']),
            new Index(IndexType::Unique, ['code']),
            new Index(IndexType::Primary, ['code']),
        ]));

        expect(blockLines($output, "Schema::create('t'"))->toBe([
            "\$table->string('code')->primary();",
            '',
            "\$table->unique('code');",
            "\$table->index('code');",
        ]);
    });

    it('recognises default names case-insensitively and with dashes and dots replaced', function () {
        $output = renderEdge(new Table('My-Table.v2', [new Column('Some-Col', 'string')], [
            new Index(IndexType::Unique, ['Some-Col'], 'my_table_v2_some_col_unique'),
        ]));

        expect(blockLines($output, "Schema::create('My-Table.v2'"))->toBe(["\$table->string('Some-Col')->unique();"]);
    });

    it('treats a default name of the wrong type as custom', function () {
        $output = renderEdge(new Table('t', [new Column('a', 'string')], [new Index(IndexType::Unique, ['a'], 't_a_index')]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(["\$table->string('a');", '', "\$table->unique('a', 't_a_index');"]);
    });

    it('recognises the default name of every index type', function (IndexType $type, string $name, string $chained) {
        $output = renderEdge(new Table('t', [new Column('a', 'text')], [new Index($type, ['a'], $name)]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(["\$table->text('a')->{$chained}();"]);
    })->with([
        [IndexType::Unique, 't_a_unique', 'unique'],
        [IndexType::Index, 't_a_index', 'index'],
        [IndexType::FullText, 't_a_fulltext', 'fullText'],
        [IndexType::Spatial, 't_a_spatialindex', 'spatialIndex'],
    ]);

    it('orders standalone indexes by type, then columns, then name', function () {
        $output = renderEdge(new Table('t', indexes: [
            new Index(IndexType::Index, ['b', 'a'], 'z'),
            new Index(IndexType::Index, ['a', 'b'], 'z'),
            new Index(IndexType::Index, ['a', 'b'], 'y'),
            new Index(IndexType::FullText, ['a', 'b'], 'x'),
            new Index(IndexType::Unique, ['c', 'd'], 'w'),
            new Index(IndexType::Primary, ['e', 'f']),
        ]));

        expect(blockLines($output, "Schema::create('t'"))->toBe([
            "\$table->primary(['e', 'f']);",
            "\$table->unique(['c', 'd'], 'w');",
            "\$table->index(['a', 'b'], 'y');",
            "\$table->index(['a', 'b'], 'z');",
            "\$table->index(['b', 'a'], 'z');",
            "\$table->fullText(['a', 'b'], 'x');",
        ]);
    });
});

describe('timestamps', function () {
    it('folds the pair wherever it sits among other columns', function () {
        $output = renderEdge(new Table('t', [
            new Column('a', 'string'),
            new Column('created_at', 'timestamp', nullable: true),
            new Column('updated_at', 'timestamp', nullable: true),
            new Column('b', 'string'),
        ]));

        expect(blockLines($output, "Schema::create('t'"))->toBe([
            "\$table->string('a');", '$table->timestamps();', "\$table->string('b');",
        ]);
    });

    it('still folds when a composite index covers the columns', function () {
        $output = renderEdge(new Table('t', [
            new Column('created_at', 'timestamp', nullable: true),
            new Column('updated_at', 'timestamp', nullable: true),
        ], [new Index(IndexType::Index, ['created_at', 'updated_at'])]));

        expect(blockLines($output, "Schema::create('t'"))->toBe([
            '$table->timestamps();', '', "\$table->index(['created_at', 'updated_at']);",
        ]);
    });

    it('does not fold a lone created_at or updated_at', function (string $name) {
        $output = renderEdge(new Table('t', [new Column($name, 'timestamp', nullable: true)]));

        expect(blockLines($output, "Schema::create('t'"))->toBe(["\$table->timestamp('{$name}')->nullable();"]);
    })->with(['created_at', 'updated_at']);

    it('does not fold when a column has a comment or is unsigned', function (Column $createdAt) {
        $output = renderEdge(new Table('t', [$createdAt, new Column('updated_at', 'timestamp', nullable: true)]));

        expect(blockLines($output, "Schema::create('t'"))->toHaveCount(2);
    })->with([
        'comment' => [new Column('created_at', 'timestamp', nullable: true, comment: 'When')],
        'unsigned' => [new Column('created_at', 'timestamp', nullable: true, unsigned: true)],
    ]);
});

describe('foreign keys', function () {
    it('renders no Schema::table block for a table without foreign keys', function () {
        expect(renderEdge(new Table('t', [new Column('id', 'id')])))->not->toContain('Schema::table');
    });

    it('renders a self-referencing key', function () {
        $output = renderEdge(new Table('categories', [new Column('id', 'id'), new Column('parent_id', 'integer', nullable: true)], [], [
            new ForeignKey(['parent_id'], 'categories', ['id'], onDelete: 'set null'),
        ]));

        expect(blockLines($output, "Schema::table('categories'"))->toBe([
            "\$table->foreign('parent_id')->references('id')->on('categories')->onDelete('set null');",
        ]);
    });

    it('renders keys of two tables that reference each other', function () {
        $output = renderEdge(
            new Table('a', [new Column('b_id', 'integer')], [], [new ForeignKey(['b_id'], 'b', ['id'])]),
            new Table('b', [new Column('a_id', 'integer')], [], [new ForeignKey(['a_id'], 'a', ['id'])]),
        );

        expect(strrpos($output, 'Schema::create'))->toBeLessThan(strpos($output, 'Schema::table'))
            ->and(substr_count($output, 'Schema::table'))->toBe(2);
    });

    it('renders only one action when only one is set', function (ForeignKey $foreignKey, string $expected) {
        $output = renderEdge(new Table('t', foreignKeys: [$foreignKey]));

        expect(blockLines($output, "Schema::table('t'"))->toBe([$expected]);
    })->with([
        'update' => [new ForeignKey(['a'], 'o', ['id'], onUpdate: 'restrict'), "\$table->foreign('a')->references('id')->on('o')->onUpdate('restrict');"],
        'delete' => [new ForeignKey(['a'], 'o', ['id'], onDelete: 'restrict'), "\$table->foreign('a')->references('id')->on('o')->onDelete('restrict');"],
    ]);

    it('recognises the default name case-insensitively', function () {
        $output = renderEdge(new Table('Posts', foreignKeys: [new ForeignKey(['User_ID'], 'users', ['id'], 'posts_user_id_foreign')]));

        expect(blockLines($output, "Schema::table('Posts'"))->toBe(["\$table->foreign('User_ID')->references('id')->on('users');"]);
    });

    it('orders keys with the same columns by name, unnamed first', function () {
        $output = renderEdge(new Table('t', foreignKeys: [
            new ForeignKey(['a'], 'y', ['id'], 'fk_b'),
            new ForeignKey(['a'], 'z', ['id']),
            new ForeignKey(['a'], 'x', ['id'], 'fk_a'),
        ]));

        expect(blockLines($output, "Schema::table('t'"))->toBe([
            "\$table->foreign('a')->references('id')->on('z');",
            "\$table->foreign('a', 'fk_a')->references('id')->on('x');",
            "\$table->foreign('a', 'fk_b')->references('id')->on('y');",
        ]);
    });
});

describe('generated columns', function () {
    it('renders the expression and leaves out the nullability a generated column has by default', function () {
        $output = renderEdge(new Table('t', [
            new Column('doubled', 'integer', nullable: true, virtualAs: 'price * 2'),
            new Column('tripled', 'integer', nullable: true, storedAs: 'price * 3'),
        ]));

        expect(blockLines($output, "Schema::create('t'"))->toBe([
            "\$table->integer('doubled')->virtualAs('price * 2');",
            "\$table->integer('tripled')->storedAs('price * 3');",
        ]);
    });

    it('says so when a generated column is not nullable', function () {
        $output = renderEdge(new Table('t', [new Column('doubled', 'integer', virtualAs: 'price * 2')]));

        expect(blockLines($output, "Schema::create('t'"))->toBe([
            "\$table->integer('doubled')->virtualAs('price * 2')->nullable(false);",
        ]);
    });

    it('escapes the expression and keeps the other modifiers', function () {
        $output = renderEdge(new Table('t', [
            new Column('label', 'string', [50], nullable: true, comment: 'Shown in lists', storedAs: "name || ' (it''s)'"),
        ], [new Index(IndexType::Index, ['label'])]));

        expect(blockLines($output, "Schema::create('t'"))->toBe([
            "\$table->string('label', 50)->storedAs('name || \\' (it\\'\\'s)\\'')->comment('Shown in lists')->index();",
        ]);
    });

    it('does not fold generated timestamps', function () {
        $output = renderEdge(new Table('t', [
            new Column('created_at', 'timestamp', nullable: true, virtualAs: 'x'),
            new Column('updated_at', 'timestamp', nullable: true),
        ]));

        expect(blockLines($output, "Schema::create('t'"))->toHaveCount(2);
    });
});
