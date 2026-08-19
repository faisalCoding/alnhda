<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingChecklist extends Model
{
    /** @use HasFactory<\Database\Factories\MarketingChecklistFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<MarketingChecklistItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MarketingChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Copy the chosen marketing methods in as checklist items.
     *
     * @param  list<int>  $methodIds
     */
    public function addMethods(array $methodIds): void
    {
        $existing = $this->items()->pluck('title');

        $methods = MarketingMethod::query()
            ->whereIn('id', $methodIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->reject(fn (MarketingMethod $method): bool => $existing->contains($method->title));

        $this->items()->createMany(
            $methods->map(fn (MarketingMethod $method): array => [
                'title' => $method->title,
                'sort_order' => $method->sort_order,
            ])->all()
        );
    }
}
