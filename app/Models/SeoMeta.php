<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A per-record override for a project, an article or a unit. Blank fields are
 * not blanks — they mean "keep whatever the record itself produces".
 */
class SeoMeta extends Model
{
    /** @use HasFactory<\Database\Factories\SeoMetaFactory> */
    use HasFactory;

    protected $table = 'seo_meta';

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'noindex',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noindex' => 'boolean',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * True when the row carries nothing worth keeping, so saving an emptied
     * form deletes the override instead of leaving a blank one behind.
     */
    public function isEmpty(): bool
    {
        return blank($this->title)
            && blank($this->description)
            && blank($this->image_path)
            && ! $this->noindex;
    }
}
