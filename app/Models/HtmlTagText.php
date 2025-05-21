<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HtmlTagText extends Model
{
    protected $fillable = ['content'];

    public function htmlTag(){
      return  $this->belongsTo(HtmlTag::class);
    }
}
