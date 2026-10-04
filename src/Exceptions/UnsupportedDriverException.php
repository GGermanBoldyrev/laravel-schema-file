<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Exceptions;

use RuntimeException;

final class UnsupportedDriverException extends RuntimeException
{
    /**
     * @param  list<string>  $supported
     */
    public static function for(string $driver, array $supported): self
    {
        return new self(sprintf(
            'The schema file cannot be generated for the [%s] database driver. Supported drivers: %s.',
            $driver,
            implode(', ', $supported),
        ));
    }
}
