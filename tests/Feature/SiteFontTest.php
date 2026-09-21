<?php

/**
 * The site's typeface is served from this domain, so the files have to be
 * there: a missing weight is not an error anywhere, the browser simply falls
 * back and nobody notices the page is wearing the wrong face.
 */
it('ships every weight it declares', function () {
    $declared = [];
    preg_match_all(
        "/url\('\/fonts\/lama-sans\/(lama-sans-\d+\.woff2)'\)/",
        $this->get(route('welcome'))->assertOk()->getContent(),
        $declared,
    );

    expect($declared[1])->not->toBeEmpty();

    foreach ($declared[1] as $file) {
        expect(public_path('fonts/lama-sans/'.$file))->toBeReadableFile();
    }
});

it('sets the body in Lama Sans', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee("font-family: 'Lama Sans', 'Almarai', sans-serif !important;", false);
});

it('keeps the previous face loaded so it can be used again', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('family=Almarai', false);
});

it('fetches the body weight with the page rather than after its stylesheet', function () {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('rel="preload" href="/fonts/lama-sans/lama-sans-400.woff2"', false);
});
