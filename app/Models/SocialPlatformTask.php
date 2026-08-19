<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPlatformTask extends Model
{
    /** @use HasFactory<\Database\Factories\SocialPlatformTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'social_platform_id',
        'title',
        'is_done',
        'completed_at',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SocialPlatform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(SocialPlatform::class, 'social_platform_id');
    }
}
