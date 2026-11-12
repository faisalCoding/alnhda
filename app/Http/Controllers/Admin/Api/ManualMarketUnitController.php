<?php

namespace App\Http\Controllers\Admin\Api;

use App\Exceptions\UnreadableSalesFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportMarketListingsRequest;
use App\Http\Requests\Admin\StoreManualMarketUnitRequest;
use App\Http\Requests\Admin\UpdateManualMarketUnitRequest;
use App\Http\Resources\Admin\ManualMarketUnitResource;
use App\Models\ManualMarketUnit;
use App\Services\Market\ListingDuplicates;
use App\Services\Market\ListingFileImporter;
use App\Services\Market\MarketPlaces;
use App\Services\Market\PlaceName;
use App\Services\Market\Quantiles;
use App\Services\Market\SalesImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Units an admin saw offered on the market and entered by hand, for the
 * market-prices screen to set the company's own units beside.
 */
class ManualMarketUnitController extends Controller
{
    public function __construct(
        private readonly MarketPlaces $places,
        private readonly ListingDuplicates $duplicates,
    ) {}

    /**
     * The units entered for one city, newest seen first, with what they add
     * up to by kind and the choices the form offers.
     */
    public function index(Request $request): JsonResponse
    {
        $city = $request->query('city');
        $cityKey = PlaceName::key(is_string($city) && $city !== '' ? $city : SalesImporter::DETAILED_CITIES[0]);

        $units = ManualMarketUnit::query()
            ->where('city_key', $cityKey)
            ->get()
            ->sortByDesc(fn (ManualMarketUnit $unit): string => $unit->seenOn()?->toDateString().sprintf('%010d', $unit->id))
            ->values();

        // How many listings each one's property has, so a list can say so.
        $sizes = $units->countBy(fn (ManualMarketUnit $unit): string => $unit->groupKey());
        $units->each(fn (ManualMarketUnit $unit) => $unit->setAttribute('group_size', $sizes[$unit->groupKey()]));

        return response()->json([
            'data' => [
                'city' => ['key' => $cityKey, 'label' => $this->places->cityLabel($cityKey) ?? PlaceName::preferred($cityKey)],
                'units' => ManualMarketUnitResource::collection($units)->resolve($request),
                'summary' => $this->summary(ManualMarketUnit::oneEach($units->filter(fn (ManualMarketUnit $unit): bool => $unit->isFresh()))),
                // Each property once, however many sites listed it.
                'properties' => ManualMarketUnit::oneEach($units)->count(),
                'stale' => $units->reject(fn (ManualMarketUnit $unit): bool => $unit->isFresh())->count(),
                'fresh_months' => ManualMarketUnit::FRESH_MONTHS,
                'districts' => $this->places->districts($cityKey),
                'kinds' => $this->places->kinds(),
            ],
        ]);
    }

    public function store(StoreManualMarketUnitRequest $request): JsonResponse
    {
        $unit = ManualMarketUnit::query()->create($this->attributes($request->validated()));
        $this->duplicates->regroup([$unit->city_key]);

        return (new ManualMarketUnitResource($unit->refresh()))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateManualMarketUnitRequest $request, ManualMarketUnit $manualMarketUnit): ManualMarketUnitResource
    {
        $before = $manualMarketUnit->city_key;
        $manualMarketUnit->update($this->attributes($request->validated()));
        $this->duplicates->regroup(array_values(array_unique([$before, $manualMarketUnit->city_key])));

        return new ManualMarketUnitResource($manualMarketUnit->refresh());
    }

    /**
     * Reads a file of listings collected from listing sites, in the same
     * fields the form has. Listings already entered are updated.
     */
    public function import(ImportMarketListingsRequest $request, ListingFileImporter $importer): JsonResponse
    {
        try {
            $report = $importer->import($request->file('file')->getRealPath(), $request->validated('kind'));
        } catch (UnreadableSalesFile $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        return response()->json(['data' => $report], 201);
    }

    public function destroy(ManualMarketUnit $manualMarketUnit): JsonResponse
    {
        $id = $manualMarketUnit->id;
        $manualMarketUnit->delete();
        // The listings it joined together may no longer be joined.
        $this->duplicates->regroup([$manualMarketUnit->city_key]);

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * A unit as it is stored: its place under the market's own spelling and
     * key, and the price per metre every comparison reads.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        $city = PlaceName::label($validated['city']);
        $cityKey = PlaceName::key($city);
        $district = PlaceName::district($validated['district'], $city);
        $districtKey = PlaceName::key($district);
        $area = (float) $validated['area'];
        $price = (int) $validated['price'];

        return [
            'name' => $validated['name'] ?? null,
            'project_name' => $validated['project_name'] ?? null,
            'source_site' => $validated['source_site'] ?? null,
            'listing_id' => $validated['listing_id'] ?? null,
            'rooms' => $validated['rooms'] ?? null,
            'bedrooms' => $validated['bedrooms'] ?? null,
            'bathrooms' => $validated['bathrooms'] ?? null,
            'street_width' => $validated['street_width'] ?? null,
            'facade' => $validated['facade'] ?? null,
            'property_age' => $validated['property_age'] ?? null,
            'property_age_years' => $validated['property_age_years'] ?? null,
            'posted_on' => $validated['posted_on'] ?? null,
            'listing_updated_on' => $validated['listing_updated_on'] ?? null,
            'duplicates' => $validated['duplicates'] ?? null,
            'city' => $this->places->cityLabel($cityKey) ?? PlaceName::preferred($city),
            'city_key' => $cityKey,
            'district' => $this->places->districtLabel($cityKey, $districtKey) ?? $district,
            'district_key' => $districtKey,
            'kind' => $validated['kind'],
            'area' => $area,
            'price' => $price,
            'price_per_meter' => round($price / $area, 2),
            'observed_on' => $validated['observed_on'] ?? now()->toDateString(),
            'source_url' => $validated['source_url'] ?? null,
        ];
    }

    /**
     * What the units still fresh enough to compare with ask for each kind of
     * property, busiest kind first.
     *
     * @param  Collection<int, ManualMarketUnit>  $units
     * @return list<array<string, mixed>>
     */
    private function summary(Collection $units): array
    {
        return $units
            ->groupBy('kind')
            ->map(fn (Collection $group, string $kind): array => ['kind' => $kind, 'units' => $group->count()]
                + Quantiles::spread($group->pluck('price_per_meter')->all())
                + ['median_price' => Quantiles::spread($group->pluck('price')->all())['median']])
            ->sortByDesc('units')
            ->values()
            ->all();
    }
}
