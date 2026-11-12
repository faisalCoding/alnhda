<?php

namespace App\Enums;

/**
 * Where a unit's comparable market price is read from.
 *
 * Ministry is the deeds the Ministry of Justice published. Manual is the
 * units an admin typed in by hand. Ministry stays the default so a report
 * that does not ask is the one it always was.
 */
enum ComparisonSource: string
{
    case Ministry = 'ministry';
    case Manual = 'manual';

    public static function fromRequest(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? $value : '') ?? self::Ministry;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
