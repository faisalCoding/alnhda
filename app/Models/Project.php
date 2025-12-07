<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    ];

    public function properties(){
        return $this->hasMany(Properties::class);
    }

    public function projectImage() {
        return $this->hasOne(ImageProject::class);
    }
}

