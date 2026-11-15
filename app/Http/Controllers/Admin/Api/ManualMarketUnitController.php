<?php

namespace App\Http\Controllers\Admin\Api;

use App\Exceptions\UnreadableSalesFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportManualMarketUnitsRequest;
use App\Http\Requests\Admin\ImportMarketListingsRequest;
use App\Http\Requests\Admin\StoreManualMarketUnitRequest;
use App\Http\Requests\Admin\UpdateManualMarketUnitRequest;
use App\Http\Resources\Admin\ManualMarketUnitResource;
use App\Models\ManualMarketUnit;
use App\Services\Market\ListingDuplicates;
use App\Services\Market\ListingFileImporter;
use App\Services\Market\ListingsWorkbook;
use App\Services\Market\MarketPlaces;
use App\Services\Market\PlaceName;
use App\Services\Market\Quantiles;
use App\Services\Market\SalesImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Units an admin saw offered on the market and entered by hand, for the
 * market-prices screen to set the company's own units beside.
 */
class ManualMarketUnitController extends Controller
{
    /**
     * What each order is called in a download's title.
     *
     * @var array<string, string>
     */
    private const SORT_LABELS = [
        'seen' => 'الأحدث رصدًا أولًا',
        'price_asc' => 'السعر من الأقل',
        'price_desc' => 'السعر من الأعلى',
        'area_asc' => 'المساحة من الأصغر',
        'area_desc' => 'المساحة من الأكبر',
    ];

    /**
     * The columns of a download, in the order the table reads.
     *
     * @var list<array{label: string, type: string, width: int}>
     */
    private const EXPORT_COLUMNS = [
        ['label' => '#', 'type' => 'number', 'width' => 6],
        ['label' => 'الإعلان', 'type' => 'text', 'width' => 42],
        ['label' => 'النوع', 'type' => 'text', 'width' => 10],
        ['label' => 'الحي', 'type' => 'text', 'width' => 12],
        ['label' => 'السعر (ر.س)', 'type' => 'money', 'width' => 14],
        ['label' => 'المساحة (م²)', 'type' => 'number', 'width' => 12],
        ['label' => 'سعر المتر (ر.س)', 'type' => 'money', 'width' => 14],
        ['label' => 'الغرف', 'type' => 'number', 'width' => 8],
        ['label' => 'دورات المياه', 'type' => 'number', 'width' => 11],
        ['label' => 'العمر', 'type' => 'text', 'width' => 22],
        ['label' => 'عرض الشارع (م)', 'type' => 'text', 'width' => 12],
        ['label' => 'الواجهة', 'type' => 'text', 'width' => 13],
        ['label' => 'المعلن', 'type' => 'text', 'width' => 28],
        ['label' => 'المنصة', 'type' => 'text', 'width' => 12],
        ['label' => 'رقم الإعلان', 'type' => 'text', 'width' => 14],
        ['label' => 'تاريخ النشر', 'type' => 'date', 'width' => 12],
        ['label' => 'آخر تحديث', 'type' => 'date', 'width' => 12],
        ['label' => 'تاريخ الرصد', 'type' => 'date', 'width' => 12],
        ['label' => 'ملاحظات', 'type' => 'text', 'width' => 30],
        ['label' => 'رابط الإعلان', 'type' => 'link', 'width' => 60],
    ];

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
        $cityKey = $this->cityKey($request->query('city'));
        $units = $this->cityUnits($cityKey);

