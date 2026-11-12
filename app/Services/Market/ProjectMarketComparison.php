<?php

namespace App\Services\Market;

use App\Enums\ComparisonSource;
use App\Models\ManualMarketUnit;
use App\Models\MarketDeed;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Support\Collection;

/**
 * Sets what the company asks for each of its units beside what the market
 * paid for the same kind of property.
 *
 * Two things have to be said by hand, because neither is in the company's own
 * records in the market's terms. A project is placed in a district, since its
 * location is written for a buyer. A unit is given its kind — flat, villa,
 * floor — since the authority prices by kind and a unit's own type is often
 * the default «وحدة عقارية»; until it is set, the kind is guessed.
 *
 * Each unit is then read against two things. The first is what the authority
 * says its kind sold for, in the project's district where the authority went
 * that far and in the city otherwise: that is the price of the same kind of
 * property. The second is a price of about the same size nearby. By default
 * that is the ministry's deeds, which do not name a kind but are counted deed
 * by deed. Asked for the manual source, it is the units an admin entered by
 * hand, and those do keep a kind.
 */
class ProjectMarketComparison
{
    /**
     * How far from a unit's own area a deed may be and still count as a sale
     * of the same size.
     */
    private const AREA_MARGIN = 0.2;

    /**
     * Figures already looked up in this reading: units of one project mostly
     * share a kind and a size, and ask the same questions.
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $found = [];

    public function __construct(
        private readonly DeedExplorer $deeds,
        private readonly PropertyTypeFigures $types,
    ) {}

    /**
     * @return array{city: array{key: string, label: string}, districts: list<array{key: string, label: string}>, kinds: list<string>, has_type_figures: bool, comparison_source: string, projects: list<array<string, mixed>>}
     */
    public function forCity(string $cityKey, ComparisonSource $source = ComparisonSource::Ministry): array
    {
        $districts = MarketDeed::query()
            ->where('city_key', $cityKey)
            ->where('district_key', '!=', '')
            ->toBase()
            ->selectRaw('district_key as "key", max(district) as label')
            ->groupBy('district_key')
            ->orderBy('label')
            ->get()
            ->map(fn (object $row): array => ['key' => $row->key, 'label' => $row->label]);

        $published = $this->types->kinds($cityKey);
        $kinds = $published === [] ? MarketPlaces::USUAL_KINDS : $published;

        return [
            'city' => ['key' => $cityKey, 'label' => PlaceName::preferred($cityKey)],
            'districts' => $districts->all(),
            'kinds' => $kinds,
            'has_type_figures' => $published !== [],
            'comparison_source' => $source->value,
            'projects' => Project::query()
                ->ordered()
                ->with('properties')
                ->get()
                ->map(fn (Project $project): array => $this->project($project, $cityKey, $districts, $kinds, $source))
                ->all(),
        ];
    }

    /**
     * The kinds a unit may be given in a city.
     *
     * @return list<string>
     */
    public function kinds(string $cityKey): array
    {
        return $this->types->kinds($cityKey) ?: MarketPlaces::USUAL_KINDS;
    }

    /**
     * @param  Collection<int, array{key: string, label: string}>  $districts
     * @param  list<string>  $kinds
     * @return array<string, mixed>
     */
    private function project(Project $project, string $cityKey, Collection $districts, array $kinds, ComparisonSource $source): array
    {
        $placed = $project->market_city_key === $cityKey && filled($project->market_district_key);
        $district = $placed ? $project->market_district_key : null;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'location' => $project->location,
            'status' => $project->status,
            // Placed in another city: shown, but not compared with this one.
            'elsewhere' => filled($project->market_city_key) && $project->market_city_key !== $cityKey,
            'district' => $district,
            'district_label' => $placed
                ? ($districts->firstWhere('key', $district)['label'] ?? $district)
                : null,
            'units' => $project->properties
                ->map(fn (Properties $unit): array => $this->unit($unit, $project, $cityKey, $district, $kinds, $source))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $kinds
     * @return array<string, mixed>
     */
    private function unit(Properties $unit, Project $project, string $cityKey, ?string $district, array $kinds, ComparisonSource $comparison): array
    {
        $area = (float) $unit->area;
        // The offer is what is actually being asked when there is one.
        $price = (int) ($unit->offer ?: $unit->price);
        $priced = $area > 0 && $price > 0;
        [$kind, $kindSource] = $this->kind($unit, $project, $kinds);

        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'status' => $unit->status,
            'kind' => $kind,
            // 'set' by hand, or guessed from the 'unit' or its 'project'.
            'kind_source' => $kindSource,
            'area' => $area > 0 ? $area : null,
            'price' => $price > 0 ? $price : null,
            'rate' => $priced ? round($price / $area, 2) : null,
            'market' => [
                'kind' => $kind === null ? null : $this->kindFigure($cityKey, $kind, $district),
                'similar' => $area > 0 ? $this->similar($cityKey, $district, $area, $kind, $comparison) : null,
            ],
        ];
    }

