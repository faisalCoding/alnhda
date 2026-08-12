<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'sort_order',
    ];

    protected $casts = [
        'guarantees' => 'array',
    ];

    /**
     * The order chosen by dragging in the dashboard, applied everywhere projects
     * are listed. New projects default to 0 so they surface at the top until
     * they are placed deliberately.
     *
     * @param  Builder<Project>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderByDesc('id');
    }

    public function properties()
    {
        return $this->hasMany(Properties::class);
    }

    public function projectImage()
    {
        return $this->hasOne(ImageProject::class);
    }
}
