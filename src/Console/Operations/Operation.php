<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Console\Operations;

use GGermanBoldyrev\SchemaFile\SchemaFileConfig;

/**
 * One thing the schema:generate command can do with the schema file, such as writing it or checking it.
 *
 * The command and its operations are the package's own, not something an application extends.
 * To give the command another mode, declare its option in the command's signature and list the
 * operation in the service provider among the ones that have to be asked for. A command line that
 * two of those support is refused, so each answers to an option of its own; writing is the
 * fallback and answers to none. An operation is chosen by the options but works from the settings
 * alone: a mode that needs a value of its own gets it through SchemaFileConfig.
 */
interface Operation
{
    /**
     * Whether this operation takes the command line. One that has to be asked for takes only a
     * command line with its option; the fallback takes any.
     *
     * @param  array<string, mixed>  $options  Every option of the command by name; one that was not given holds its default, false for a flag.
     */
    public function supports(array $options): bool;

    public function run(SchemaFileConfig $config): OperationResult;
}
