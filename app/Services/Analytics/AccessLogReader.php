<?php

namespace App\Services\Analytics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Summarises a day out of the web server's own access log.
 *
 * This is the half of the traffic analytics cannot see: crawlers, visitors who
 * block tracking, requests that ended in an error, and what all of it cost in
 * bandwidth. Apache writes the file whether or not anybody reads it, so the
 * only cost is one pass over yesterday's lines, once, at night.
 *
 * The file is read line by line rather than loaded: a busy month of logs is
 * larger than the memory this server can spare.
 */
class AccessLogReader
{
    /**
     * Combined log format, which is what Apache writes by default:
     * host - - [date] "METHOD path protocol" status bytes "referer" "agent"
     */
    private const LINE = '/^(?P<host>\S+) \S+ \S+ \[(?P<time>[^\]]+)\] "(?P<method>[A-Z]+) (?P<path>[^" ]*)[^"]*" (?P<status>\d{3}) (?P<bytes>\S+)(?: "(?P<referer>[^"]*)" "(?P<agent>[^"]*)")?/';

    /**
     * Substrings that mark a request as a crawler rather than a person. Kept
     * short on purpose: the point is to separate the obvious robots, not to
     * win an arms race with the ones that hide.
     *
     * @var list<string>
     */
    private const BOT_MARKERS = [
        'bot', 'crawler', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit',
        'whatsapp', 'telegram', 'embedly', 'quora link preview', 'pinterest',
        'semrush', 'ahrefs', 'mj12', 'dotbot', 'petalbot', 'gptbot', 'claudebot',
        'ccbot', 'perplexitybot', 'chatgpt-user', 'headlesschrome', 'python-requests',
        'curl/', 'wget/', 'go-http-client', 'lighthouse',
    ];

    /**
     * Paths that say nothing about what people read.
     *
     * @var list<string>
     */
    private const IGNORED_PREFIXES = ['/build/', '/img/', '/fonts/', '/storage/', '/favicon', '/robots.txt'];

    /**
     * @return array<string, mixed>|null Null when the log holds nothing for that day.
     */
    public function summarise(string $path, Carbon $date): ?array
    {
        // Rotated days arrive gzipped. gzopen reads a plain file too, so one
        // path covers both and the caller never has to care which it holds.
        $handle = @gzopen($path, 'r');

        if ($handle === false) {
            return null;
        }

        $stamp = $date->format('d/M/Y');

        $requests = 0;
        $bytes = 0;
        $bots = 0;
        $addresses = [];
        $status = ['2' => 0, '3' => 0, '4' => 0, '5' => 0];
        $paths = [];
        $botNames = [];
        $missing = [];

        while (($line = gzgets($handle)) !== false) {
            // Cheap rejection before the regex: most lines belong to other days.
            if (! str_contains($line, $stamp)) {
                continue;
            }

            if (! preg_match(self::LINE, $line, $matches)) {
                continue;
            }

            $requests++;
            $bytes += is_numeric($matches['bytes']) ? (int) $matches['bytes'] : 0;
            $addresses[$matches['host']] = true;

            $class = substr($matches['status'], 0, 1);

            if (isset($status[$class])) {
                $status[$class]++;
            }

            $agent = $matches['agent'] ?? '';
            $bot = $this->botName($agent);

            if ($bot !== null) {
                $bots++;
                $botNames[$bot] = ($botNames[$bot] ?? 0) + 1;
            }

            $requestPath = strtok($matches['path'], '?') ?: $matches['path'];

            if ($matches['status'] === '404') {
                $missing[$requestPath] = ($missing[$requestPath] ?? 0) + 1;
            }

            if ($bot === null && $class === '2' && ! $this->isIgnored($requestPath)) {
                $paths[$requestPath] = ($paths[$requestPath] ?? 0) + 1;
            }
        }

        gzclose($handle);

        if ($requests === 0) {
            return null;
        }

        return [
            'date' => $date->toDateString(),
            'requests' => $requests,
            'unique_addresses' => count($addresses),
            'bytes' => $bytes,
            'bot_requests' => $bots,
            'status_2xx' => $status['2'],
            'status_3xx' => $status['3'],
            'status_4xx' => $status['4'],
            'status_5xx' => $status['5'],
            'top_paths' => $this->rank($paths),
            'top_bots' => $this->rank($botNames),
            'not_found' => $this->rank($missing),
        ];
    }

    /**
     * The crawler behind a user agent, or null when it reads like a person.
     */
    public function botName(string $agent): ?string
    {
        $lower = Str::lower($agent);

        foreach (self::BOT_MARKERS as $marker) {
            if (str_contains($lower, $marker)) {
                return $this->prettyBotName($lower, $marker);
            }
        }

        return null;
    }

    /**
     * Names the well-known crawlers, so a screen shows «Googlebot» rather than
     * eighty characters of version string.
     */
    private function prettyBotName(string $agent, string $marker): string
    {
        $known = [
            'googlebot' => 'Googlebot',
            'google-inspectiontool' => 'Google Inspection',
            'bingbot' => 'Bingbot',
            'yandexbot' => 'YandexBot',
            'duckduckbot' => 'DuckDuckBot',
            'facebookexternalhit' => 'Facebook',
            'whatsapp' => 'WhatsApp',
            'telegram' => 'Telegram',
            'gptbot' => 'GPTBot',
            'claudebot' => 'ClaudeBot',
            'perplexitybot' => 'PerplexityBot',
            'ccbot' => 'CCBot',
            'ahrefs' => 'AhrefsBot',
            'semrush' => 'SemrushBot',
            'petalbot' => 'PetalBot',
            'applebot' => 'Applebot',
        ];

        foreach ($known as $needle => $label) {
            if (str_contains($agent, $needle)) {
                return $label;
            }
        }

        return Str::title($marker);
    }

    private function isIgnored(string $path): bool
    {
        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{label: string, value: int}>
     */
    private function rank(array $counts, int $limit = 10): array
    {
        arsort($counts);

        return collect($counts)
            ->take($limit)
            ->map(fn (int $value, string $label): array => ['label' => $label, 'value' => $value])
            ->values()
            ->all();
    }
}
