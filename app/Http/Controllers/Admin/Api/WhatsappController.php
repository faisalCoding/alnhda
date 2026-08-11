<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendWhatsappRequest;
use App\Jobs\SendLeadWhatsappJob;
use App\Models\Lead;
use App\Services\WhatsappGateway;
use Illuminate\Http\JsonResponse;

class WhatsappController extends Controller
{
    public function __construct(private WhatsappGateway $whatsapp) {}

    public function status(): JsonResponse
    {
        $clientId = $this->clientId();

        return response()->json([
            'data' => ['client_id' => $clientId] + $this->whatsapp->status($clientId),
        ]);
    }

    public function disconnect(): JsonResponse
    {
        $this->whatsapp->disconnect($this->clientId());

        return response()->json(['data' => ['status' => 'starting', 'message' => 'جاري إعادة التهيئة...']]);
    }

    public function reset(): JsonResponse
    {
        $this->whatsapp->reset($this->clientId());

        return response()->json(['data' => ['status' => 'starting', 'message' => 'جاري إعادة التهيئة بالكامل...']]);
    }

    /**
     * Queue one message per lead. `{الاسم}` is replaced with each lead's own
     * name so a bulk send still reads as a personal message.
     */
    public function send(SendWhatsappRequest $request): JsonResponse
    {
        $clientId = $this->clientId();

        if (! $this->whatsapp->isReady($clientId)) {
            return response()->json([
                'message' => 'الواتساب غير مرتبط. افتح صفحة ربط الواتساب وامسح رمز QR أولًا.',
            ], 409);
        }

        $validated = $request->validated();

        $leads = Lead::query()->whereIn('id', $validated['lead_ids'])->get();
        $queued = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            if ($this->whatsapp->normalizePhone((string) $lead->phone) === '') {
                $skipped++;

                continue;
            }

            SendLeadWhatsappJob::dispatch(
                $clientId,
                (string) $lead->phone,
                str_replace('{الاسم}', (string) $lead->name, $validated['message']),
            );

            $queued++;
        }

        return response()->json([
            'data' => [
                'queued' => $queued,
                'skipped' => $skipped,
                'message' => "تمت جدولة {$queued} رسالة للإرسال.",
            ],
        ]);
    }

    private function clientId(): string
    {
        return $this->whatsapp->clientIdFor(auth('admin')->user());
    }
}
