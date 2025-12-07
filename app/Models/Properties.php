<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Properties extends Model
{
    //
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
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }
    
    public function propertiesImages() {
        return $this->hasMany(ImageProperties::class);
    }
}
