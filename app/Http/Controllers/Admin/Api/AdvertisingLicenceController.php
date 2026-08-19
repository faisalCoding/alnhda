<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdvertisingLicenceRequest;
use App\Http\Requests\Admin\UpdateAdvertisingLicenceRequest;
use App\Http\Resources\Admin\AdvertisingLicenceResource;
use App\Models\AdvertisingLicence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdvertisingLicenceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AdvertisingLicenceResource::collection(
            AdvertisingLicence::query()
                ->with('unit.project')
                // Soonest to lapse first; the ones without a date sit at the end.
                ->orderByRaw('expires_on is null, expires_on asc')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreAdvertisingLicenceRequest $request): JsonResponse
    {
        $licence = AdvertisingLicence::query()->create([
            ...$request->validated(),
            'sort_order' => (int) AdvertisingLicence::query()->max('sort_order') + 1,
        ]);

        return (new AdvertisingLicenceResource($licence->load('unit.project')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAdvertisingLicenceRequest $request, AdvertisingLicence $advertisingLicence): AdvertisingLicenceResource
    {
        $advertisingLicence->update($request->validated());

        return new AdvertisingLicenceResource($advertisingLicence->load('unit.project'));
    }

    public function destroy(AdvertisingLicence $advertisingLicence): JsonResponse
    {
        $id = $advertisingLicence->id;
        $advertisingLicence->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
