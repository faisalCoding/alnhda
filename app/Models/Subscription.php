<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'identifier',
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
            'payment_account' => 'encrypted',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Days until renewal; negative once the subscription has lapsed.
     */
    public function daysUntilExpiry(): ?int
    {
        return $this->expires_on?->startOfDay()->diffInDays(now()->startOfDay(), false) * -1;
    }
}
