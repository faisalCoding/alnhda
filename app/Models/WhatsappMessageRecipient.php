<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappMessageRecipient extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappMessageRecipientFactory> */
    use HasFactory;

    /** Waiting in the queue, not handed to WhatsApp yet. */
    public const STATUS_QUEUED = 'queued';

    /** Accepted by WhatsApp, delivery to the device not confirmed yet. */
    public const STATUS_SENT = 'sent';

    /** WhatsApp confirmed the message reached the recipient's device. */
    public const STATUS_DELIVERED = 'delivered';

    /** The recipient opened it. */
    public const STATUS_READ = 'read';

    /** Sending failed, or WhatsApp reported an error acknowledgement. */
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'whatsapp_message_id',
        'lead_id',
        'name',
        'phone',
        'status',
        'provider_message_id',
        'error',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WhatsappMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Maps a whatsapp-web.js acknowledgement level onto a status.
     * -1 error, 0 pending, 1 sent to server, 2 delivered to device, 3 read, 4 played.
     */
    public function applyAcknowledgement(int $ack): bool
    {
        $status = match (true) {
            $ack < 0 => self::STATUS_FAILED,
            $ack >= 3 => self::STATUS_READ,
            $ack === 2 => self::STATUS_DELIVERED,
            default => self::STATUS_SENT,
        };

        // Acknowledgements can arrive out of order; never walk a status backwards.
        if ($this->rank($status) <= $this->rank($this->status) && $status !== self::STATUS_FAILED) {
            return false;
        }

        $this->status = $status;

        if ($status === self::STATUS_FAILED) {
            $this->error ??= 'رفض واتساب تسليم الرسالة.';
        }

        if ($status === self::STATUS_DELIVERED || $status === self::STATUS_READ) {
            $this->delivered_at ??= now();
        }

        if ($status === self::STATUS_READ) {
            $this->read_at ??= now();
        }

        return $this->save();
    }

    private function rank(?string $status): int
    {
        return match ($status) {
            self::STATUS_READ => 4,
            self::STATUS_DELIVERED => 3,
            self::STATUS_SENT => 2,
            self::STATUS_FAILED => 1,
            default => 0,
        };
    }
}
