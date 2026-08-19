<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddMarketingMethodsRequest;
use App\Http\Requests\Admin\StoreMarketingChecklistItemRequest;
use App\Http\Requests\Admin\StoreMarketingChecklistRequest;
use App\Http\Requests\Admin\UpdateMarketingChecklistItemRequest;
use App\Http\Requests\Admin\UpdateMarketingChecklistRequest;
use App\Http\Resources\Admin\MarketingChecklistItemResource;
use App\Http\Resources\Admin\MarketingChecklistResource;
use App\Models\MarketingChecklist;
use App\Models\MarketingChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class MarketingChecklistController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return MarketingChecklistResource::collection(
            MarketingChecklist::query()->with('items')->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(StoreMarketingChecklistRequest $request): JsonResponse
    {
        $checklist = DB::transaction(function () use ($request): MarketingChecklist {
            $checklist = MarketingChecklist::query()->create([
                'name' => $request->validated('name'),
                'sort_order' => (int) MarketingChecklist::query()->max('sort_order') + 1,
            ]);

            $checklist->addMethods($request->validated('method_ids', []));

            return $checklist;
        });

        return (new MarketingChecklistResource($checklist->load('items')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateMarketingChecklistRequest $request, MarketingChecklist $marketingChecklist): MarketingChecklistResource
    {
        $marketingChecklist->update($request->validated());

        return new MarketingChecklistResource($marketingChecklist->load('items'));
    }

    public function destroy(MarketingChecklist $marketingChecklist): JsonResponse
    {
        $id = $marketingChecklist->id;
        $marketingChecklist->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * Pull chosen marketing methods into the checklist, skipping ones already there.
     */
    public function addMethods(AddMarketingMethodsRequest $request, MarketingChecklist $marketingChecklist): MarketingChecklistResource
    {
        $marketingChecklist->addMethods($request->validated('method_ids'));

        return new MarketingChecklistResource($marketingChecklist->load('items'));
    }

    public function storeItem(StoreMarketingChecklistItemRequest $request, MarketingChecklist $marketingChecklist): JsonResponse
    {
        $item = $marketingChecklist->items()->create([
            ...$request->validated(),
            'sort_order' => (int) $marketingChecklist->items()->max('sort_order') + 1,
        ]);

        return (new MarketingChecklistItemResource($item))
            ->response()
            ->setStatusCode(201);
    }

    public function updateItem(UpdateMarketingChecklistItemRequest $request, MarketingChecklistItem $item): MarketingChecklistItemResource
    {
        $attributes = $request->validated();

        if (array_key_exists('is_done', $attributes)) {
            $attributes['completed_at'] = $attributes['is_done'] ? now() : null;
        }

        $item->update($attributes);

        return new MarketingChecklistItemResource($item);
    }

    public function destroyItem(MarketingChecklistItem $item): JsonResponse
    {
        $id = $item->id;
        $item->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
