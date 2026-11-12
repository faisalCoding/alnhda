<?php

namespace App\Services\Market;

use App\Models\ManualMarketUnit;

/**
 * Works out which hand-entered listings are one property listed more than
 * once, from what each listing says it repeats.
 *
 * A listings file names, beside each listing, the others it was found to
 * repeat — «aqar:6874122;haraj:11169632270», or «duplicate ad of 88154537» for
 * one on the same site. Those claims are not always made both ways round, so
 * the listings are joined through every claim either of them makes, and each
 * connected set becomes one group, named by the first of its listings.
 */
class ListingDuplicates
{
    /**
     * The listings a duplicates note names, as «site:number». A bare number is
     * a listing on the same site as the one the note is on.
     *
     * @return list<string>
     */
    public static function refs(?string $note, ?string $site): array
    {
        if (blank($note)) {
            return [];
        }

        $refs = [];

        preg_match_all('/([a-z]+)\s*:\s*(\d+)/i', $note, $named, PREG_SET_ORDER);

        foreach ($named as [, $namedSite, $number]) {
            $refs[] = strtolower($namedSite).':'.$number;
        }

        if (filled($site)) {
            preg_match_all('/(?<![:\d])(\d{6,})/', $note, $bare);

            foreach ($bare[1] as $number) {
                $refs[] = strtolower($site).':'.$number;
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * Gives the units of the given cities the duplicate group they belong to,
     * and none to a unit that repeats nothing.
     *
     * @param  list<string>  $cityKeys
     * @return int How many groups hold more than one listing.
     */
    public function regroup(array $cityKeys): int
    {
        $units = ManualMarketUnit::query()->whereIn('city_key', $cityKeys)->get();
        $parent = [];

        $find = function (string $ref) use (&$parent, &$find): string {
            $parent[$ref] ??= $ref;

            if ($parent[$ref] !== $ref) {
                $parent[$ref] = $find($parent[$ref]);
            }

            return $parent[$ref];
        };

        $join = function (string $one, string $other) use (&$parent, $find): void {
            [$a, $b] = [$find($one), $find($other)];

            if ($a !== $b) {
                // The smaller name wins, so a group is named the same however
                // the listings arrive.
                $parent[max($a, $b)] = min($a, $b);
            }
        };

        $nodes = [];

        foreach ($units as $unit) {
            $node = $unit->listingRef() ?? 'unit:'.$unit->id;
            $nodes[$unit->id] = $node;
            $find($node);

            foreach (self::refs($unit->duplicates, $unit->source_site) as $ref) {
                $join($node, $ref);
            }
        }

        $sizes = [];

        foreach ($nodes as $node) {
            $root = $find($node);
            $sizes[$root] = ($sizes[$root] ?? 0) + 1;
        }

        foreach ($units as $unit) {
            $root = $find($nodes[$unit->id]);
            $group = $sizes[$root] > 1 ? $root : null;

            if ($unit->duplicate_group !== $group) {
                $unit->forceFill(['duplicate_group' => $group])->saveQuietly();
            }
        }

        return count(array_filter($sizes, fn (int $size): bool => $size > 1));
    }
}