        return response()->json([
            'data' => [
                'city' => ['key' => $cityKey, 'label' => $this->places->cityLabel($cityKey) ?? PlaceName::preferred($cityKey)],
                'units' => ManualMarketUnitResource::collection($units)->resolve($request),
                'summary' => $this->summary(ManualMarketUnit::oneEach($units->filter(fn (ManualMarketUnit $unit): bool => $unit->isFresh() && ! $unit->own_property))),
                'own' => $units->where('own_property', true)->count(),
                // Each property once, however many sites listed it.
                'properties' => ManualMarketUnit::oneEach($units)->count(),
                'stale' => $units->reject(fn (ManualMarketUnit $unit): bool => $unit->isFresh())->count(),
                'fresh_months' => ManualMarketUnit::FRESH_MONTHS,
                'districts' => $this->places->districts($cityKey),
                'kinds' => $this->places->kinds(),
            ],
        ]);
    }

    /**
     * The units of a city as an Excel workbook, narrowed and ordered the way
     * the table on the screen is.
     */
    public function export(ExportManualMarketUnitsRequest $request, ListingsWorkbook $workbook): BinaryFileResponse
    {
        $cityKey = $this->cityKey($request->validated('city'));
        $city = $this->places->cityLabel($cityKey) ?? PlaceName::preferred($cityKey);
        $district = $request->validated('district');
        $kind = $request->validated('kind');
        $sort = $request->validated('sort') ?? 'seen';

        $units = $this->cityUnits($cityKey)
            ->when(filled($district), fn (Collection $units) => $units->where('district_key', PlaceName::key((string) $district)))
            ->when(filled($kind), fn (Collection $units) => $units->where('kind', $kind))
            ->values();

        $units = match ($sort) {
            'price_asc' => $units->sortBy('price'),
            'price_desc' => $units->sortByDesc('price'),
            'area_asc' => $units->sortBy('area'),
            'area_desc' => $units->sortByDesc('area'),
            default => $units,
        };

        $title = collect([
            'وحدات السوق المضافة يدويًا — '.$city,
            filled($district) ? ($units->first()?->district ?? $district) : null,
            $kind,
            self::SORT_LABELS[$sort],
            $units->count().' إعلانًا',
            'حتى '.now()->toDateString(),
        ])->filter()->implode(' · ');

        $path = tempnam(sys_get_temp_dir(), 'listings').'.xlsx';

        $workbook->write($path, 'وحدات السوق', $title, self::EXPORT_COLUMNS, $units->values()->map(fn (ManualMarketUnit $unit, int $index): array => [
            'marked' => (bool) $unit->own_property,
            'cells' => [
                $index + 1,
                $unit->label(),
                $unit->kind,
                $unit->district,
                $unit->price,
                $unit->area,
                $unit->price_per_meter,
                $unit->bedrooms ?? $unit->rooms,
                $unit->bathrooms,
                $unit->property_age ?? ($unit->property_age_years === null ? null : ($unit->property_age_years === 0 ? 'جديد' : $unit->property_age_years.' سنة')),
                $unit->street_width,
                $unit->facade,
                $unit->advertiser,
                $unit->siteLabel(),
                $unit->listing_id,
                $unit->posted_on?->toDateString(),
                $unit->listing_updated_on?->toDateString(),
                $unit->seenOn()?->toDateString(),
                $this->exportNote($unit),
                ['url' => $unit->source_url, 'text' => $unit->source_url],
            ],
        ]));

        $name = 'وحدات-السوق-'.$city.'-'.now()->toDateString().'.xlsx';

        return response()->download($path, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
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

    private function cityKey(mixed $city): string
    {
        return PlaceName::key(is_string($city) && $city !== '' ? $city : SalesImporter::DETAILED_CITIES[0]);
    }

    /**
     * The units entered for a city, newest seen first, each knowing how many
     * listings its property has and whether the company itself placed it.
     *
     * @return Collection<int, ManualMarketUnit>
     */
    private function cityUnits(string $cityKey): Collection
    {
        $units = ManualMarketUnit::query()
            ->where('city_key', $cityKey)
            ->get()
            ->sortByDesc(fn (ManualMarketUnit $unit): string => $unit->seenOn()?->toDateString().sprintf('%010d', $unit->id))
            ->values();

        // How many listings each one's property has, so a list can say so,
        // and whether it is a property the company itself placed, which is
        // listed but not compared with.
        $sizes = $units->countBy(fn (ManualMarketUnit $unit): string => $unit->groupKey());
        $own = ManualMarketUnit::ownGroups($units);

        return $units->each(fn (ManualMarketUnit $unit) => $unit
            ->setAttribute('group_size', $sizes[$unit->groupKey()])
            ->setAttribute('own_property', in_array($unit->groupKey(), $own, true)));
    }

    /**
     * What the last column of a download says about a unit, in a few words.
     */
    private function exportNote(ManualMarketUnit $unit): ?string
    {
        return collect([
            $unit->isOurs() ? 'إعلاننا، لا يُقارَن به' : ($unit->own_property ? 'عقارنا بإعلان آخر، لا يُقارَن به' : null),
            $unit->group_size > 1 ? 'نفس العقار في '.$unit->group_size.' إعلانات' : null,
            $unit->isFresh() ? null : 'رُصد قبل أكثر من سنة، لا يُقارَن به',
        ])->filter()->implode(' · ') ?: null;
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
            'advertiser' => $validated['advertiser'] ?? null,
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
     * property, busiest kind first. The company's own listings are not among
     * them.
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
