<?php

declare(strict_types=1);

namespace GGermanBoldyrev\SchemaFile\Schema;

enum IndexType: string
{
    case Primary = 'primary';
    case Unique = 'unique';
    case Index = 'index';
    case FullText = 'fulltext';
    case Spatial = 'spatial';

    /**
     * The Blueprint method that creates an index of this type.
     */
    public function method(): string
    {
        return match ($this) {
            self::Primary => 'primary',
            self::Unique => 'unique',
            self::Index => 'index',
            self::FullText => 'fullText',
            self::Spatial => 'spatialIndex',
        };
    }

    /**
     * The suffix Laravel appends when it names an index of this type itself.
     */
    public function suffix(): string
    {
        return match ($this) {
            self::Spatial => 'spatialindex',
            default => $this->value,
        };
    }
}
