<?php

declare(strict_types=1);

use GGermanBoldyrev\SchemaFile\Console\ConsoleNotifier;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Container\Container;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

function commandStarting(OutputInterface $output, string $command = 'migrate'): CommandStarting
{
    return new CommandStarting($command, new ArrayInput([]), $output);
}

beforeEach(function () {
    $this->notifier = new ConsoleNotifier(new Container);
});

it('prints to the output of the running command', function () {
    $output = new BufferedOutput;
    $this->notifier->capture(commandStarting($output));

    $this->notifier->info('Schema file written.');
    $this->notifier->warn('Something is off.');

    expect($output->fetch())
        ->toContain('INFO')->toContain('Schema file written.')
        ->toContain('WARN')->toContain('Something is off.');
});

it('drops messages when no command is running', function () {
    $this->notifier->info('nobody hears this');
    $this->notifier->warn('nor this');
})->throwsNoExceptions();

it('prints to the innermost of nested commands', function () {
    [$outer, $inner] = [new BufferedOutput, new BufferedOutput];
    $this->notifier->capture(commandStarting($outer, 'migrate:fresh'));
    $this->notifier->capture(commandStarting($inner, 'migrate'));

    $this->notifier->info('to the inner one');

    expect($inner->fetch())->toContain('to the inner one')
        ->and($outer->fetch())->toBe('');
});

it('goes back to the outer command when the inner one finishes', function () {
    [$outer, $inner] = [new BufferedOutput, new BufferedOutput];
    $this->notifier->capture(commandStarting($outer));
    $this->notifier->capture(commandStarting($inner));
    $this->notifier->release();

    $this->notifier->info('to the outer one');

    expect($outer->fetch())->toContain('to the outer one')
        ->and($inner->fetch())->toBe('');
});

it('forgets the output once its command has finished', function () {
    $output = new BufferedOutput;
    $this->notifier->capture(commandStarting($output));
    $this->notifier->release();

    $this->notifier->info('too late');

    expect($output->fetch())->toBe('');
});

it('survives more commands finishing than started', function () {
    $this->notifier->release();
    $this->notifier->release();

    $output = new BufferedOutput;
    $this->notifier->capture(commandStarting($output));
    $this->notifier->info('still works');

    expect($output->fetch())->toContain('still works');
});

it('prints nothing to a quiet command', function () {
    $output = new BufferedOutput(OutputInterface::VERBOSITY_QUIET);
    $this->notifier->capture(commandStarting($output));

    $this->notifier->info('hush');
    $this->notifier->warn('hush');

    expect($output->fetch())->toBe('');
});
