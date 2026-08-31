<?php

namespace App\Services;

use App\Livewire\HeaderNavBar;
use Illuminate\Routing\Route;

/**
 * The site-wide Schema.org graph: who publishes this site, what site it is,
 * and where the visitor stands in it.
 *
 * Records carry their own nodes in their own Blade files. This class supplies
 * only the layer beneath them, and hands out `@id` references so a crawler
 * reads one publisher shared by every page rather than a fresh copy per page.
 */
class SiteSchema
{
    public function __construct(private readonly SeoPageDefaults $pageDefaults) {}

    public const ORGANIZATION_ID = '#organization';

    public const WEBSITE_ID = '#website';

    /**
     * Routes whose breadcrumb is built by the record's own Blade file, so
     * building a second one here would contradict it.
     *
     * @var array<int, string>
     */
    private const RECORD_ROUTES = ['project', 'article', 'properties'];

    /**
     * The whole graph for the current request.
     *
     * @return array<string, mixed>
     */
    public function graph(ResolvedSeo $seo): array
    {
        $nodes = [$this->organization(), $this->website(), $this->webPage($seo)];

        if ($breadcrumbs = $this->breadcrumbs()) {
            $nodes[] = $breadcrumbs;
        }

        return ['@context' => 'https://schema.org', '@graph' => $nodes];
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@type' => 'RealEstateAgent',
            '@id' => url('/').self::ORGANIZATION_ID,
            'name' => 'كيان النهضة العقارية',
            'url' => url('/'),
            'logo' => asset('img/KNicon.png'),
            'image' => asset('img/KNicon.png'),
            'telephone' => '+966564364261',
            'email' => 'info@kayanalnhda.com',
            'identifier' => [
                [
                    '@type' => 'PropertyValue',
                    'name' => 'الرقم الموحد للمنشأة',
                    'value' => '7025720975',
                ],
                [
                    '@type' => 'PropertyValue',
                    'name' => 'رخصة فال من الهيئة العامة للعقار',
                    'value' => '1200019224',
                ],
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'جدة',
                'addressCountry' => 'SA',
            ],
            'areaServed' => [
                '@type' => 'City',
                'name' => 'جدة',
            ],
            'hasMap' => HeaderNavBar::OFFICE_MAP_URL,
            'geo' => $this->officeCoordinates(),
            'sameAs' => [
                'https://www.youtube.com/@KayanAlnhda',
                'https://www.instagram.com/nahda_realestate/',
            ],
        ];
    }

    /**
     * Where the office actually is, read out of the map link the site already
     * publishes rather than typed a second time — two copies of a coordinate
     * are two coordinates the moment one of them is corrected.
     *
     * @return array<string, mixed>|null
     */
    private function officeCoordinates(): ?array
    {
        parse_str((string) parse_url(HeaderNavBar::OFFICE_MAP_URL, PHP_URL_QUERY), $query);

        $destination = $query['destination'] ?? null;

        if (! is_string($destination) || ! preg_match('/^(-?\d+\.?\d*),(-?\d+\.?\d*)$/', $destination, $matches)) {
            return null;
        }

        return [
            '@type' => 'GeoCoordinates',
            'latitude' => (float) $matches[1],
            'longitude' => (float) $matches[2],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').self::WEBSITE_ID,
            'url' => url('/'),
            'name' => 'كيان النهضة العقارية',
            'inLanguage' => 'ar',
            'publisher' => ['@id' => url('/').self::ORGANIZATION_ID],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function webPage(ResolvedSeo $seo): array
    {
        return array_filter([
            '@type' => 'WebPage',
            '@id' => url()->current().'#page',
            'url' => url()->current(),
            'name' => $seo->title,
            'description' => $seo->description,
            'inLanguage' => 'ar',
            'isPartOf' => ['@id' => url('/').self::WEBSITE_ID],
            'about' => ['@id' => url('/').self::ORGANIZATION_ID],
            'primaryImageOfPage' => $seo->image ? ['@type' => 'ImageObject', 'url' => $seo->image] : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * The trail for a fixed page, or null when the page has none worth showing
     * or builds its own.
     *
     * @return array<string, mixed>|null
     */
    public function breadcrumbs(): ?array
    {
        $route = request()->route();
        $name = $route instanceof Route ? $route->getName() : null;

        if ($name === null || $name === 'welcome' || in_array($name, self::RECORD_ROUTES, true)) {
            return null;
        }

        $label = $this->pageDefaults->label($name);

        // label() يعيد اسم المسار نفسه حين لا يعرف الصفحة — لا نبني فتاتًا منه.
        if ($label === $name) {
            return null;
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => url()->current().'#breadcrumbs',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'الرئيسية', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $label, 'item' => url()->current()],
            ],
        ];
    }
}
