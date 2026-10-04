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
 * It keeps the output of every command that is running right now, innermost last,
 * and forgets each one when its command finishes. Outside of a command there is
 * nowhere to print, and the messages are dropped.
 */
final class ConsoleNotifier
{
    /**
     * @var list<OutputStyle>
     */
    private array $outputs = [];

    public function __construct(
        private readonly Container $container,
    ) {
    }

    public function capture(CommandStarting $event): void
    {
        // Built through the container, exactly as commands build theirs, so that an
        // application's own output style applies to these messages too.
        $this->outputs[] = $this->container->make(OutputStyle::class, [
            'input' => $event->input,
            'output' => $event->output,
        ]);
    }

    public function release(): void
    {
        array_pop($this->outputs);
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
        $output = $this->outputs[array_key_last($this->outputs) ?? 0] ?? null;

        return $output === null ? null : new Factory($output);
    }
}
