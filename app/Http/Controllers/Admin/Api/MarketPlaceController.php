<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Services\Market\MarketPlaces;
use App\Services\Market\PlaceName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The choices the project and unit forms offer, so what is typed into the
 * company's own records matches what the market's files call it.
 */
class MarketPlaceController extends Controller
{
    public function __invoke(Request $request, MarketPlaces $places): JsonResponse
    {
        $city = $request->query('city');
        $cityKey = is_string($city) && $city !== '' ? PlaceName::key($city) : null;

        return response()->json([
            'data' => [
                'cities' => $places->cities(),
                'city' => $cityKey,
                'districts' => $cityKey === null ? [] : $places->districts($cityKey),
                'kinds' => $places->kinds(),
            ],
        ]);
    }
}
