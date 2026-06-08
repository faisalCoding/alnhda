<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
        'project_type',
        'location',
        'image_url',
        'map_url',
        'pdf_path',
        'guarantees',
    ];

    protected $casts = [
        'guarantees' => 'array',
    ];

    public function properties()
    {
        return $this->hasMany(Properties::class);
    }

    public function projectImage()
    {
        return $this->hasOne(ImageProject::class);
    }
}
