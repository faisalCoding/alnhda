<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreManualMarketUnitRequest;
use App\Http\Resources\Admin\ManualMarketUnitResource;
use App\Models\ManualMarketUnit;
use App\Services\Market\PlaceName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Units an admin adds by hand, for the market-prices screen to compare with.
 */
class ManualMarketUnitController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ManualMarketUnitResource::collection(
            ManualMarketUnit::query()->latest('id')->get()
        );
    }

    public function store(StoreManualMarketUnitRequest $request): JsonResponse
    {
        $city = PlaceName::label($request->validated('city'));
        $district = PlaceName::label($request->validated('district'));
        $area = (float) $request->validated('area');
        $price = (int) $request->validated('price');

        $unit = ManualMarketUnit::query()->create([
            'name' => $request->validated('name'),
            'project_name' => $request->validated('project_name'),
            'city' => PlaceName::preferred($city),
            'city_key' => PlaceName::key($city),
            'district' => $district,
            'district_key' => PlaceName::key($district),
            'kind' => $request->validated('kind'),
            'area' => $area,
            'price' => $price,
            'price_per_meter' => round($price / $area, 2),
        ]);

        return (new ManualMarketUnitResource($unit))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(ManualMarketUnit $manualMarketUnit): JsonResponse
    {
        $id = $manualMarketUnit->id;
        $manualMarketUnit->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
