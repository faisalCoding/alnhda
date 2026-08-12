<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappMessage extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'body',
        'recipients_count',
        'skipped_count',
    ];

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * @return HasMany<WhatsappMessageRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappMessageRecipient::class);
    }

    /**
     * Counts per delivery status, for the message list.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        return $this->recipients
            ->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->all();
    }
}
