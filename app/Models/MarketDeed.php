<?php

namespace App\Models;

use Database\Factories\MarketDeedFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sale as the Ministry of Justice registered it, kept for the cities the
 * company works in.
 */
class MarketDeed extends Model
{
    /** @use HasFactory<MarketDeedFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'market_quarter_id',
        'city_key',
        'city',
        'district_key',
        'district',
        'classification',
        'deed_date',
        'price',
        'area',
        'price_per_meter',
        'properties_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deed_date' => 'date',
            'price' => 'integer',
            'area' => 'float',
            'price_per_meter' => 'float',
            'properties_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MarketQuarter, $this>
     */
    public function quarter(): BelongsTo
    {
        return $this->belongsTo(MarketQuarter::class, 'market_quarter_id');
    }
}
