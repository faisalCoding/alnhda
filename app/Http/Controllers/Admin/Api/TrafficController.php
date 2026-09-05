<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsDay;
use App\Models\AnalyticsSummary;
use App\Models\ServerLogDay;
use App\Services\Analytics\Ga4Reporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The traffic screen reads rows that were written last night. Nothing here
 * talks to Google or touches a log file: the panel is a reader, and the cost of
 * opening it is one indexed query per table.
 */
class TrafficController extends Controller
{
    /**
     * The ranges the screen offers, in days.
     *
     * @var list<int>
     */
    private const RANGES = [7, 30, 90];

    public function __construct(private readonly Ga4Reporter $reporter) {}

    public function index(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 30);
        $days = in_array($days, self::RANGES, true) ? $days : 30;

        $end = Carbon::yesterday();
        $start = $end->copy()->subDays($days - 1);
        $previousStart = $start->copy()->subDays($days);
        $previousEnd = $start->copy()->subDay();

        $analytics = AnalyticsDay::query()
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        $server = ServerLogDay::query()
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get();

        $previous = AnalyticsDay::query()->whereBetween('date', [$previousStart, $previousEnd])->get();
        $summary = AnalyticsSummary::query()->where('period', AnalyticsSummary::CURRENT_PERIOD)->first();

        return response()->json([
            'data' => [
                'today' => $this->today(),
                'range' => ['days' => $days, 'from' => $start->toDateString(), 'to' => $end->toDateString()],
                'ranges' => self::RANGES,
                'google' => [
                    'configured' => $this->reporter->isConfigured(),
                    'problem' => $this->reporter->configurationProblem(),
                    'pulled_at' => $summary?->pulled_at?->toISOString(),
                    'has_data' => $analytics->isNotEmpty(),
                    'totals' => [
                        'users' => (int) $analytics->sum('users'),
                        'sessions' => (int) $analytics->sum('sessions'),
                        'views' => (int) $analytics->sum('views'),
                    ],
                    'previous_totals' => [
                        'users' => (int) $previous->sum('users'),
                        'sessions' => (int) $previous->sum('sessions'),
                        'views' => (int) $previous->sum('views'),
                    ],
                    'top_pages' => $summary?->top_pages ?? [],
                    'channels' => $summary?->channels ?? [],
                    'devices' => $summary?->devices ?? [],
                    'cities' => $summary?->cities ?? [],
                ],
                'server' => [
                    'has_data' => $server->isNotEmpty(),
                    'totals' => [
                        'requests' => (int) $server->sum('requests'),
                        'human_requests' => (int) $server->sum(fn (ServerLogDay $day): int => $day->humanRequests()),
                        'bot_requests' => (int) $server->sum('bot_requests'),
                        'bytes' => (int) $server->sum('bytes'),
                        'errors' => (int) $server->sum('status_4xx') + (int) $server->sum('status_5xx'),
                    ],
                    'top_bots' => $this->merge($server->pluck('top_bots')->all()),
                    'not_found' => $this->merge($server->pluck('not_found')->all()),
                ],
                // One row per day across both sources, so the screen draws a
                // single timeline instead of stitching two.
                'days' => $this->timeline($start, $end, $analytics, $server),
            ],
        ]);
    }

    /**
     * The day still running, kept apart from the ranges above it: a partial day
     * added to a run of finished ones reads as a collapse in traffic every
     * morning.
     *
     * @return array<string, mixed>
     */
    private function today(): array
    {
        $today = Carbon::today();

        $visits = AnalyticsDay::query()->whereDate('date', $today)->first();
        $log = ServerLogDay::query()->whereDate('date', $today)->first();

        return [
            'date' => $today->toDateString(),
            'has_data' => $visits !== null || $log !== null,
            'updated_at' => collect([$visits?->updated_at, $log?->updated_at])->filter()->max()?->toISOString(),
            'users' => (int) ($visits?->users ?? 0),
            'sessions' => (int) ($visits?->sessions ?? 0),
            'views' => (int) ($visits?->views ?? 0),
            'requests' => (int) ($log?->requests ?? 0),
            'human_requests' => (int) ($log?->humanRequests() ?? 0),
            'bot_requests' => (int) ($log?->bot_requests ?? 0),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AnalyticsDay>  $analytics
     * @param  \Illuminate\Support\Collection<int, ServerLogDay>  $server
     * @return list<array<string, mixed>>
     */
    private function timeline(Carbon $start, Carbon $end, $analytics, $server): array
    {
        $byDate = $analytics->keyBy(fn (AnalyticsDay $day): string => $day->date->toDateString());
        $serverByDate = $server->keyBy(fn (ServerLogDay $day): string => $day->date->toDateString());

        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $visit = $byDate->get($key);
            $log = $serverByDate->get($key);

            $days[] = [
                'date' => $key,
                'users' => (int) ($visit?->users ?? 0),
                'sessions' => (int) ($visit?->sessions ?? 0),
                'views' => (int) ($visit?->views ?? 0),
                'requests' => (int) ($log?->requests ?? 0),
                'bot_requests' => (int) ($log?->bot_requests ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Adds up the same label across several days.
     *
     * @param  array<int, array<int, array{label: string, value: int}>|null>  $lists
     * @return list<array{label: string, value: int}>
     */
    private function merge(array $lists): array
    {
        $totals = [];

        foreach ($lists as $list) {
            foreach ($list ?? [] as $entry) {
                $label = (string) ($entry['label'] ?? '');

                if ($label === '') {
                    continue;
                }

                $totals[$label] = ($totals[$label] ?? 0) + (int) ($entry['value'] ?? 0);
            }
        }

        arsort($totals);

        return collect($totals)
            ->take(10)
            ->map(fn (int $value, string $label): array => ['label' => $label, 'value' => $value])
            ->values()
            ->all();
    }
}
