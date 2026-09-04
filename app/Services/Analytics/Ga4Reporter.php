<?php

namespace App\Services\Analytics;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads what Google Analytics already recorded about this site.
 *
 * The access token is minted here rather than through Google's SDK: the whole
 * exchange is one signed assertion and one POST, and the SDK would add tens of
 * megabytes of dependency to a project that needs none of the rest of it.
 */
class Ga4Reporter
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private const API = 'https://analyticsdata.googleapis.com/v1beta';

    /**
     * Google issues tokens for an hour; this expires a little earlier so a
     * request never sets out with a token that dies on the way.
     */
    private const TOKEN_TTL = 3300;

    public function isConfigured(): bool
    {
        $credentials = (string) config('services.ga4.credentials');

        return filled(config('services.ga4.property_id')) && $credentials !== '' && is_readable($credentials);
    }

    /**
     * Why the panel cannot show anything yet, or null when it can.
     */
    public function configurationProblem(): ?string
    {
        if (blank(config('services.ga4.property_id'))) {
            return 'لم يُضبط GA4_PROPERTY_ID في إعدادات الخادم.';
        }

        $credentials = (string) config('services.ga4.credentials');

        if ($credentials === '') {
            return 'لم يُضبط GA4_CREDENTIALS_PATH في إعدادات الخادم.';
        }

        if (! is_readable($credentials)) {
            return 'ملف اعتماد جوجل غير موجود أو غير مقروء على الخادم.';
        }

        return null;
    }

    /**
     * Daily totals, oldest first.
     *
     * @return list<array{date: string, users: int, sessions: int, views: int}>
     */
    public function dailyTotals(Carbon $from, Carbon $to): array
    {
        $rows = $this->runReport([
            'dateRanges' => [['startDate' => $from->toDateString(), 'endDate' => $to->toDateString()]],
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'totalUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]],
        ]);

        return array_map(fn (array $row): array => [
            'date' => Carbon::createFromFormat('Ymd', $row['dimensionValues'][0]['value'])->toDateString(),
            'users' => (int) $row['metricValues'][0]['value'],
            'sessions' => (int) $row['metricValues'][1]['value'],
            'views' => (int) $row['metricValues'][2]['value'],
        ], $rows);
    }

    /**
     * A single dimension ranked by one metric.
     *
     * @return list<array{label: string, value: int}>
     */
    public function breakdown(Carbon $from, Carbon $to, string $dimension, string $metric, int $limit = 10): array
    {
        $rows = $this->runReport([
            'dateRanges' => [['startDate' => $from->toDateString(), 'endDate' => $to->toDateString()]],
            'dimensions' => [['name' => $dimension]],
            'metrics' => [['name' => $metric]],
            'orderBys' => [['metric' => ['metricName' => $metric], 'desc' => true]],
            'limit' => $limit,
        ]);

        return array_map(fn (array $row): array => [
            'label' => (string) $row['dimensionValues'][0]['value'],
            'value' => (int) $row['metricValues'][0]['value'],
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<array<string, mixed>>
     */
    private function runReport(array $body): array
    {
        $property = (string) config('services.ga4.property_id');

        $response = Http::withToken($this->accessToken())
            ->timeout(20)
            ->post(self::API.'/properties/'.$property.':runReport', $body);

        if ($response->failed()) {
            throw new RuntimeException('رفض تحليلات جوجل الطلب: '.$response->status().' '.$response->body());
        }

        return $response->json('rows', []);
    }

    /**
     * A service-account assertion exchanged for a bearer token, cached until
     * shortly before Google would expire it.
     */
    private function accessToken(): string
    {
        return Cache::remember('ga4:access-token', self::TOKEN_TTL, function (): string {
            $key = $this->credentials();
            $now = time();

            $assertion = $this->sign([
                'iss' => $key['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $now,
                'exp' => $now + 3600,
            ], $key['private_key']);

            try {
                $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
            } catch (ConnectionException $e) {
                throw new RuntimeException('تعذّر الوصول إلى جوجل: '.$e->getMessage(), previous: $e);
            }

            if ($response->failed()) {
                throw new RuntimeException('رفضت جوجل بيانات الاعتماد: '.$response->status());
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function sign(array $claims, string $privateKey): string
    {
        $encode = fn (array $part): string => rtrim(strtr(base64_encode(json_encode($part, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');

        $payload = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims);

        if (! openssl_sign($payload, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('تعذّر توقيع طلب جوجل بالمفتاح الموجود.');
        }

        return $payload.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    /**
     * @return array{client_email: string, private_key: string}
     */
    private function credentials(): array
    {
        $path = (string) config('services.ga4.credentials');

        if (! is_readable($path)) {
            throw new RuntimeException('ملف اعتماد جوجل غير موجود على الخادم.');
        }

        $key = json_decode((string) file_get_contents($path), true);

        if (! is_array($key) || ! isset($key['client_email'], $key['private_key'])) {
            throw new RuntimeException('ملف اعتماد جوجل غير صالح — يُتوقع ملف حساب خدمة بصيغة JSON.');
        }

        return ['client_email' => (string) $key['client_email'], 'private_key' => (string) $key['private_key']];
    }
}
