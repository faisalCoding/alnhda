<?php

use App\Services\Analytics\AccessLogReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function logLine(string $host, string $date, string $path, int $status, int $bytes, string $agent): string
{
    return sprintf(
        '%s - - [%s:04:11:22 +0300] "GET %s HTTP/1.1" %d %d "-" "%s"',
        $host, $date, $path, $status, $bytes, $agent
    );
}

function writeLog(array $lines): string
{
    $path = tempnam(sys_get_temp_dir(), 'access').'.log';
    file_put_contents($path, implode("\n", $lines)."\n");

    return $path;
}

const HUMAN = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1';

it('summarises one day and leaves the other days alone', function () {
    $path = writeLog([
        logLine('1.1.1.1', '03/Sep/2026', '/', 200, 5000, HUMAN),
        logLine('1.1.1.1', '03/Sep/2026', '/projects', 200, 4000, HUMAN),
        logLine('2.2.2.2', '03/Sep/2026', '/', 200, 5000, HUMAN),
        logLine('9.9.9.9', '02/Sep/2026', '/', 200, 5000, HUMAN),
    ]);

    $summary = app(AccessLogReader::class)->summarise($path, Carbon::parse('2026-09-03'));

    expect($summary['requests'])->toBe(3)
        ->and($summary['unique_addresses'])->toBe(2)
        ->and($summary['bytes'])->toBe(14000)
        ->and($summary['status_2xx'])->toBe(3);

    unlink($path);
});

