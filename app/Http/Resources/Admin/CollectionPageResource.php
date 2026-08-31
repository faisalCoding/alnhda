<?php

namespace App\Http\Resources\Admin;

use App\Models\CollectionPageItem;
use App\Services\LinkTargets;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CollectionPage
 */
class CollectionPageResource extends JsonResource
{
    /**
     * Items come back both as the `type:id` list the panel edits and as named
     * entries it can display, so the screen never has to look them up again.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items->filter(fn (CollectionPageItem $entry): bool => $entry->item !== null);

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'url' => route('collection', $this->resource),
            'items' => $items
                ->map(fn (CollectionPageItem $entry): string => LinkTargets::keyFor($entry->item).':'.$entry->item->getKey())
                ->values()
                ->all(),
            'item_details' => $items
                ->map(fn (CollectionPageItem $entry): array => [
                    'type' => LinkTargets::keyFor($entry->item),
                    'id' => $entry->item->getKey(),
                    'name' => LinkTargets::nameFor($entry->item),
                    'url' => LinkTargets::urlFor($entry->item),
                ])
                ->values()
                ->all(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
