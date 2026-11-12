<?php

namespace App\Models;

use Database\Factories\ManualMarketUnitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A unit someone saw offered on the market and typed in: a competitor's flat,
 * a listing, a price heard from an agent.
 *
 * It is an asking price, which neither of the other sources has: the
 * ministry's deeds are what was paid and do not name the kind of property,
 * and the authority's indicators name the kind but are averages a quarter or
 * more old. So it stands beside those two rather than in place of either.
 * Its city, district and kind are the market's own, so it lines up with the
 * company's units without anyone matching names.
 *
 * Most are copied from listing sites, and carry what a listing says. One
 * property is often listed on several sites at once; the listings that repeat
 * one another share a duplicate group, and are counted once.
 */
class ManualMarketUnit extends Model
{
    /** @use HasFactory<ManualMarketUnitFactory> */
    use HasFactory;

    /**
     * How long a price that was seen still says something about the market.
     * Older units stay on the list, marked, and are left out of comparisons.
     */
    public const FRESH_MONTHS = 12;

    /**
     * The listing sites these units are usually copied from, by the name a
     * listings file gives them.
     *
     * @var array<string, string>
     */
    public const SITES = [
        'aqar' => 'عقار',
        'bayut' => 'بيوت',
        'haraj' => 'حراج',
        'propertyfinder' => 'بروبرتي فايندر',
    ];

    protected $fillable = [
        'name',
        'project_name',
        'source_site',
        'listing_id',
        'city',
        'city_key',
        'district',
        'district_key',
        'kind',
        'area',
        'price',
        'price_per_meter',
        'rooms',
        'bedrooms',
        'bathrooms',
        'street_width',
        'facade',
        'property_age',
        'property_age_years',
        'posted_on',
        'listing_updated_on',
        'observed_on',
        'source_url',
        'duplicates',
        'duplicate_group',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area' => 'float',
            'price' => 'integer',
            'price_per_meter' => 'float',
            'rooms' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'property_age_years' => 'integer',
            'posted_on' => 'date',
            'listing_updated_on' => 'date',
            'observed_on' => 'date',
        ];
    }

    /**
     * Seen recently enough to compare with. A unit entered before the day was
     * recorded is dated by when it was typed in.
     *
     * @param  Builder<ManualMarketUnit>  $query
     */
    public function scopeFresh(Builder $query): void
    {
        $since = Carbon::today()->subMonths(self::FRESH_MONTHS);

        $query->where(fn (Builder $query) => $query
            ->whereDate('observed_on', '>=', $since)
            ->orWhere(fn (Builder $query) => $query->whereNull('observed_on')->whereDate('created_at', '>=', $since)));
    }

    /**
     * The day the unit was seen on the market.
     */
    public function seenOn(): ?Carbon
    {
        return $this->observed_on ?? $this->created_at;
    }

    public function isFresh(): bool
    {
        return $this->seenOn()?->greaterThanOrEqualTo(Carbon::today()->subMonths(self::FRESH_MONTHS)) ?? true;
    }

    /**
     * The listing as the duplicates column of a listings file names it:
     * «aqar:6874122». Null for a unit that was not copied from a listing.
     */
    public function listingRef(): ?string
    {
        return filled($this->source_site) && filled($this->listing_id)
            ? $this->source_site.':'.$this->listing_id
            : null;
    }

    /**
     * The name of the site the unit was listed on, as a person reads it.
     */
    public function siteLabel(): ?string
    {
        return filled($this->source_site) ? (self::SITES[$this->source_site] ?? $this->source_site) : null;
    }

    /**
     * What a group of repeated listings is known by; a unit repeated nowhere
     * is a group of its own.
     */
    public function groupKey(): string
    {
        return $this->duplicate_group ?? 'unit:'.$this->id;
    }

    /**
     * One listing for each property: of the listings that repeat one another,
     * the one most recently updated. What a comparison reads, so a villa
     * listed on five sites is one villa and not five.
     *
     * @param  Collection<int, ManualMarketUnit>  $units
     * @return Collection<int, ManualMarketUnit>
     */
    public static function oneEach(Collection $units): Collection
    {
        return $units
            ->groupBy(fn (ManualMarketUnit $unit): string => $unit->groupKey())
            ->map(fn (Collection $group): ManualMarketUnit => $group
                ->sortByDesc(fn (ManualMarketUnit $unit): string => ($unit->listing_updated_on ?? $unit->seenOn())?->toDateString().sprintf('%010d', $unit->id))
                ->first())
            ->values();
    }

    /**
     * What to call the unit on a list: its name, or its kind and district
     * when it was entered without one.
     */
    public function label(): string
    {
        return filled($this->name) ? $this->name : $this->kind.' — '.$this->district;
    }
}
