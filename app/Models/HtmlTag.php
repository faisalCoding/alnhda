<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HtmlTag extends Model
{

    //
    protected $fillable = [
        'tag_name',
        'classes',
        'order_by',
        'tag_attributes',
        'blog_id',
        'html_tag_id',
        'parent_type'
    ];

    public $innerHTML = '';
    public $content_init = '';

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }

    public function childTags()
    {
        return $this->hasMany(HtmlTag::class);
    }

    public function content()
    {
        return $this->hasOne(HtmlTagText::class);
    }

    public function getInnerAttribute($val)
    {

        if (!$this->content()->get()->isEmpty()) {
            $this->innerHTML .= $this->content()->first()->content;
        }

        if (!$this->childTags()->get()->isEmpty()) {
            foreach ($this->childTags()->get() as  $html_tag) {
                $this->innerHTML .= $html_tag->inner;
            }
        }
        $this->content_init = "<$this->tag_name class='px-10 order-$this->order_by $this->classes' $this->tag_attributes>
                                    $this->innerHTML
                               </$this->tag_name>";

        return $this->content_init;
    }
}
