<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console\Operations;

use RuntimeException;

final class ConflictingOperationsException extends RuntimeException
{
    /**
     * @param  list<Operation>  $operations  The ones that all support the same command line.
     */
    public static function between(array $operations): self
    {
        return new self(sprintf(
            'The command line asks for more than one operation at once: %s. Ask for one of them at a time.',
            implode(', ', array_map(fn (Operation $operation): string => class_basename($operation), $operations)),
        ));
    }
}
