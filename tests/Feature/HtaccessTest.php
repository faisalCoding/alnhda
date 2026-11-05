<?php

/**
 * Apache never runs under the test suite, so the rule is read out of
 * public/.htaccess and its condition is run against request lines the way
 * mod_rewrite would see them in THE_REQUEST.
 */
function indexPhpRedirect(): array
{
    $htaccess = file_get_contents(public_path('.htaccess'));

    preg_match('/^\s*RewriteCond %\{THE_REQUEST\} (\S+) \[NC\]\s*\n\s*RewriteRule \^ (\S+) \[([^\]]+)\]/m', $htaccess, $rule);

    expect($rule)->not->toBeEmpty();

    return ['pattern' => '#'.$rule[1].'#i', 'target' => $rule[2], 'flags' => explode(',', $rule[3]), 'htaccess' => $htaccess];
}

it('sends index.php addresses to the clean address of the same page', function (string $requestLine, string $expected) {
    $rule = indexPhpRedirect();

    expect(preg_match($rule['pattern'], $requestLine, $captures))->toBe(1)
        ->and(str_replace('%2', $captures[2] ?? '', $rule['target']))->toBe($expected);
})->with([
    'the front page' => ['GET /index.php HTTP/1.1', '/'],
    'with a trailing slash' => ['GET /index.php/ HTTP/1.1', '/'],
    'a page beneath it' => ['GET /index.php/projects HTTP/1.1', '/projects'],
    'with a query string' => ['GET /index.php?utm_source=x HTTP/1.1', '/'],
    'an encoded arabic slug' => ['GET /index.php/collections/%D8%A7%D9%84 HTTP/2.0', '/collections/%D8%A7%D9%84'],
    'a head request' => ['HEAD /index.php HTTP/1.1', '/'],
    'in capitals' => ['GET /INDEX.PHP HTTP/1.1', '/'],
]);

it('leaves alone what only looks like index.php, and anything with a body', function (string $requestLine) {
    expect(preg_match(indexPhpRedirect()['pattern'], $requestLine))->toBe(0);
})->with([
    'a form post' => ['POST /index.php HTTP/1.1'],
    'the front controller address itself' => ['GET / HTTP/1.1'],
    'a longer file name' => ['GET /index.php.bak HTTP/1.1'],
    'a lookalike' => ['GET /index.phpx HTTP/1.1'],
    'a nested index.php' => ['GET /foo/index.php HTTP/1.1'],
]);

it('redirects permanently without encoding the address a second time', function () {
    expect(indexPhpRedirect()['flags'])->toContain('R=301', 'NE', 'L');
});

it('runs before the front controller rewrites every request to index.php', function () {
    $htaccess = indexPhpRedirect()['htaccess'];

    expect(strpos($htaccess, '%{THE_REQUEST}'))->toBeLessThan(strpos($htaccess, 'RewriteRule ^ index.php [L]'));
});
