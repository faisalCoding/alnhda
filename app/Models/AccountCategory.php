<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountCategory extends Model
{
    /** @use HasFactory<\Database\Factories\AccountCategoryFactory> */
    use HasFactory;

    /**
     * A fixed palette rather than a free colour field. Every entry is picked to
     * stay legible against both the light and the dark panel, which an
     * arbitrary hex value cannot promise.
     *
     * @var list<string>
     */
    public const COLORS = ['emerald', 'sky', 'violet', 'amber', 'rose', 'teal', 'indigo', 'zinc'];

    protected $fillable = [
        'name',
        'color',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }
}
