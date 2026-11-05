<?php

namespace App\Models;

use App\Models\Concerns\HasSeoMeta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A page composed of records that already live elsewhere on the site — the
 * campaign page an admin arranges by hand.
 */
class CollectionPage extends Model
{
    /** @use HasFactory<\Database\Factories\CollectionPageFactory> */
    use HasFactory, HasSeoMeta;

    protected $fillable = [
        'slug',
        'title',
        'description',
    ];

    /**
     * The address is written, not numbered — this link is shared before it is
     * opened.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<CollectionPageItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CollectionPageItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
