<?php

namespace App\Services\Market;

/**
 * The value a given share of a list falls below: 0.5 is the median, 0.25 and
 * 0.75 the quarters either side of it.
 *
 * Property prices are read by their median rather than their mean because
 * deeds include gifts recorded at a token price and farmland sold by the
 * square kilometre, and a mean follows those. The quarters say how wide the
 * ordinary range is.
 */
class Quantiles
{
    /**
     * @param  list<float|int>  $sorted  Ascending.
     */
    public static function of(array $sorted, float $share): float
    {
        $position = (count($sorted) - 1) * $share;
        $below = (int) floor($position);
        $above = (int) ceil($position);

        return $sorted[$below] + ($sorted[$above] - $sorted[$below]) * ($position - $below);
    }

    /**
     * The lower quarter, the median and the upper quarter of an unsorted list,
     * or nulls for an empty one.
     *
     * @param  list<float|int>  $values
     * @return array{lower: float|null, median: float|null, upper: float|null}
     */
    public static function spread(array $values): array
    {
        if ($values === []) {
            return ['lower' => null, 'median' => null, 'upper' => null];
        }

        sort($values);

        return [
            'lower' => round(self::of($values, 0.25), 2),
            'median' => round(self::of($values, 0.5), 2),
            'upper' => round(self::of($values, 0.75), 2),
        ];
    }
}
