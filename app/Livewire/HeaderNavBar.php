<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class HeaderNavBar extends Component
{
    /**
     * Google Maps directions link to the company office in Jeddah.
     */
    public const OFFICE_MAP_URL = 'https://www.google.com/maps/dir/?api=1&destination=21.5660262%2C39.2480873';

    /**
     * The `exact` flag keeps the home link from being marked as current on every page,
     * since `wire:current` otherwise matches "/" against any path.
     *
     * @return array<int, array{route: string, label: string, exact: bool}>
     */
    protected function links(): array
    {
        return [
            ['route' => 'welcome', 'label' => 'الرئيسية', 'exact' => true],
            ['route' => 'projects', 'label' => 'مشاريعنا', 'exact' => false],
            ['route' => 'articles', 'label' => 'المقالات', 'exact' => false],
            ['route' => 'about-us', 'label' => 'من نحن', 'exact' => false],
            ['route' => 'contact-us', 'label' => 'تواصل معنا', 'exact' => false],
        ];
    }

    public function render(): View
    {
        return view('livewire.header-nav-bar', [
            'links' => $this->links(),
            'officeMapUrl' => self::OFFICE_MAP_URL,
        ]);
    }
}