    /**
     * The kind of property a unit is, in the authority's words: the one set
     * by hand, else the unit's own type or its project's when that is a kind
     * the authority prices.
     *
     * @param  list<string>  $kinds
     * @return array{0: string|null, 1: string|null}
     */
    private function kind(Properties $unit, Project $project, array $kinds): array
    {
        $known = collect($kinds)->keyBy(fn (string $kind): string => PlaceName::key($kind));

        foreach (['set' => $unit->market_type, 'unit' => $unit->type, 'project' => $project->project_type] as $source => $value) {
            $kind = $known->get(PlaceName::key((string) $value));

            if ($kind !== null) {
                return [$kind, $source];
            }
        }

        return [null, null];
    }

    /**
     * What the authority says this kind of property sold for: in the district
     * when it published one for it, in the city otherwise.
     *
     * @return array<string, mixed>|null
     */
    private function kindFigure(string $cityKey, string $kind, ?string $district): ?array
    {
        return $this->found['kind|'.$kind.'|'.$district] ??= $this->scoped(
            $district === null ? null : $this->types->reference($cityKey, $kind, $district),
            fn (): ?array => $this->types->reference($cityKey, $kind),
        );
    }

    /**
     * A price of about the same size: the ministry's deeds, unless the report
     * was asked to read the units entered by hand.
     *
     * @return array<string, mixed>|null
     */
    private function similar(string $cityKey, ?string $district, float $area, ?string $kind, ComparisonSource $source): ?array
    {
        if ($source === ComparisonSource::Manual) {
            return $this->similarManual($cityKey, $district, $area, $kind);
        }

        return $this->similarDeeds($cityKey, $district, $area);
    }

    /**
     * The ministry's deeds within a fifth of a unit's size over the latest
     * four quarters: in the district when the project is placed and anything
     * of that size sold there, in the city otherwise.
     *
     * @return array<string, mixed>|null
     */
    private function similarDeeds(string $cityKey, ?string $district, float $area): ?array
    {
        $from = $area * (1 - self::AREA_MARGIN);
        $to = $area * (1 + self::AREA_MARGIN);
        $nearby = $district === null ? null : $this->deeds->comparables($cityKey, $district, $from, $to);

        return $this->found['similar|'.$area.'|'.$district] ??= $this->scoped(
            $nearby !== null && $nearby['deals'] > 0 ? $nearby : null,
            function () use ($cityKey, $from, $to): ?array {
                $city = $this->deeds->comparables($cityKey, null, $from, $to);

                return $city['deals'] > 0 ? $city : null;
            },
        );
    }

    /**
     * Hand-entered units within a fifth of a unit's size, and of its kind when
     * it has one: in the district when the project is placed and any such unit
     * was entered there, in the city otherwise.
     *
     * @return array<string, mixed>|null
     */
    private function similarManual(string $cityKey, ?string $district, float $area, ?string $kind): ?array
    {
        $from = $area * (1 - self::AREA_MARGIN);
        $to = $area * (1 + self::AREA_MARGIN);
        $nearby = $district === null ? null : $this->manualSpread($cityKey, $district, $kind, $from, $to);

        return $this->found['manual|'.($kind ?? '').'|'.$area.'|'.$district] ??= $this->scoped(
            $nearby !== null && $nearby['deals'] > 0 ? $nearby : null,
            function () use ($cityKey, $kind, $from, $to): ?array {
                $city = $this->manualSpread($cityKey, null, $kind, $from, $to);

                return $city['deals'] > 0 ? $city : null;
            },
        );
    }

    /**
     * The middle price per metre of the hand-entered units in one place.
     *
     * @return array{deals: int, lower: float|null, median: float|null, upper: float|null}
     */
    private function manualSpread(string $cityKey, ?string $district, ?string $kind, float $from, float $to): array
    {
        $rates = ManualMarketUnit::query()
            ->where('city_key', $cityKey)
            ->when($district !== null, fn ($query) => $query->where('district_key', $district))
            ->when(filled($kind), fn ($query) => $query->where('kind', $kind))
            ->whereBetween('area', [$from, $to])
            ->pluck('price_per_meter')
            ->map(fn (mixed $rate): float => (float) $rate)
            ->all();

        return ['deals' => count($rates)] + Quantiles::spread($rates);
    }

    /**
     * A figure marked with how wide a place it speaks for.
     *
     * @param  array<string, mixed>|null  $district
     * @param  callable(): (array<string, mixed>|null)  $city
     * @return array<string, mixed>|null
     */
    private function scoped(?array $district, callable $city): ?array
    {
        if ($district !== null) {
            return ['scope' => 'district'] + $district;
        }

        $whole = $city();

        return $whole === null ? null : ['scope' => 'city'] + $whole;
    }
}
