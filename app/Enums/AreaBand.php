<?php

namespace App\Enums;

/**
 * The size of what a deed sold, in bands that tell property types apart.
 *
 * The ministry's files do not say whether a deed was a flat or a plot. In the
 * one year they did, three sales in four under 300 m² were flats and nearly
 * every sale above it was land, so the area stands in for the type.
 */
enum AreaBand: string
{
    case Under150 = 'under_150';
    case From150 = '150_300';
    case From300 = '300_600';
    case From600 = '600_1000';
    case Over1000 = 'over_1000';

    public static function forArea(float $area): self
    {
        return match (true) {
            $area < 150 => self::Under150,
            $area < 300 => self::From150,
            $area < 600 => self::From300,
            $area < 1000 => self::From600,
            default => self::Over1000,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Under150 => 'أقل من 150 م²',
            self::From150 => '150 – 300 م²',
            self::From300 => '300 – 600 م²',
            self::From600 => '600 – 1000 م²',
            self::Over1000 => '1000 م² فأكثر',
        };
    }
}
