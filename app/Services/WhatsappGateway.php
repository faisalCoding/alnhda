<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the local Node service (whatsapp-service) that drives
 * whatsapp-web.js. Every admin links their own phone, so calls are scoped by a
 * per-admin session id.
 */
class WhatsappGateway
{
    /**
     * Session id the Node service stores the linked device under.
     */
    public function clientIdFor(Admin $admin): string
    {
        return 'admin_'.$admin->id;
    }

    /**
     * Saudi numbers are entered in many shapes (05…, 5…, +966…, with spaces).
     * WhatsApp needs bare international digits.
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            return '966'.substr($digits, 1);
        }

        if (str_starts_with($digits, '5')) {
            return '966'.$digits;
        }

        return $digits;
    }

    /**
     * @return array{status: string, message?: string, qr_image?: string}
     */
    public function status(string $clientId): array
    {
        try {
            $response = $this->client(3)->get("{$this->baseUrl()}/status/{$clientId}");

            if (! $response->successful()) {
                return ['status' => 'error', 'message' => 'لا يمكن الاتصال بخدمة الواتساب.'];
            }

            /** @var array{status?: string, message?: string, qr_image?: string} $data */
            $data = $response->json() ?? [];

            return [
                'status' => $data['status'] ?? 'unknown',
                'message' => $data['message'] ?? '',
                'qr_image' => $data['qr_image'] ?? null,
            ];
        } catch (\Throwable) {
            return ['status' => 'error', 'message' => 'تأكد من تشغيل خدمة الواتساب (Node.js).'];
        }
    }

    /**
     * Service-level health. Unlike status(), this never creates a session — the
     * doctor must not spawn a browser or leave credentials on disk.
     *
     * @return array{ok: bool, active_sessions?: array<int, array{client_id: string, status: string}>, saved_sessions?: array<int, string>, message?: string}
     */
    public function health(): array
    {
        try {
            $response = $this->client(3)->get($this->baseUrl().'/health');

            if ($response->notFound()) {
                // /health shipped with the current service; a 404 means the
                // running process is an older build that survived the deploy.
                return ['ok' => false, 'outdated' => true, 'message' => 'الخدمة تعمل بنسخة قديمة من الكود'];
            }

            if (! $response->successful()) {
                return ['ok' => false, 'message' => 'الخدمة ردّت بحالة '.$response->status()];
            }

            return ['ok' => true] + ($response->json() ?? []);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function isReady(string $clientId): bool
    {
        return $this->status($clientId)['status'] === 'ready';
    }

    public function disconnect(string $clientId): bool
    {
        return $this->post("/disconnect/{$clientId}");
    }

    public function reset(string $clientId): bool
    {
        return $this->post("/reset/{$clientId}");
    }

    /**
     * Hand one message to the Node service.
     *
     * @return array{sent: bool, message_id?: string|null, error?: string}
     */
    public function send(string $clientId, string $phone, string $message): array
    {
        $normalized = $this->normalizePhone($phone);

        if ($normalized === '') {
            return ['sent' => false, 'error' => 'رقم هاتف غير صالح.'];
        }

        try {
            $response = $this->client(15)->post("{$this->baseUrl()}/send", [
                'clientId' => $clientId,
                'phone' => $normalized,
                'message' => $message,
            ]);

            if (! $response->successful()) {
                return ['sent' => false, 'error' => (string) ($response->json('message') ?? 'رفضت البوابة الإرسال.')];
            }

            return ['sent' => true, 'message_id' => $response->json('message_id')];
        } catch (\Throwable $e) {
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Current acknowledgement level for messages Laravel has not confirmed yet.
     *
     * @param  array<int, string>  $messageIds
     * @return array<string, int>
     */
    public function acknowledgements(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        try {
            $response = $this->client(10)->post($this->baseUrl().'/acks', ['ids' => array_values($messageIds)]);

            return $response->successful() ? (array) $response->json('acks', []) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function post(string $path): bool
    {
        try {
            return $this->client(5)->post($this->baseUrl().$path)->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function baseUrl(): string
    {
        return (string) config('services.whatsapp.url');
    }

    private function client(int $timeout): PendingRequest
    {
        return Http::withHeaders(['X-Api-Key' => (string) config('services.whatsapp.key')])
            ->timeout($timeout);
    }
}
