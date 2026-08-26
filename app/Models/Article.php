<?php

namespace App\Models;

use App\Models\Concerns\HasSeoMeta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory, HasSeoMeta;

    protected $fillable = [
        'title',
        'content',
        'image_article',
        'image_post',
    ];
}
