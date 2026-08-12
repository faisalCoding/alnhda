<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendWhatsappRequest;
use App\Http\Resources\Admin\WhatsappMessageResource;
use App\Jobs\SendLeadWhatsappJob;
use App\Models\Lead;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageRecipient;
use App\Services\WhatsappGateway;
use App\Services\WhatsappServiceProcess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
     * Message history, each with its recipients and their delivery state.
     */
    public function messages(): AnonymousResourceCollection
    {
        return WhatsappMessageResource::collection(
            WhatsappMessage::query()
                // Ordered so the list does not reshuffle between refreshes.
                ->with(['admin', 'recipients' => fn ($query) => $query->orderBy('id')])
                ->latest()
                ->limit(100)
                ->get()
        );
    }

    public function log(WhatsappServiceProcess $process): JsonResponse
    {
        return response()->json([
            'data' => [
                'path' => $process->logPath(),
                'lines' => $process->tailLog(),
                'running' => $process->isRunning(),
            ],
        ]);
    }

    public function start(WhatsappServiceProcess $process): JsonResponse
    {
        $outcome = $process->start();

        if ($outcome === 'unavailable') {
            return response()->json([
                'message' => 'تشغيل الخدمة من المتصفح غير متاح على هذا الخادم — شغّلها يدويًا.',
            ], 409);
        }

        return response()->json([
            'data' => [
                'status' => 'starting',
                'message' => $outcome === 'started'
                    ? 'تم إرسال أمر تشغيل الخدمة، انتظر لحظات...'
                    : 'الخدمة تعمل مسبقًا.',
            ],
        ]);
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

        [$deliverable, $skipped] = $leads->partition(
            fn (Lead $lead) => $this->whatsapp->normalizePhone((string) $lead->phone) !== ''
        );

        $message = WhatsappMessage::query()->create([
            'admin_id' => auth('admin')->id(),
            'body' => $validated['message'],
            'recipients_count' => $deliverable->count(),
            'skipped_count' => $skipped->count(),
        ]);

        foreach ($deliverable as $lead) {
            $recipient = $message->recipients()->create([
                'lead_id' => $lead->id,
                'name' => (string) $lead->name,
                'phone' => (string) $lead->phone,
                'status' => WhatsappMessageRecipient::STATUS_QUEUED,
            ]);

            SendLeadWhatsappJob::dispatch($clientId, $recipient->id);
        }

        return response()->json([
            'data' => [
                'message_id' => $message->id,
                'queued' => $deliverable->count(),
                'skipped' => $skipped->count(),
                'message' => 'تمت جدولة '.$deliverable->count().' رسالة للإرسال.',
            ],
        ]);
    }

    private function clientId(): string
    {
        return $this->whatsapp->clientIdFor(auth('admin')->user());
    }
}
