<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMarketCatalogueRequest;
use App\Services\Market\CatalogueSync;
use Illuminate\Http\JsonResponse;

/**
 * Receives the open data portal's catalogue from a computer that can reach
 * the portal, which this server cannot. It records which files exist and
 * where they are downloaded from; it never receives the files themselves,
 * which are still uploaded by a signed-in admin.
 */
class MarketCatalogueController extends Controller
{
    public function __invoke(StoreMarketCatalogueRequest $request, CatalogueSync $sync): JsonResponse
    {
        return response()->json(['data' => $sync->apply($request->validated())]);
    }
}
