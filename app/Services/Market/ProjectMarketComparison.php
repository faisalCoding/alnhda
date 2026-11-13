<?php

namespace App\Services\Market;

use App\Models\ManualMarketUnit;
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
 * property. The second is the ministry's deeds of about the same size nearby,
 * which do not name a kind but are counted deed by deed. The third is what
 * someone saw the same kind of property offered at and entered by hand: an
 * asking price, which neither of the others is. The three are shown side by
 * side, since each answers something the others cannot.
 */
class ProjectMarketComparison
{
    /**
     * How far from a unit's own area a deed may be and still count as a sale
     * of the same size.
     */
    private const AREA_MARGIN = 0.2;

    /**
     * The kinds the market measures by their land. A deed, the authority's
     * figures and a listing all give a villa's area as its plot's, so a villa
     * is set beside them by its own plot when that is known.
     *
     * @var list<string>
     */
    public const LAND_KINDS = ['فيلا', 'دوبلكس', 'أرض'];

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
        private readonly MarketPlaces $places,
    ) {}

    /**
     * @return array{city: array{key: string, label: string}, districts: list<array{key: string, label: string}>, kinds: list<string>, has_type_figures: bool, has_offers: bool, projects: list<array<string, mixed>>}
     */
    public function forCity(string $cityKey): array
    {
        // The districts the project form offers, so a project placed there
        // and one placed here are placed in the same words.
        $districts = collect($this->places->districts($cityKey));

        $published = $this->types->kinds($cityKey);
        $kinds = $published === [] ? MarketPlaces::USUAL_KINDS : $published;

        return [
            'city' => ['key' => $cityKey, 'label' => PlaceName::preferred($cityKey)],
            'districts' => $districts->all(),
            'kinds' => $kinds,
            'has_type_figures' => $published !== [],
            'has_offers' => ManualMarketUnit::query()->where('city_key', $cityKey)->fresh()->exists(),
            'projects' => Project::query()
                ->ordered()
                ->with('properties')
                ->get()
                ->map(fn (Project $project): array => $this->project($project, $cityKey, $districts, $kinds))
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
    private function project(Project $project, string $cityKey, Collection $districts, array $kinds): array
    {
        $placed = $project->market_city_key === $cityKey && filled($project->market_district_key);
        // Not placed, but its location names a district of this city: read
        // it there, and say it was read from the location.
        $guessed = ! $placed && blank($project->market_city_key) ? $this->districtIn($project->location, $cityKey, $districts) : null;
        $district = $placed ? $project->market_district_key : $guessed;

        return [
            'id' => $project->id,
            'name' => $project->name,
            'location' => $project->location,
            'status' => $project->status,
            // Placed in another city: shown, but not compared with this one.
            'elsewhere' => filled($project->market_city_key) && $project->market_city_key !== $cityKey,
            'district' => $placed ? $district : null,
            // 'set' by hand, or read from the project's 'location'.
            'district_source' => $placed ? 'set' : ($guessed !== null ? 'location' : null),
            'district_label' => $district === null
                ? null
                : ($districts->firstWhere('key', $district)['label'] ?? $district),
            'guessed_district' => $guessed,
            'units' => $project->properties
                ->map(fn (Properties $unit): array => $this->unit($unit, $project, $cityKey, $district, $kinds))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $kinds
     * @return array<string, mixed>
     */
    private function unit(Properties $unit, Project $project, string $cityKey, ?string $district, array $kinds): array
    {
        // The offer is what is actually being asked when there is one.
        $price = (int) ($unit->offer ?: $unit->price);
        [$kind, $kindSource] = $this->kind($unit, $project, $kinds);

        // What the unit is measured by: its plot for a kind the market
        // measures by land, when the plot is known; its own area otherwise.
        $byLand = in_array($kind, self::LAND_KINDS, true);
        $land = (float) $unit->land_area;
        $area = $byLand && $land > 0 ? $land : (float) $unit->area;
        $basis = match (true) {
            ! $byLand => 'unit',
            $land > 0 => 'land',
            // A villa measured by what was built: its price per metre reads
            // lower than the market's, which is per metre of plot.
            default => 'unit_not_land',
        };
        $priced = $area > 0 && $price > 0;

        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'status' => $unit->status,
            'kind' => $kind,
            // 'set' by hand, or guessed from the 'unit' or its 'project'.
            'kind_source' => $kindSource,
            'area' => (float) $unit->area > 0 ? (float) $unit->area : null,
            'land_area' => $land > 0 ? $land : null,
            // The area the rate and every comparison are worked out on.
            'basis' => $basis,
            'basis_area' => $area > 0 ? $area : null,
            'price' => $price > 0 ? $price : null,
            'rate' => $priced ? round($price / $area, 2) : null,
            'market' => [
                'kind' => $kind === null ? null : $this->kindFigure($cityKey, $kind, $district),
                'similar' => $area > 0 ? $this->similarDeeds($cityKey, $district, $area) : null,
                'offers' => $area > 0 && $kind !== null ? $this->similarOffers($cityKey, $district, $area, $kind) : null,
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
     * What the same kind of property is offered at: units entered by hand,
     * of the unit's own kind and within a fifth of its size, seen recently
     * enough to still mean something.
     *
     * When the project's district is known, only that district is read, even
     * when nothing like the unit was entered there: asking prices differ far
     * more between districts than sales averaged across a city do, and a
     * villa in one district set beside the villas of three others is set
     * beside the wrong market. Only a project whose district is not known at
     * all is read against the whole city.
     *
     * Without a kind there is nothing to read: a flat's asking price says
     * nothing about a villa's.
     *
     * @return array<string, mixed>|null
     */
    private function similarOffers(string $cityKey, ?string $district, float $area, string $kind): ?array
    {
        $from = $area * (1 - self::AREA_MARGIN);
        $to = $area * (1 + self::AREA_MARGIN);

        return $this->found['offers|'.$kind.'|'.$area.'|'.$district] ??= (function () use ($cityKey, $district, $kind, $from, $to): ?array {
            $figure = $this->offered($cityKey, $district, $kind, $from, $to);

            return $figure['deals'] > 0 ? ['scope' => $district === null ? 'city' : 'district'] + $figure : null;
        })();
    }

    /**
     * The middle asking price per metre of the hand-entered units in one
     * place, each property once however many sites listed it. The count is
     * called «deals» to read like the other figures.
     *
     * @return array{deals: int, lower: float|null, median: float|null, upper: float|null}
     */
    private function offered(string $cityKey, ?string $district, string $kind, float $from, float $to): array
    {
        $units = ManualMarketUnit::query()
            ->fresh()
            ->where('city_key', $cityKey)
            ->when($district !== null, fn ($query) => $query->where('district_key', $district))
            ->where('kind', $kind)
            ->whereBetween('area', [$from, $to])
            ->get();

        $rates = ManualMarketUnit::oneEach($units)
            ->map(fn (ManualMarketUnit $unit): float => (float) $unit->price_per_meter)
            ->all();

        return ['deals' => count($rates)] + Quantiles::spread($rates);
    }

    /**
     * The district of a city a project's location names, when it names one:
     * «جدة حي المنار» names المنار. The location has to name the city too, so
     * a district of the same name in another city is not taken for it.
     *
     * @param  Collection<int, array{key: string, label: string}>  $districts
     */
    private function districtIn(?string $location, string $cityKey, Collection $districts): ?string
    {
        if (blank($location)) {
            return null;
        }

        $words = PlaceName::loose($location);

        if (! str_contains($words, PlaceName::loose($this->places->cityLabel($cityKey) ?? PlaceName::preferred($cityKey)))) {
            return null;
        }

        if (! preg_match('/حي\s*(.+)$/u', PlaceName::label($location), $named)) {
            return null;
        }

        $named = PlaceName::loose($named[1]);

        return $districts->first(fn (array $district): bool => PlaceName::loose($district['label']) === $named)['key'] ?? null;
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
