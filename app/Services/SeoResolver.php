<?php

namespace App\Services;

use App\Models\AppSettings;
use App\Models\SeoMeta;
use App\Models\SeoPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;

/**
 * Decides what a page tells search engines and link previews.
 *
 * Three sources, narrowest first: an override saved in the panel for this exact
 * record or page, then the text the page builds for itself in its own Blade
 * sections, then the site-wide defaults. An override is only ever what someone
 * typed on purpose, so it wins; a default is a floor, so it loses to anything
 * the page already says.
 */
class SeoResolver
{
    public function __construct(private readonly SeoPageDefaults $pageDefaults) {}

    /**
     * Route parameters that carry a record whose own overrides apply.
     *
     * @var array<string, string>
     */
    private const RECORD_ROUTES = [
        'project' => 'project',
        'article' => 'article',
        'properties' => 'properties',
    ];

    /**
     * @param  array{title?: ?string, description?: ?string, image?: ?string, og_type?: ?string}  $fromPage
     */
    public function forCurrentRoute(array $fromPage = []): ResolvedSeo
    {
        $route = request()->route();
        $override = $route instanceof Route ? $this->overrideFor($route) : null;
        $settings = AppSettings::current();
        $page = $this->pageDefaults->for((string) $route?->getName());

        $title = $this->firstFilled([
            $override?->title,
            $fromPage['title'] ?? null,
            $page['title'],
            $settings->seo_default_title,
        ]);

        $description = $this->firstFilled([
            $override?->description,
            $fromPage['description'] ?? null,
            $page['description'],
            $settings->seo_default_description,
        ]);

        $image = $this->firstFilled([
            $this->storageUrl($override?->image_path),
            $fromPage['image'] ?? null,
            $this->storageUrl($settings->seo_default_image_path),
            // الاحتياطي الأخير جزء من السلسلة لا استثناء بعدها: خارجها كان
            // يخرج بلا أبعاد، وبلا الأبعاد تفقد المعاينة لافتتها.
            asset('img/KNicon.png'),
        ]);

        $type = $this->firstFilled([
            $override instanceof SeoPage ? $override->og_type : null,
            $fromPage['og_type'] ?? null,
        ]) ?? 'website';

        return new ResolvedSeo(
            title: $title,
            description: $description,
            image: $image,
            imageSize: $image === null ? null : $this->imageSize($image),
            type: $type,
            noindex: (bool) $override?->noindex,
        );
    }

    /**
     * The saved override for whatever this route shows — a record's own row if
     * the route carries one, otherwise the row for the fixed page.
     */
    private function overrideFor(Route $route): SeoMeta|SeoPage|null
    {
        foreach (self::RECORD_ROUTES as $routeName => $parameter) {
            if ($route->getName() !== $routeName) {
                continue;
            }

            $record = $route->parameter($parameter);

            return $record instanceof Model ? $record->seoMeta : null;
        }

        $name = $route->getName();

        if ($name === null || ! array_key_exists($name, SeoPageDefaults::PAGES)) {
            return null;
        }

        return SeoPage::query()->where('route_name', $name)->first();
    }

    private function storageUrl(?string $path): ?string
    {
        return blank($path) ? null : asset('storage/'.ltrim($path, '/'));
    }

    /**
     * @param  array<int, ?string>  $candidates
     */
    private function firstFilled(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $trimmed = is_string($candidate) ? trim($candidate) : null;

            if ($trimmed !== null && $trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * WhatsApp and Facebook choose between a wide banner and a small square
     * thumbnail from these two numbers, before they have fetched the image at
     * all — so leaving them out costs the preview its banner.
     *
     * @return array{0: int, 1: int}|null
     */
    private function imageSize(string $url): ?array
    {
        $path = $this->localPathFor($url);

        if ($path === null) {
            return null;
        }

        // Upload paths carry a random name, so a cached size can never go stale
        // against a different image.
        return Cache::rememberForever('seo:image-size:'.md5($path), function () use ($path) {
            $size = @getimagesize($path);

            return $size === false ? null : [(int) $size[0], (int) $size[1]];
        });
    }

    private function localPathFor(string $url): ?string
    {
        $relative = ltrim((string) parse_url($url, PHP_URL_PATH), '/');

        if ($relative === '') {
            return null;
        }

        $path = public_path($relative);

        return is_file($path) ? $path : null;
    }
}