it('separates crawlers from people and names the ones it knows', function () {
    $path = writeLog([
        logLine('1.1.1.1', '03/Sep/2026', '/', 200, 100, HUMAN),
        logLine('66.249.66.1', '03/Sep/2026', '/', 200, 100, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'),
        logLine('66.249.66.2', '03/Sep/2026', '/projects', 200, 100, 'Mozilla/5.0 (compatible; Googlebot/2.1)'),
        logLine('5.5.5.5', '03/Sep/2026', '/', 200, 100, 'Mozilla/5.0 (compatible; bingbot/2.0)'),
    ]);

    $summary = app(AccessLogReader::class)->summarise($path, Carbon::parse('2026-09-03'));

    expect($summary['bot_requests'])->toBe(3)
        ->and($summary['top_bots'][0])->toBe(['label' => 'Googlebot', 'value' => 2])
        ->and($summary['top_bots'][1])->toBe(['label' => 'Bingbot', 'value' => 1]);

    unlink($path);
});

it('ranks the pages people read, without the assets or the crawlers', function () {
    $path = writeLog([
        logLine('1.1.1.1', '03/Sep/2026', '/projects', 200, 100, HUMAN),
        logLine('2.2.2.2', '03/Sep/2026', '/projects', 200, 100, HUMAN),
        logLine('3.3.3.3', '03/Sep/2026', '/', 200, 100, HUMAN),
        logLine('1.1.1.1', '03/Sep/2026', '/build/assets/app.css', 200, 100, HUMAN),
        logLine('1.1.1.1', '03/Sep/2026', '/img/logo.webp', 200, 100, HUMAN),
        logLine('66.249.66.1', '03/Sep/2026', '/projects', 200, 100, 'Googlebot/2.1'),
    ]);

    $summary = app(AccessLogReader::class)->summarise($path, Carbon::parse('2026-09-03'));

    expect($summary['top_paths'])->toBe([
        ['label' => '/projects', 'value' => 2],
        ['label' => '/', 'value' => 1],
    ]);

    unlink($path);
});

it('collects the addresses that answered 404 so they can be fixed', function () {
    $path = writeLog([
        logLine('1.1.1.1', '03/Sep/2026', '/old-page', 404, 500, HUMAN),
        logLine('2.2.2.2', '03/Sep/2026', '/old-page', 404, 500, HUMAN),
        logLine('3.3.3.3', '03/Sep/2026', '/gone', 404, 500, HUMAN),
        logLine('4.4.4.4', '03/Sep/2026', '/', 500, 0, HUMAN),
    ]);

    $summary = app(AccessLogReader::class)->summarise($path, Carbon::parse('2026-09-03'));

    expect($summary['not_found'][0])->toBe(['label' => '/old-page', 'value' => 2])
        ->and($summary['status_4xx'])->toBe(3)
        ->and($summary['status_5xx'])->toBe(1);

    unlink($path);
});

it('drops the query string so one page is not counted as many', function () {
    $path = writeLog([
        logLine('1.1.1.1', '03/Sep/2026', '/projects?page=1', 200, 100, HUMAN),
        logLine('2.2.2.2', '03/Sep/2026', '/projects?page=2', 200, 100, HUMAN),
    ]);

    $summary = app(AccessLogReader::class)->summarise($path, Carbon::parse('2026-09-03'));

    expect($summary['top_paths'])->toBe([['label' => '/projects', 'value' => 2]]);

    unlink($path);
});

it('says nothing rather than zero when the day is not in the file', function () {
    $path = writeLog([logLine('1.1.1.1', '02/Sep/2026', '/', 200, 100, HUMAN)]);

    expect(app(AccessLogReader::class)->summarise($path, Carbon::parse('2026-09-03')))->toBeNull();

    unlink($path);
});

it('returns nothing for a log it cannot read', function () {
    expect(app(AccessLogReader::class)->summarise('/no/such/access.log', Carbon::parse('2026-09-03')))->toBeNull();
});

it('survives a line that is not in the expected format', function () {
    $path = writeLog([
        'this is not a log line at all 03/Sep/2026',
        logLine('1.1.1.1', '03/Sep/2026', '/', 200, 100, HUMAN),
    ]);

    expect(app(AccessLogReader::class)->summarise($path, Carbon::parse('2026-09-03'))['requests'])->toBe(1);

    unlink($path);
});

it('reads a dash for bytes as nothing sent, not as an error', function () {
    $path = writeLog([
        '1.1.1.1 - - [03/Sep/2026:04:11:22 +0300] "GET / HTTP/1.1" 304 - "-" "'.HUMAN.'"',
    ]);

    $summary = app(AccessLogReader::class)->summarise($path, Carbon::parse('2026-09-03'));

    expect($summary['bytes'])->toBe(0)
        ->and($summary['status_3xx'])->toBe(1);

    unlink($path);
});

it('knows the ai crawlers by name', function (string $agent, string $expected) {
    expect(app(AccessLogReader::class)->botName($agent))->toBe($expected);
})->with([
    'gptbot' => ['Mozilla/5.0 (compatible; GPTBot/1.2; +https://openai.com/gptbot)', 'GPTBot'],
    'claude' => ['Mozilla/5.0 (compatible; ClaudeBot/1.0)', 'ClaudeBot'],
    'perplexity' => ['Mozilla/5.0 (compatible; PerplexityBot/1.0)', 'PerplexityBot'],
]);

it('takes a phone for a person', function () {
    expect(app(AccessLogReader::class)->botName(HUMAN))->toBeNull();
});

it('reads yesterday out of the rotated file when apache has moved it', function () {
    $rotated = writeLog([logLine('1.1.1.1', '03/Sep/2026', '/', 200, 100, HUMAN)]);
    $current = str_replace('.log', '-current.log', $rotated);
    file_put_contents($current, logLine('2.2.2.2', '04/Sep/2026', '/', 200, 100, HUMAN)."\n");
    rename($rotated, $current.'.1');

    config(['services.access_log.path' => $current]);

    $this->artisan('analytics:parse-log', ['--date' => '2026-09-03'])->assertSuccessful();

    expect(App\Models\ServerLogDay::query()->count())->toBe(1)
        ->and(App\Models\ServerLogDay::query()->first()->requests)->toBe(1);

    unlink($current);
    unlink($current.'.1');
})->group('database');

it('writes nothing and complains quietly when the log is missing', function () {
    config(['services.access_log.path' => '/no/such/access.log']);

    $this->artisan('analytics:parse-log')->assertSuccessful();

    expect(App\Models\ServerLogDay::query()->count())->toBe(0);
})->group('database');

it('reads a rotated day straight out of the gzipped archive', function () {
    $plain = writeLog([logLine('1.1.1.1', '20/Aug/2026', '/projects', 200, 700, HUMAN)]);
    $gz = $plain.'.gz';
    file_put_contents('compress.zlib://'.$gz, file_get_contents($plain));
    unlink($plain);

    $summary = app(AccessLogReader::class)->summarise($gz, Carbon::parse('2026-08-20'));

    expect($summary['requests'])->toBe(1)
        ->and($summary['top_paths'][0]['label'])->toBe('/projects');

    unlink($gz);
});

it('walks back through the archive when asked to backfill', function () {
    $base = tempnam(sys_get_temp_dir(), 'backfill').'.log';

    // Today's file, yesterday's rotation, and two gzipped days behind it.
    file_put_contents($base, logLine('1.1.1.1', '04/Sep/2026', '/', 200, 100, HUMAN)."\n");
    file_put_contents($base.'.1', logLine('2.2.2.2', '03/Sep/2026', '/', 200, 100, HUMAN)."\n");
    file_put_contents('compress.zlib://'.$base.'.2.gz', logLine('3.3.3.3', '02/Sep/2026', '/', 200, 100, HUMAN)."\n");
    file_put_contents('compress.zlib://'.$base.'.10.gz', logLine('4.4.4.4', '01/Sep/2026', '/', 200, 100, HUMAN)."\n");

    config(['services.access_log.path' => $base]);
    Carbon::setTestNow('2026-09-05 06:00:00');

    $this->artisan('analytics:parse-log', ['--backfill' => 5])->assertSuccessful();

    expect(App\Models\ServerLogDay::query()->count())->toBe(4)
        ->and(App\Models\ServerLogDay::query()->orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04']);

    Carbon::setTestNow();
    array_map('unlink', [$base, $base.'.1', $base.'.2.gz', $base.'.10.gz']);
})->group('database');

it('reads the archive in the order logrotate numbers it, not alphabetically', function () {
    $base = tempnam(sys_get_temp_dir(), 'order').'.log';

    // .10.gz must not be read before .2.gz, which is what plain text sorting does.
    file_put_contents($base, '');
    file_put_contents('compress.zlib://'.$base.'.2.gz', logLine('1.1.1.1', '03/Sep/2026', '/two', 200, 100, HUMAN)."\n");
    file_put_contents('compress.zlib://'.$base.'.10.gz', logLine('2.2.2.2', '03/Sep/2026', '/ten', 200, 100, HUMAN)."\n");

    config(['services.access_log.path' => $base]);

    $this->artisan('analytics:parse-log', ['--date' => '2026-09-03'])->assertSuccessful();

    expect(App\Models\ServerLogDay::query()->first()->top_paths[0]['label'])->toBe('/two');

    array_map('unlink', [$base, $base.'.2.gz', $base.'.10.gz']);
})->group('database');

it('names an unknown crawler after itself, not after the word that matched it', function (string $agent, string $expected) {
    expect(app(AccessLogReader::class)->botName($agent))->toBe($expected);
})->with([
    'unknown bot' => ['Mozilla/5.0 (compatible; Barkrowler-Bot/2.1; +http://example.com)', 'Barkrowler-Bot'],
    'named crawler' => ['SomeCrawler/1.4 (+http://example.com/crawler)', 'Somecrawler'],
    'bare tool' => ['curl/8.4.0', 'Curl'],
    'known one still wins' => ['Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)', 'SemrushBot'],
]);
