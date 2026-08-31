<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One record placed on a collection page, at the position it was dragged to.
 */
class CollectionPageItem extends Model
{
    protected $fillable = [
        'item_type',
        'item_id',
        'sort_order',
    ];

    /**
     * @return BelongsTo<CollectionPage, $this>
     */
    public function collectionPage(): BelongsTo
    {
        return $this->belongsTo(CollectionPage::class);
    }

    /**
     * The project, unit or article shown at this position.
     */
    public function item(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
