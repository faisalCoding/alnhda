<?php

namespace App\Console\Commands;

use App\Services\Market\CatalogueSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class CheckMarketData extends Command
{
    protected $signature = 'market:check {--push : Also send what was found to the live panel, which cannot reach the portal itself}';

    protected $description = 'Note which files the open data portal has published';

    public function handle(CatalogueSync $sync): int
    {
        try {
            $catalogue = $sync->gather();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $result = $sync->apply($catalogue);

        $this->info(sprintf(
            'المنشور على المنصة %d ربعًا من الصفقات، منها %d لم يُرفع هنا، و%d من ملفات المؤشرات لم يُرفع.',
            $result['published'],
            $result['waiting'],
            $result['indicators_waiting'],
        ));

        return $this->option('push') ? $this->push($catalogue) : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $catalogue
     */
    private function push(array $catalogue): int
    {
        $url = config('services.market.push_url');
        $token = config('services.market.push_token');

        if (blank($url) || blank($token)) {
            $this->error('لإرسال الفهرس إلى الخادم ضع MARKET_PUSH_URL و MARKET_PUSH_TOKEN في ملف .env. الرمز يصدره الأمر market:token على الخادم.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(30)->acceptJson()->withToken($token)->post($url, $catalogue);
        } catch (Throwable $e) {
            $this->error('تعذّر الوصول إلى الخادم: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error(sprintf('الخادم رفض الفهرس (الحالة %d): %s', $response->status(), $response->json('message') ?? ''));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'أُرسل الفهرس إلى الخادم: %d ربعًا من الصفقات لم يُرفع هناك، و%d من ملفات المؤشرات.',
            $response->json('data.waiting') ?? 0,
            $response->json('data.indicators_waiting') ?? 0,
        ));

        return self::SUCCESS;
    }
}
