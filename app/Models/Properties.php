<?php

namespace App\Models;

use App\Models\Concerns\HasSeoMeta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function propertiesImages()
    {
        return $this->hasMany(ImageProperties::class);
    }
}
