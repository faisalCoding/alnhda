<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'account_id',
        'name',
        'amount',
        'paid_on',
        'expires_on',
        'payment_account',
        'note',
        'sort_order',
    ];

    /**
     * Billing details are treated like the social passwords: encrypted at rest
     * and kept out of every array form, so only the reveal endpoint hands them
     * over.
     *
     * @var list<string>
     */
    protected $hidden = [
        'payment_account',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'payment_account' => 'encrypted',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * What to call this record: the linked account's name when there is one,
     * since the fields are held there rather than copied onto every payment.
     */
    public function displayName(): string
    {
        return $this->account?->name ?? (string) $this->name;
    }

    /**
     * A record with a renewal date is a subscription; one without is a one off
     * payment. Nothing else separates them, so the page derives it rather than
     * asking for a type that could contradict the dates.
     */
    public function isSubscription(): bool
    {
        return $this->expires_on !== null;
    }

    /**
     * Days until renewal; negative once the subscription has lapsed.
     */
    public function daysUntilExpiry(): ?int
    {
        // The null-safe call stops the chain, but the negation still runs, and
        // null * -1 is 0 — which reads as "expires today" on a record that has
        // no expiry at all. The guard has to come first.
        if ($this->expires_on === null) {
            return null;
        }

        return -1 * $this->expires_on->startOfDay()->diffInDays(now()->startOfDay(), false);
    }
}
