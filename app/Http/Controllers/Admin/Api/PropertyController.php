<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePropertyRequest;
use App\Http\Requests\Admin\UpdatePropertyRequest;
use App\Http\Resources\Admin\PropertyResource;
use App\Models\Properties;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PropertyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PropertyResource::collection(
            Properties::query()
                ->with('propertiesImages')
                ->when($request->integer('project_id'), fn ($query, int $projectId) => $query->where('project_id', $projectId))
                ->latest()
                ->get()
        );
    }

    public function store(StorePropertyRequest $request): JsonResponse
    {
        $property = Properties::query()->create($this->propertyData($request->validated()));

        return (new PropertyResource($property->load('propertiesImages')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePropertyRequest $request, Properties $property): PropertyResource
    {
        $attributes = $this->propertyData($request->validated());

        // A kind set by hand on the market screen spoke for the old type. Once
        // the type itself is changed here, it is the type that speaks.
        if ($property->type !== $attributes['type']) {
            $attributes['market_type'] = null;
        }

        $property->update($attributes);

        return new PropertyResource($property->load('propertiesImages'));
    }

    public function destroy(Properties $property): JsonResponse
    {
        foreach ($property->propertiesImages as $image) {
            ImageService::delete($image->url);
        }

        $id = $property->id;
        $property->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * Normalize validated input into a Properties attribute array.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function propertyData(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'project_id' => $validated['project_id'],
            'price' => $validated['price'],
            'offer' => $validated['offer'] ?: null,
            'status' => $validated['status'],
            'rooms' => $validated['rooms'],
            'bathrooms' => $validated['bathrooms'],
            'living_rooms' => $validated['living_rooms'],
            'mainds_room' => $validated['mainds_room'],
            'area' => $validated['area'],
            'doors' => $validated['doors'],
            'type' => $validated['type'],
            'parkings' => $validated['parkings'],
            'driver_room' => $validated['driver_room'],
            'facade' => $validated['facade'],
            'furniture' => $validated['furniture'],
            'unit_youtube' => $validated['unit_youtube'] ?: null,
            'stages_building_youtube' => $validated['stages_building_youtube'] ?: null,
        ];
    }
}
