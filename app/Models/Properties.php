<?php

namespace App\Models;

use App\Models\Concerns\HasSeoMeta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Properties extends Model
{
    use HasFactory, HasSeoMeta;

    protected $fillable = [
        'name',
        'project_id',
        'price',
        'offer',
        'status',
        'rooms',
        'bathrooms',
        'living_rooms',
        'mainds_room',
        'area',
        'land_area',
        'doors',
        'type',
        'parkings',
        'driver_room',
        'facade',
        'furniture',
        'properties_image',
        'unit_youtube',
        'stages_building_youtube',
        'pdf_path',
        'market_type',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The market units chosen by hand as this unit's competitors.
     *
     * @return BelongsToMany<ManualMarketUnit, $this>
     */
    public function competitors(): BelongsToMany
    {
        return $this->belongsToMany(ManualMarketUnit::class, 'property_competitors', 'properties_id', 'manual_market_unit_id')
            ->withPivot('note')
            ->withTimestamps();
    }

    public function propertiesImages()
    {
        return $this->hasMany(ImageProperties::class);
    }
}
