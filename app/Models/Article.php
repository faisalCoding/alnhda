<?php

namespace App\Models;

use App\Models\Concerns\HasSeoMeta;
use App\Services\LinkTargets;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Article extends Model
{
    use HasFactory, HasSeoMeta;

    protected $fillable = [
        'title',
        'content',
        'image_article',
        'image_post',
        'cta_label',
        'cta_target_type',
        'cta_target_id',
    ];

    /**
     * The project, unit or article the button at the end of this one points at.
     */
    public function ctaTarget(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A button is only shown while its destination still exists — a deleted
     * project takes its button with it rather than leaving a dead link behind.
     */
    public function hasCta(): bool
    {
        return $this->ctaUrl() !== null;
    }

    public function ctaUrl(): ?string
    {
        return LinkTargets::urlFor($this->ctaTarget);
    }

    /**
     * The button's wording, falling back to what the destination calls itself
     * so the button is never blank.
     */
    public function ctaLabel(): ?string
    {
        return filled($this->cta_label)
            ? $this->cta_label
            : LinkTargets::nameFor($this->ctaTarget);
    }
}
