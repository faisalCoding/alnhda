<?php

namespace App\Models;

use App\Enums\MarketLevel;
use Database\Factories\MarketTypeStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one kind of property sold for in a city or a district during a quarter.
 */
class MarketTypeStat extends Model
{
    /** @use HasFactory<MarketTypeStatFactory> */
    use HasFactory;

    protected $fillable = [
        'market_indicator_file_id',
        'level',
        'city_key',
        'city',
        'district_key',
        'district',
        'property_type',
        'classification',
        'deals_count',
        'total_price',
        'total_area',
        'average_price_per_meter',
        'lowest_price_per_meter',
        'highest_price_per_meter',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => MarketLevel::class,
            'deals_count' => 'integer',
            'total_price' => 'integer',
            'total_area' => 'float',
            'average_price_per_meter' => 'float',
            'lowest_price_per_meter' => 'float',
            'highest_price_per_meter' => 'float',
        ];
    }

    /**
     * @return BelongsTo<MarketIndicatorFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(MarketIndicatorFile::class, 'market_indicator_file_id');
    }
}
