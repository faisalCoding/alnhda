<?php

namespace App\Models;

use Database\Factories\MarketIndicatorFileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A region's quarterly file of sale indicators by property type, published by
 * the Real Estate General Authority.
 */
class MarketIndicatorFile extends Model
{
    /** @use HasFactory<MarketIndicatorFileFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'quarter',
        'region_key',
        'region',
        'dataset_id',
        'download_url',
        'file_updated_at',
        'source_name',
        'deals_count',
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
            'file_updated_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MarketTypeStat, $this>
     */
    public function stats(): HasMany
    {
        return $this->hasMany(MarketTypeStat::class);
    }

    /**
     * @param  Builder<MarketIndicatorFile>  $query
     */
    public function scopeImported(Builder $query): void
    {
        $query->whereNotNull('imported_at');
    }

    /**
     * Oldest first, the way a trend is read.
     *
     * @param  Builder<MarketIndicatorFile>  $query
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
     * The ministry-style quarter key, «2026-1», shared with the deeds.
     */
    public function key(): string
    {
        return $this->year.'-'.$this->quarter;
    }

    /**
     * The authority replaced the file after it was uploaded here.
     */
    public function fileChangedSinceImport(): bool
    {
        return $this->isImported()
            && $this->file_updated_at !== null
            && $this->file_updated_at->greaterThan($this->imported_at);
    }

    /**
     * Where a person downloads this file on the open data portal.
     */
    public function portalUrl(): ?string
    {
        return $this->dataset_id === null
            ? null
            : 'https://open.data.gov.sa/ar/datasets/view/'.$this->dataset_id.'/resources';
    }
}
