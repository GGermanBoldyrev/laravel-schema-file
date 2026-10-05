<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Console\Operations\ConflictingOperationsException;
use GGermanBoldyrev\SchemaFile\Console\Operations\Operation;
use GGermanBoldyrev\SchemaFile\Console\Operations\OperationRegistry;
use GGermanBoldyrev\SchemaFile\Console\Operations\OperationResult;
use GGermanBoldyrev\SchemaFile\SchemaFileConfig;

/**
 * An operation that supports a command line whenever the callback says so.
 */
function operationSupporting(Closure $supports): Operation
{
    return new class($supports) implements Operation
    {
        public function __construct(
            private Closure $supports,
        ) {
        }

        public function supports(array $options): bool
        {
            return ($this->supports)($options);
        }

        public function run(SchemaFileConfig $config): OperationResult
        {
            return OperationResult::success('done');
        }
    };
}

it('returns the operation the command line asks for', function () {
    $check = operationSupporting(fn (array $options) => $options['check'] === true);
    $write = operationSupporting(fn () => true);
    $registry = new OperationRegistry($write, [$check]);

    expect($registry->for(['check' => true]))->toBe($check)
        ->and($registry->for(['check' => false]))->toBe($write);
});

it('falls back when there is nothing else to ask for', function () {
    $write = operationSupporting(fn () => true);

    expect((new OperationRegistry($write))->for(['check' => true]))->toBe($write);
});

it('refuses a command line that asks for two operations at once', function () {
    $check = operationSupporting(fn (array $options) => $options['check'] === true);
    $diff = operationSupporting(fn (array $options) => $options['diff'] === true);
    $registry = new OperationRegistry(operationSupporting(fn () => true), [$check, $diff]);

    expect(fn () => $registry->for(['check' => true, 'diff' => true]))->toThrow(
        ConflictingOperationsException::class,
        'The command line asks for more than one operation at once',
    );

    expect($registry->for(['check' => true, 'diff' => false]))->toBe($check)
        ->and($registry->for(['check' => false, 'diff' => true]))->toBe($diff);
});

it('does not depend on the order the operations were given in', function () {
    $check = operationSupporting(fn (array $options) => $options['check'] === true);
    $diff = operationSupporting(fn (array $options) => $options['diff'] === true);
    $write = operationSupporting(fn () => true);

    foreach ([[$check, $diff], [$diff, $check]] as $operations) {
        $registry = new OperationRegistry($write, $operations);

        expect($registry->for(['check' => true, 'diff' => false]))->toBe($check)
            ->and($registry->for(['check' => false, 'diff' => true]))->toBe($diff)
            ->and($registry->for(['check' => false, 'diff' => false]))->toBe($write);
    }
});

it('fails when not even the fallback supports the command line', function () {
    $registry = new OperationRegistry(operationSupporting(fn () => false), [operationSupporting(fn () => false)]);

    expect(fn () => $registry->for(['check' => true]))->toThrow(
        LogicException::class,
        'No operation supports the given command line.',
    );
});
