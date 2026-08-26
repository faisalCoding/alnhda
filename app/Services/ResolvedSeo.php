<?php

namespace App\Services;

/**
 * The finished tags for one page, after the overrides, the page's own sections
 * and the site defaults have been folded together.
 */
final readonly class ResolvedSeo
{
    /**
     * @param  array{0: int, 1: int}|null  $imageSize
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $image,
        public ?array $imageSize,
        public string $type,
        public bool $noindex,
    ) {}

    public function imageWidth(): ?int
    {
        return $this->imageSize[0] ?? null;
    }

    public function imageHeight(): ?int
    {
        return $this->imageSize[1] ?? null;
    }
}
