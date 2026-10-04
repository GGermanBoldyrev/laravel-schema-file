<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Contracts\Container\Container;

/**
 * Lets code that runs inside someone else's command, such as the migration listener
 * inside "migrate", print to that command's output in its style.
 *
 * It remembers the output of the command that is currently running. Outside of a
 * command there is nowhere to print, and the messages are dropped.
 */
final class ConsoleNotifier
{
    private ?OutputStyle $output = null;

    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function capture(CommandStarting $event): void
    {
        // Built through the container, exactly as commands build theirs, so that an
        // application's own output style applies to these messages too.
        $this->output = $this->container->make(OutputStyle::class, [
            'input' => $event->input,
            'output' => $event->output,
        ]);
    }

    public function info(string $message): void
    {
        $this->components()?->info($message);
    }

    public function warn(string $message): void
    {
        $this->components()?->warn($message);
    }

    private function components(): ?Factory
    {
        return $this->output === null ? null : new Factory($this->output);
    }
}
