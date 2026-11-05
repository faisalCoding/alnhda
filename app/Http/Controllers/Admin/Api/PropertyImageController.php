<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageProperties;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;

class PropertyImageController extends Controller
{
    public function destroy(ImageProperties $image): JsonResponse
    {
        ImageService::delete($image->url);

        $id = $image->id;
        $image->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
