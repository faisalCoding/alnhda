<?php

namespace App\Models\Concerns;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Lets a record carry its own search and social presentation, overriding the
 * text otherwise derived from its name and body.
 */
trait HasSeoMeta
{
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /**
     * Records meant to be found. One switched to noindex in the SEO panel is
     * asking not to be listed, so it stays out of the sitemap and out of
     * llms.txt rather than being announced and then refused.
     *
     * @param  Builder<static>  $query
     */
    public function scopeIndexable(Builder $query): void
    {
        $query->whereDoesntHave('seoMeta', fn (Builder $meta) => $meta->where('noindex', true));
    }
}
