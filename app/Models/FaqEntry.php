<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One question and its answer, as an admin wrote them.
 */
class FaqEntry extends Model
{
    /** @use HasFactory<\Database\Factories\FaqEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'question',
        'answer',
        'sort_order',
    ];

    /**
     * The order chosen by dragging in the dashboard, applied wherever the
     * questions are shown.
     *
     * @param  Builder<FaqEntry>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
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
