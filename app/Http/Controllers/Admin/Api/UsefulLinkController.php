<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUsefulLinkRequest;
use App\Http\Requests\Admin\UpdateUsefulLinkRequest;
use App\Http\Resources\Admin\UsefulLinkResource;
use App\Models\UsefulLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UsefulLinkController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UsefulLinkResource::collection(
            UsefulLink::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(StoreUsefulLinkRequest $request): JsonResponse
    {
        $usefulLink = UsefulLink::query()->create([
            ...$request->validated(),
            'sort_order' => (int) UsefulLink::query()->max('sort_order') + 1,
        ]);

        return (new UsefulLinkResource($usefulLink))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUsefulLinkRequest $request, UsefulLink $usefulLink): UsefulLinkResource
    {
        $usefulLink->update($request->validated());

        return new UsefulLinkResource($usefulLink);
    }

    public function destroy(UsefulLink $usefulLink): JsonResponse
    {
        $id = $usefulLink->id;
        $usefulLink->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
