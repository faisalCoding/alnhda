<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageProperties extends Model
{
    //
    protected $fillable = [
        'url',
        'order_by',
        'properties_id',     
    ];


    public function properties(){
        return $this->belongto(Properties::class);
    }

}
