<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console\Operations;

use LogicException;

/**
 * The operations the schema:generate command chooses from.
 */
final readonly class OperationRegistry
{
    /**
     * @param  Operation  $fallback  What runs when the command line asks for none of the others.
     * @param  list<Operation>  $operations  The ones that have to be asked for. Their order does not matter:
     *                                       a command line may ask for only one of them.
     */
    public function __construct(
        private Operation $fallback,
        private array $operations = [],
    ) {
    }

    /**
     * The one operation the command line asks for.
     *
     * @param  array<string, mixed>  $options  Every option of the command by name, as Operation::supports() takes them.
     */
    public function for(array $options): Operation
    {
        $asked = array_values(array_filter(
            $this->operations,
            fn (Operation $operation): bool => $operation->supports($options),
        ));

        // Running the first and ignoring the rest would do something other than what was typed.
        if (count($asked) > 1) {
            throw ConflictingOperationsException::between($asked);
        }

        return $asked[0] ?? $this->fallback($options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function fallback(array $options): Operation
    {
        return $this->fallback->supports($options)
            ? $this->fallback
            : throw new LogicException('No operation supports the given command line.');
    }
}
