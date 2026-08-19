<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SocialPlatform extends Model
{
    /** @use HasFactory<\Database\Factories\SocialPlatformFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'identifier',
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
     * @return HasMany<SocialPlatformTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(SocialPlatformTask::class)->orderBy('sort_order')->orderBy('id');
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
