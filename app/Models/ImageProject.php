
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageProject extends Model
{
    //
    protected $fillable = [
        'url',
        'order_by',
        'project_id',     
    ];

    public function project(){
        return $this->belongto(Project::class);
    }
}
