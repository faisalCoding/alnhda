<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBacklinkRequest;
use App\Http\Requests\Admin\UpdateBacklinkRequest;
use App\Http\Resources\Admin\BacklinkResource;
use App\Models\Backlink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BacklinkController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BacklinkResource::collection(
            Backlink::query()->orderByDesc('visits')->orderBy('id')->get()
        );
    }

    public function store(StoreBacklinkRequest $request): JsonResponse
    {
        $backlink = Backlink::query()->create([
            ...$request->validated(),
            'sort_order' => (int) Backlink::query()->max('sort_order') + 1,
        ]);

        return (new BacklinkResource($backlink))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateBacklinkRequest $request, Backlink $backlink): BacklinkResource
    {
        $backlink->update($request->validated());

        return new BacklinkResource($backlink);
    }

    public function destroy(Backlink $backlink): JsonResponse
    {
        $id = $backlink->id;
        $backlink->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
