<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMarketingMethodRequest;
use App\Http\Requests\Admin\UpdateMarketingMethodRequest;
use App\Http\Resources\Admin\MarketingMethodResource;
use App\Models\MarketingMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MarketingMethodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return MarketingMethodResource::collection(
            MarketingMethod::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(StoreMarketingMethodRequest $request): JsonResponse
    {
        $marketingMethod = MarketingMethod::query()->create([
            ...$request->validated(),
            'sort_order' => (int) MarketingMethod::query()->max('sort_order') + 1,
        ]);

        return (new MarketingMethodResource($marketingMethod))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMarketingMethodRequest $request, MarketingMethod $marketingMethod): MarketingMethodResource
    {
        $marketingMethod->update($request->validated());

        return new MarketingMethodResource($marketingMethod);
    }

    public function destroy(MarketingMethod $marketingMethod): JsonResponse
    {
        $id = $marketingMethod->id;
        $marketingMethod->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
