<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console\Operations;

/**
 * How an operation ended: whether it succeeded, and the one line to tell the user.
 *
 * An operation returns this instead of printing, so it knows nothing about the console.
 */
final readonly class OperationResult
{
    private function __construct(
        public bool $successful,
        public string $message,
    ) {
    }

    public static function success(string $message): self
    {
        return new self(true, $message);
    }

    public static function failure(string $message): self
    {
        return new self(false, $message);
    }
}
