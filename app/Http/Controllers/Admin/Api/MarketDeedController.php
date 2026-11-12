<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MarketDeedsRequest;
use App\Services\Market\DeedExplorer;
use App\Services\Market\SalesImporter;
use Illuminate\Http\JsonResponse;

/**
 * The deeds of one city, sale by sale, for the market prices screen.
 */
class MarketDeedController extends Controller
{
    public function index(MarketDeedsRequest $request, DeedExplorer $explorer): JsonResponse
    {
        $filters = $request->validated();
        $filters['city'] = $filters['city'] ?? SalesImporter::DETAILED_CITIES[0];

        foreach (['area_min', 'area_max', 'price_min', 'price_max'] as $bound) {
            $filters[$bound] = isset($filters[$bound]) ? (float) $filters[$bound] : null;
        }

        foreach (['quarters', 'page'] as $whole) {
            $filters[$whole] = isset($filters[$whole]) ? (int) $filters[$whole] : null;
        }

        return response()->json(['data' => $explorer->explore($filters)]);
    }
}
