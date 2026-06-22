<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PingGoogleSitemap implements ShouldQueue
{
    use Queueable;

    public function __construct() {}

    public function handle(): void
    {
        $sitemapUrl = route('sitemap');

        try {
            Http::timeout(10)->get('https://www.google.com/ping', [
                'sitemap' => $sitemapUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Google sitemap ping failed: '.$e->getMessage());
        }
    }
}
