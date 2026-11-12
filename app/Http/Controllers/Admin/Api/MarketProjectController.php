<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateProjectMarketDistrictRequest;
use App\Models\Project;
use App\Services\Market\PlaceName;
use App\Services\Market\ProjectMarketComparison;
use App\Services\Market\SalesImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The company's own projects beside the market around them.
 */
class MarketProjectController extends Controller
{
    public function index(Request $request, ProjectMarketComparison $comparison): JsonResponse
    {
        $city = $request->query('city');

        return response()->json([
            'data' => $comparison->forCity(PlaceName::key(is_string($city) && $city !== '' ? $city : SalesImporter::DETAILED_CITIES[0])),
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

        return response()->json(['data' => $comparison->forCity($cityKey)]);
    }
}
