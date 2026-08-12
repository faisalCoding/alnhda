<?php

namespace App\Console\Commands;

use App\Services\WhatsappGateway;
use App\Services\WhatsappServiceProcess;
use Illuminate\Console\Command;

/**
 * Checks every link in the chain between the panel and a scannable QR, in the
 * order they fail. Written for servers, where "the QR never appears" is almost
 * always Chromium missing its shared libraries rather than anything in Laravel.
 */
class WhatsappDoctor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:doctor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose why the WhatsApp gateway is not producing a QR';

    public function handle(WhatsappServiceProcess $process, WhatsappGateway $gateway): int
    {
        $rows = [];
        $failed = 0;

        $check = function (string $name, bool $ok, string $detail, string $hint = '') use (&$rows, &$failed): bool {
            $rows[] = [$ok ? '✓' : '✗', $name, $ok ? $detail : trim($detail.' — '.$hint, ' —')];

            if (! $ok) {
                $failed++;
            }

            return $ok;
        };

        $node = $process->nodeVersion();
        $check('Node.js', $node !== null, $node ?? 'غير موجود', 'ثبّت Node 18+ على الخادم');

        $check(
            'حزم الخدمة',
            $process->isInstalled(),
            $process->isInstalled() ? 'مثبتة' : 'ناقصة',
            'npm install --prefix whatsapp-service'
        );

        $browser = $process->browserCheck();
        $browserOk = $check(
            'متصفح Chromium',
            str_starts_with($browser, 'ok: '),
            str_replace('ok: ', '', $browser),
            'ينقص المتصفحَ مكتباتُ النظام — انظر الإصلاح المقترح أسفل الجدول'
        );

        $key = (string) config('services.whatsapp.key');
        $check('المفتاح المشترك', $key !== '', $key === '' ? 'غير معرّف' : strlen($key).' محرفًا', 'عرّف WHATSAPP_SERVICE_KEY في .env');

        $running = $process->isRunning();
        $check(
            'الخدمة تستمع',
            $running,
            (string) config('services.whatsapp.url'),
            'php artisan whatsapp:start'
        );

        if ($running) {
            // health() deliberately, not status(): probing with a client id would
            // boot a whole browser and persist a junk session on the server.
            $health = $gateway->health();

            $check(
                'استجابة البوابة',
                $health['ok'],
                $health['ok'] ? 'سليمة' : ($health['message'] ?? 'لا تستجيب'),
                'راجع السجل أدناه'
            );

            if ($health['ok']) {
                $active = collect($health['active_sessions'] ?? [])
                    ->map(fn (array $s) => $s['client_id'].' ('.$s['status'].')')
                    ->implode('، ');

                $saved = implode('، ', $health['saved_sessions'] ?? []);

                $rows[] = ['•', 'جلسات نشطة', $active !== '' ? $active : 'لا شيء'];
                $rows[] = ['•', 'جلسات محفوظة', $saved !== '' ? $saved : 'لا شيء'];
            }
        }

        $this->table(['', 'الفحص', 'النتيجة'], $rows);

        if (! $browserOk) {
            $this->browserFixHint();
        }

        $lines = $process->tailLog(15);

        if ($lines !== []) {
            $this->newLine();
            $this->line('<comment>آخر أسطر السجل:</comment>');

            foreach ($lines as $line) {
                $this->line('  '.$line);
            }
        }

        $this->newLine();

        if ($failed === 0) {
            $this->info('كل الفحوص سليمة. افتح صفحة ربط الواتساب وانتظر ظهور رمز QR.');

            return self::SUCCESS;
        }

        $this->error("فشل {$failed} فحص — عالج الأول في القائمة ثم أعد التشغيل.");

        return self::FAILURE;
    }

    /**
     * Package names for these libraries differ between distro releases (Ubuntu
     * 24.04 renamed them to the t64 variants), so rather than a list that breaks
     * on half the servers, let apt resolve Chrome's own dependencies.
     */
    private function browserFixHint(): void
    {
        $this->newLine();
        $this->line('<comment>الإصلاح المقترح (دبيان/أوبنتو) — يترك apt يجلب كل المكتبات المطلوبة:</comment>');
        $this->newLine();
        $this->line('  wget -q https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb');
        $this->line('  sudo apt-get update && sudo apt-get install -y ./google-chrome-stable_current_amd64.deb');
        $this->line('  rm google-chrome-stable_current_amd64.deb');
        $this->newLine();
        $this->line('  ثم أعد تشغيل الخدمة: php artisan whatsapp:stop && php artisan whatsapp:start');
    }
}
