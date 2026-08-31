<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCollectionPageRequest;
use App\Http\Requests\Admin\UpdateCollectionPageRequest;
use App\Http\Resources\Admin\CollectionPageResource;
use App\Models\CollectionPage;
use App\Services\LinkTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CollectionPageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CollectionPageResource::collection(
            CollectionPage::query()->with('items.item')->latest()->get()
        );
    }

    public function store(StoreCollectionPageRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $page = DB::transaction(function () use ($validated): CollectionPage {
            $page = CollectionPage::query()->create([
                'slug' => $validated['slug'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncItems($page, $validated);

            return $page;
        });

        return (new CollectionPageResource($page->load('items.item')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCollectionPageRequest $request, CollectionPage $collectionPage): CollectionPageResource
    {
        $validated = $request->validated();

        DB::transaction(function () use ($collectionPage, $validated): void {
            $collectionPage->update([
                'slug' => $validated['slug'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
            ]);

            $this->syncItems($collectionPage, $validated);
        });

        return new CollectionPageResource($collectionPage->load('items.item'));
    }

    public function destroy(CollectionPage $collectionPage): JsonResponse
    {
        $id = $collectionPage->id;
        $collectionPage->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * The sent list is the page: it is written out in the order it arrived.
     * A payload that carries no `items` key leaves the page as it stands,
     * so an edit of the title alone never empties it.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncItems(CollectionPage $page, array $validated): void
    {
        if (! array_key_exists('items', $validated)) {
            return;
        }

        $page->items()->delete();

        foreach (array_values((array) $validated['items']) as $position => $entry) {
            [$type, $id] = explode(':', (string) $entry, 2);

            $page->items()->create([
                'item_type' => LinkTargets::classFor($type),
                'item_id' => (int) $id,
                'sort_order' => $position,
            ]);
        }
    }
}
