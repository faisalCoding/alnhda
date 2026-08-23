<?php

namespace App\Console\Commands;

use App\Services\WhatsappGateway;
use Illuminate\Console\Command;

/**
 * Prints the shape of WhatsApp's own IndexedDB on the linked session. Recovering
 * a message id, and reading its acknowledgement, both go through the injected
 * model layer that breaks on some builds — the database underneath does not.
 * Its field names differ between builds, so this reads them before code depends
 * on them, rather than guessing and failing silently a second time.
 */
class WhatsappStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:storage
                            {--client= : Session id (defaults to the only active one)}
                            {--store=message : Object store to read}
                            {--limit=3 : Rows to print}
                            {--scan=400 : Rows to walk before choosing}
                            {--id= : Only rows whose message id contains this}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect WhatsApp\'s IndexedDB on the linked session (no message content)';

    public function handle(WhatsappGateway $gateway): int
    {
        $clientId = $this->resolveClient($gateway);

        if ($clientId === null) {
            return self::FAILURE;
        }

        $this->line('الجلسة: '.$clientId);

        $result = $gateway->storage($clientId, [
            'store' => (string) $this->option('store'),
            'limit' => (string) $this->option('limit'),
            'scan' => (string) $this->option('scan'),
            'id' => (string) $this->option('id'),
        ]);

        if (! $result['ok']) {
            $this->error($result['error'] ?? 'تعذر فحص القاعدة.');

            return self::FAILURE;
        }

        $report = $result['report'];

        $this->newLine();
        $this->line('<comment>القواعد:</comment> '.$this->listOf($report['databases'] ?? null));
        $this->line('<comment>المخازن:</comment> '.$this->listOf($report['stores'] ?? null));

        if (($report['meta'] ?? null) !== null) {
            $this->line('<comment>المفتاح:</comment> '.json_encode($report['meta']['keyPath'] ?? null, JSON_UNESCAPED_UNICODE));
            $this->line('<comment>الفهارس:</comment> '.$this->listOf($report['meta']['indexes'] ?? null));
        }

        $this->line('<comment>عدد الصفوف:</comment> '.($report['count'] ?? 'غير معروف').' (مُر على '.($report['scanned'] ?? 0).')');

        foreach ($report['notes'] ?? [] as $note) {
            $this->warn('• '.$note);
        }

        $rows = $report['rows'] ?? [];

        if ($rows === []) {
            $this->newLine();
            $this->warn('لا صفوف لعرضها.');

            return self::SUCCESS;
        }

        foreach ($rows as $index => $row) {
            $this->newLine();
            $this->line('<comment>── صف '.($index + 1).' — المفتاح:</comment> '.json_encode($row['key'] ?? null, JSON_UNESCAPED_UNICODE));
            $this->line(json_encode($row['fields'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->newLine();
        $this->info('القيم المعروضة تعريفية فقط — المحتوى محجوب إلى نوعه وحجمه.');

        return self::SUCCESS;
    }

    /**
     * A wrong session id reports "not ready", which reads like a broken link
     * rather than a typo — so the active session is looked up instead of asked
     * for, and only an ambiguous answer is put back to the operator.
     */
    private function resolveClient(WhatsappGateway $gateway): ?string
    {
        $given = (string) $this->option('client');

        if ($given !== '') {
            return $given;
        }

        $health = $gateway->health();

        if (! ($health['ok'] ?? false)) {
            $this->error('الخدمة لا تستجيب: '.($health['message'] ?? 'سبب غير معروف').' — شغّل: php artisan whatsapp:start');

            return null;
        }

        $ready = array_values(array_filter(
            $health['active_sessions'] ?? [],
            fn (array $session) => ($session['status'] ?? '') === 'ready'
        ));

        if ($ready === []) {
            $this->error('لا توجد جلسة مرتبطة — افتح صفحة الواتساب في اللوحة واربط الجهاز أولاً.');

            return null;
        }

        if (count($ready) > 1) {
            $this->error('أكثر من جلسة مرتبطة، حدّد واحدة بـ --client: '.implode('، ', array_column($ready, 'client_id')));

            return null;
        }

        return (string) $ready[0]['client_id'];
    }

    /**
     * @param  list<string>|null  $values
     */
    private function listOf(?array $values): string
    {
        return $values === null || $values === [] ? 'غير متاح' : implode('، ', $values);
    }
}
