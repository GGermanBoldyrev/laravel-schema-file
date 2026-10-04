<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile;

/**
 * What generating the schema file did to the file on disk.
 */
enum GenerationResult
{
    /** The file was created or its contents replaced. */
    case Written;

    /** The file already matched the database and was left untouched. */
    case Unchanged;
}
