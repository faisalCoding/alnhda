<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The official accounts are declared once in config and published in four
 * different shapes. These tests pin every shape to that one declaration, so an
 * account that is added — as the X account was — cannot reach some surfaces
 * and quietly miss the others.
 */
it('publishes every configured account in the footer', function () {
    $html = $this->get(route('welcome'))->assertOk()->getContent();

    foreach (config('services.social') as $account) {
        expect($html)->toContain($account['url']);
    }
});

it('shows the accounts on the contact page', function () {
    $this->get(route('contact-us'))
        ->assertOk()
        ->assertSee('تابعنا')
        ->assertSee(config('services.social.x.url'), false)
        ->assertSee(config('services.social.x.aria'), false);
});

it('names the accounts for language models in llms.txt', function () {
    $content = $this->get(route('llms'))->assertOk()->getContent();

    foreach (config('services.social') as $account) {
        expect($content)->toContain($account['label'].' '.$account['url']);
    }
});

it('attributes shared pages to the X account', function () {
    $this->get(route('contact-us'))
        ->assertOk()
        ->assertSee('<meta name="twitter:site" content="@Nahda_Cont" />', false);
});
