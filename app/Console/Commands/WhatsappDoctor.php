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
        $check(
            'متصفح Chromium',
            str_starts_with($browser, 'ok: '),
            str_replace('ok: ', '', $browser),
            'على لينكس ثبّت مكتبات Chromium: sudo apt install -y libnss3 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2'
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
            $status = $gateway->status('doctor_probe');
            $check(
                'استجابة البوابة',
                $status['status'] !== 'error',
                $status['status'].' — '.($status['message'] ?? ''),
                'راجع السجل أدناه'
            );
        }

        $this->table(['', 'الفحص', 'النتيجة'], $rows);

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
}
