<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Reader\Columns;

/**
 * The column mapper to use for each database driver.
 */
final readonly class ColumnMapperRegistry
{
    /**
     * @param  array<string, ColumnMapper>  $mappers  Keyed by driver name, e.g. "sqlite".
     */
    public function __construct(
        private array $mappers = [],
    ) {
    }

    public function for(string $driver): ColumnMapper
    {
        return $this->mappers[$driver]
            ?? throw UnsupportedDriverException::for($driver, $this->drivers());
    }

    /**
     * A copy that also handles the given driver, replacing any mapper already set for it.
     */
    public function with(string $driver, ColumnMapper $mapper): self
    {
        return new self([...$this->mappers, $driver => $mapper]);
    }

    /**
     * @return list<string>
     */
    public function drivers(): array
    {
        return array_keys($this->mappers);
    }
}
