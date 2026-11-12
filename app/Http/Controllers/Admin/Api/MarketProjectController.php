<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\ComparisonSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarketComparisonRequest;
use App\Http\Requests\Admin\UpdateProjectMarketDistrictRequest;
use App\Http\Requests\Admin\UpdateUnitMarketTypeRequest;
use App\Models\Project;
use App\Models\Properties;
use App\Services\Market\PlaceName;
use App\Services\Market\ProjectMarketComparison;
use App\Services\Market\SalesImporter;
use Illuminate\Http\JsonResponse;

/**
 * The company's own units beside what the market paid for the same kind of
 * property around them.
 */
class MarketProjectController extends Controller
{
    public function index(MarketComparisonRequest $request, ProjectMarketComparison $comparison): JsonResponse
    {
        $city = $request->validated('city');

        return response()->json([
            'data' => $comparison->forCity(
                PlaceName::key(is_string($city) && $city !== '' ? $city : SalesImporter::DETAILED_CITIES[0]),
                $request->source(),
            ),
        ]);
    }

    /**
     * Places a project in a district of the deeds, or takes it out of one.
     */
    public function update(UpdateProjectMarketDistrictRequest $request, Project $project, ProjectMarketComparison $comparison): JsonResponse
    {
        $cityKey = PlaceName::key($request->validated('city'));
        $district = $request->validated('district');

        $project->forceFill(filled($district)
            ? ['market_city_key' => $cityKey, 'market_district_key' => PlaceName::key($district)]
            : ['market_city_key' => null, 'market_district_key' => null])->save();

        return response()->json(['data' => $comparison->forCity($cityKey, ComparisonSource::fromRequest($request->validated('source')))]);
    }

    /**
     * Says what kind of property a unit is, or goes back to guessing it.
     */
    public function updateUnit(UpdateUnitMarketTypeRequest $request, Properties $unit, ProjectMarketComparison $comparison): JsonResponse
    {
        $unit->forceFill(['market_type' => $request->validated('kind') ?: null])->save();

        return response()->json([
            'data' => $comparison->forCity(
                PlaceName::key($request->validated('city')),
                ComparisonSource::fromRequest($request->validated('source')),
            ),
        ]);
    }
}
