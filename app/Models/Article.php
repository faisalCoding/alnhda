<?php

namespace App\Models;

use App\Models\Concerns\HasSeoMeta;
use App\Services\LinkTargets;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory, HasSeoMeta;

    protected $fillable = [
        'title',
        'content',
        'image_article',
        'image_post',
        'cta_label',
        'cta_target_type',
        'cta_target_id',
    ];

    /**
     * Arabic prose is read at roughly this rate; the figure on a card is a
     * courtesy to the reader, not a measurement.
     */
    private const WORDS_PER_MINUTE = 180;

    /**
     * The opening of the article as plain text, for a card or a listing.
     */
    public function excerpt(int $limit = 120): string
    {
        return Str::limit($this->plainText(), $limit);
    }

    public function readingMinutes(): int
    {
        $text = $this->plainText();

        if ($text === '') {
            return 1;
        }

        return max(1, (int) ceil(count(preg_split('/\s+/u', $text) ?: []) / self::WORDS_PER_MINUTE));
    }

    /**
     * The article stripped of its markup.
     *
     * A tag is a word boundary, and strip_tags() does not treat it as one — it
     * would join the last word of a heading to the first word of the paragraph
     * beneath it, which then reads as one nonsense word in every excerpt and
     * counts as one word in every estimate. Scripts and styles go entirely:
     * their contents are not prose.
     */
    private function plainText(): string
    {
        $content = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $this->content) ?? '';

        return Str::squish(strip_tags(str_replace('<', ' <', $content)));
    }

    /**
     * Arabic counts one, two and a few differently, and a card that says
     * «1 دقائق» reads as though a machine wrote it.
     */
    public function readingTimeLabel(): string
    {
        $minutes = $this->readingMinutes();

        return match (true) {
            $minutes === 1 => 'دقيقة قراءة',
            $minutes === 2 => 'دقيقتان للقراءة',
            $minutes <= 10 => $minutes.' دقائق قراءة',
            default => $minutes.' دقيقة قراءة',
        };
    }

    /**
     * The project, unit or article the button at the end of this one points at.
     */
    public function ctaTarget(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A button is only shown while its destination still exists — a deleted
     * project takes its button with it rather than leaving a dead link behind.
     */
    public function hasCta(): bool
    {
        return $this->ctaUrl() !== null;
    }

    public function ctaUrl(): ?string
    {
        return LinkTargets::urlFor($this->ctaTarget);
    }

    /**
     * The button's wording, falling back to what the destination calls itself
     * so the button is never blank.
     */
    public function ctaLabel(): ?string
    {
        return filled($this->cta_label)
            ? $this->cta_label
            : LinkTargets::nameFor($this->ctaTarget);
    }
}
