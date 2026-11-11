<?php

namespace App\Models;

use App\Enums\MarketLevel;
use Database\Factories\MarketPriceStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one place traded at during one quarter.
 */
class MarketPriceStat extends Model
{
    /** @use HasFactory<MarketPriceStatFactory> */
    use HasFactory;

    /**
     * The classification stored on the row that covers all of them together.
     */
    public const ALL_CLASSIFICATIONS = '';

    /**
     * The area band stored on the row that covers every size together.
     */
    public const ALL_AREAS = '';

    protected $fillable = [
        'market_quarter_id',
        'level',
        'region_key',
        'region',
        'city_key',
        'city',
        'district_key',
        'district',
        'classification',
        'area_band',
        'deals_count',
        'properties_count',
        'total_price',
        'total_area',
        'median_price_per_meter',
        'lower_price_per_meter',
        'upper_price_per_meter',
        'median_price',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => MarketLevel::class,
            'deals_count' => 'integer',
            'properties_count' => 'integer',
            'total_price' => 'integer',
            'total_area' => 'float',
            'median_price_per_meter' => 'float',
            'lower_price_per_meter' => 'float',
            'upper_price_per_meter' => 'float',
            'median_price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MarketQuarter, $this>
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(MarketQuarter::class, 'market_quarter_id');
    }

    /**
     * Everything paid over everything sold. A single large plot moves this far
     * more than it moves the median, which is why both are kept.
     */
    public function averagePricePerMeter(): float
    {
        return $this->total_area > 0 ? round($this->total_price / $this->total_area, 2) : 0.0;
    }
}
