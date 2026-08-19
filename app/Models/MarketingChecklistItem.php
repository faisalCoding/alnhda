<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingChecklistItem extends Model
{
    /** @use HasFactory<\Database\Factories\MarketingChecklistItemFactory> */
    use HasFactory;

    protected $fillable = [
        'marketing_checklist_id',
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
     * @return BelongsTo<MarketingChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(MarketingChecklist::class, 'marketing_checklist_id');
    }
}
