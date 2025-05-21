<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{

    use HasFactory;
    //
    public $html_tags = '';

    protected $fillable = [
        'title',
        'image_post'
    ];

    public function htmlTags()
    {
        return $this->hasMany(HtmlTag::class);
    }

    public function getContentAttribute()
    {

        foreach ($this->htmlTags()->get() as $html_tag) {
            $this->html_tags .= $html_tag->inner;
        }

        return $this->html_tags;
    }
}
