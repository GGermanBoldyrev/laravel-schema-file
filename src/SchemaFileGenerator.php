<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile;

use GGermanBoldyrev\SchemaFile\Reader\SchemaReader;
use GGermanBoldyrev\SchemaFile\Renderer\SchemaRenderer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;

/**
 * Runs the whole pipeline: read the database, render the schema, write the file.
 */
final readonly class SchemaFileGenerator
{
    public function __construct(
        private DatabaseManager $connections,
        private SchemaReader $reader,
        private SchemaRenderer $renderer,
        private Filesystem $files,
    ) {
    }

    /**
     * Write the schema file, leaving it untouched when it already matches the database.
     */
    public function generate(SchemaFileConfig $config): GenerationResult
    {
        $contents = $this->contents($config);

        if ($this->matches($config->path, $contents)) {
            return GenerationResult::Unchanged;
        }

        $this->files->ensureDirectoryExists(dirname($config->path));
        $this->files->replace($config->path, $contents, 0644);

        return GenerationResult::Written;
    }

    /**
     * Whether the schema file on disk matches the current state of the database.
     */
    public function isUpToDate(SchemaFileConfig $config): bool
    {
        return $this->matches($config->path, $this->contents($config));
    }

    private function contents(SchemaFileConfig $config): string
    {
        $tables = $this->reader->read(
            $this->connections->connection($config->connection),
            $config->except,
        );

        return $this->renderer->render($tables);
    }

    private function matches(string $path, string $contents): bool
    {
        return $this->files->exists($path) && $this->files->get($path) === $contents;
    }
}
