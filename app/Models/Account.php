<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    /** @use HasFactory<\Database\Factories\AccountFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'identifier',
        'url',
        'password',
        'sort_order',
    ];

    /**
     * The password is deliberately hidden from array and JSON output. It is
     * only ever handed out by the dedicated reveal endpoint, so an accidental
     * toArray() somewhere can never leak it into a list response or a log.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<AccountCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(AccountCategory::class)->orderBy('sort_order')->orderBy('account_categories.id');
    }

    /**
     * @return HasMany<AccountTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(AccountTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Copy the current template checklist onto this platform.
     */
    public function applyTaskTemplates(): void
    {
        $templates = TaskTemplate::query()->orderBy('sort_order')->orderBy('id')->get();

        $this->tasks()->createMany(
            $templates->map(fn (TaskTemplate $template): array => [
                'title' => $template->title,
                'sort_order' => $template->sort_order,
            ])->all()
        );
    }
}
