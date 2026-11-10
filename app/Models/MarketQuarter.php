<?php

namespace App\Models;

use Database\Factories\MarketQuarterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A quarter of sale deeds published by the Ministry of Justice.
 */
class MarketQuarter extends Model
{
    /** @use HasFactory<MarketQuarterFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'quarter',
        'dataset_id',
        'source_name',
        'deals_count',
        'skipped_rows',
        'imported_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quarter' => 'integer',
            'deals_count' => 'integer',
            'skipped_rows' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MarketPriceStat, $this>
     */
    public function stats(): HasMany
    {
        return $this->hasMany(MarketPriceStat::class);
    }

    /**
     * @param  Builder<MarketQuarter>  $query
     */
    public function scopeImported(Builder $query): void
    {
        $query->whereNotNull('imported_at');
    }

    /**
     * Oldest first, the way a trend is read.
     *
     * @param  Builder<MarketQuarter>  $query
     */
    public function scopeChronological(Builder $query): void
    {
        $query->orderBy('year')->orderBy('quarter');
    }

    public function isImported(): bool
    {
        return $this->imported_at !== null;
    }

    /**
     * The form a quarter takes in a query string: «2026-1».
     */
    public function key(): string
    {
        return $this->year.'-'.$this->quarter;
    }

    /**
     * Where a person downloads this quarter's file on the open data portal.
     */
    public function portalUrl(): ?string
    {
        return $this->dataset_id === null
            ? null
            : 'https://open.data.gov.sa/ar/datasets/view/'.$this->dataset_id.'/resources';
    }
}
