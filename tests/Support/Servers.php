<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Tests\Support;

/**
 * The database servers the driver tests run against, in one place.
 *
 * Nothing here is used unless TEST_DATABASES names a driver ("mysql,pgsql") or
 * says "all". The defaults match docker-compose.yml; CI overrides them with the
 * same variables.
 */
final class Servers
{
    public const array DRIVERS = ['mysql', 'mariadb', 'pgsql'];

    private const array DEFAULT_PORTS = ['mysql' => '33061', 'mariadb' => '33062', 'pgsql' => '54321'];

    private const array DEFAULT_USERS = ['mysql' => 'root', 'mariadb' => 'root', 'pgsql' => 'postgres'];

    /**
     * The drivers whose tests should run.
     *
     * @return list<string>
     */
    public static function enabled(): array
    {
        $requested = strtolower(trim((string) getenv('TEST_DATABASES')));

        if ($requested === 'all') {
            return self::DRIVERS;
        }

        return array_values(array_intersect(self::DRIVERS, array_map('trim', explode(',', $requested))));
    }

    /**
     * Connection name => Laravel connection config. Each server gets a plain
     * connection named after its driver and one with a table prefix.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function connections(): array
    {
        $connections = [];

        foreach (self::DRIVERS as $driver) {
            $connections[$driver] = self::connection($driver);
            $connections[$driver.'_prefixed'] = [...self::connection($driver), 'prefix' => 'app_'];
        }

        return $connections;
    }

    /**
     * @return array<string, mixed>
     */
    private static function connection(string $driver): array
    {
        $config = [
            'driver' => $driver,
            'host' => self::env($driver, 'HOST', '127.0.0.1'),
            'port' => self::env($driver, 'PORT', self::DEFAULT_PORTS[$driver]),
            'database' => self::env($driver, 'DATABASE', 'schema_file'),
            'username' => self::env($driver, 'USERNAME', self::DEFAULT_USERS[$driver]),
            'password' => self::env($driver, 'PASSWORD', 'secret'),
            'prefix' => '',
        ];

        return $driver === 'pgsql'
            ? [...$config, 'charset' => 'utf8', 'search_path' => 'public']
            : [...$config, 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci'];
    }

    private static function env(string $driver, string $key, string $default): string
    {
        $value = getenv('TEST_'.strtoupper($driver).'_'.$key);

        return $value === false || $value === '' ? $default : $value;
    }
}
