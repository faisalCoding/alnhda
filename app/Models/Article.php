<?php

namespace App\Models;

use App\Jobs\PingGoogleSitemap;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'image_article',
        'image_post',
    ];

    protected static function booted(): void
    {
        static::created(function (): void {
            PingGoogleSitemap::dispatch();
        });
    }
}
