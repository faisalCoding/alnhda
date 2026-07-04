<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\ImageProperties;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class PropertyImageController extends Controller
{
    public function destroy(ImageProperties $image): JsonResponse
    {
        Storage::disk('public')->delete($image->url);

        $id = $image->id;
        $image->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
