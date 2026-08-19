<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvertisingLicence extends Model
{
    /** @use HasFactory<\Database\Factories\AdvertisingLicenceFactory> */
    use HasFactory;

    protected $fillable = [
        'properties_id',
        'unit_name',
        'licence_number',
        'expires_on',
        'note',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Properties, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Properties::class, 'properties_id');
    }

    /**
     * The unit on file when there is one, otherwise whatever was typed. The
     * typed name also carries a licence whose unit is later deleted.
     */
    public function unitLabel(): string
    {
        return $this->unit?->name ?? (string) $this->unit_name;
    }

    /**
     * Days until the licence lapses; negative once it has. Guarded rather than
     * written with ?->, since null * -1 is 0 and would read as "expires today".
     */
    public function daysUntilExpiry(): ?int
    {
        if ($this->expires_on === null) {
            return null;
        }

        return -1 * $this->expires_on->startOfDay()->diffInDays(now()->startOfDay(), false);
    }
}
