<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Tests;

use GGermanBoldyrev\SchemaFile\SchemaFileServiceProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * A directory of its own for every test, removed afterwards.
     */
    public string $workspace;

    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir().'/schema-file-tests/'.bin2hex(random_bytes(8));

        (new Filesystem)->ensureDirectoryExists($this->workspace);

        parent::setUp();

        // Laravel does not dispatch CommandStarting and CommandFinished while running
        // tests. The package relies on them, so they are switched on here to make
        // commands behave as they do in a real console.
        $this->app->make(Kernel::class)->rerouteSymfonyCommandEvents();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem)->deleteDirectory($this->workspace);
    }

    protected function getPackageProviders($app): array
    {
        return [SchemaFileServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $memory = ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true];

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $memory);
        $app['config']->set('database.connections.secondary', $memory);
        $app['config']->set('database.connections.prefixed', [...$memory, 'prefix' => 'app_']);
        $app['config']->set('schema-file.path', $this->schemaPath());
    }

    public function schemaPath(): string
    {
        return $this->workspace.'/schema.php';
    }

    public function migrationsPath(): string
    {
        return __DIR__.'/Fixtures/migrations';
    }
}
