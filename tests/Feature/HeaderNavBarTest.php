<?php

use App\Livewire\HeaderNavBar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function navBarHtml(): string
{
    $html = test()->get(route('contact-us'))->assertOk()->getContent();

    return Str::between($html, '<nav', '</nav>');
}

it('renders the navigation inside the guest layout header', function () {
    $this->get(route('contact-us'))
        ->assertOk()
        ->assertSeeLivewire(HeaderNavBar::class);
});

it('renders every navigation link', function (string $route, string $label) {
    expect(navBarHtml())
        ->toContain($label)
        ->toContain(route($route));
})->with([
    ['welcome', 'الرئيسية'],
    ['projects', 'مشاريعنا'],
    ['articles', 'المقالات'],
    ['about-us', 'من نحن'],
    ['contact-us', 'تواصل معنا'],
]);

it('shows the visit office call to action linking to the office location', function () {
    expect(navBarHtml())
        ->toContain('زيارة المكتب')
        ->toContain(e(HeaderNavBar::OFFICE_MAP_URL));
});

it('keeps the visit office action outside the mobile drawer so it stays visible on phones', function () {
    $nav = navBarHtml();

    expect(strpos($nav, 'زيارة المكتب'))->toBeLessThan(strpos($nav, 'id="mobile-menu"'));
});

it('places the logo before the desktop menu so it sits on the right in rtl', function () {
    $nav = navBarHtml();

    expect(strpos($nav, 'alnhdafooterlogo.webp'))->toBeLessThan(strpos($nav, 'مشاريعنا'));
});

it('renders the mobile drawer with a backdrop and a close control', function () {
    expect(navBarHtml())
        ->toContain('id="mobile-menu"')
        ->toContain('إغلاق القائمة')
        ->toContain('فتح القائمة');
});
