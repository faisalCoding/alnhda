<?php

namespace App\Models;

use Database\Factories\ManualMarketUnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A unit someone typed in, rather than one the Ministry of Justice registered.
 *
 * It carries the same things a comparison reads off a deed — a place, a size
 * and a price per metre — plus the kind of property, which a deed does not
 * name, and the project it belongs to so the list stays readable.
 */
class ManualMarketUnit extends Model
{
    /** @use HasFactory<ManualMarketUnitFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'project_name',
        'city',
        'city_key',
        'district',
        'district_key',
        'kind',
        'area',
        'price',
        'price_per_meter',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'area' => 'float',
            'price' => 'integer',
            'price_per_meter' => 'float',
        ];
    }
}
