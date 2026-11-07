<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells the browser which scripts the public site vouches for, so a script
 * that finds its way into a page — through a stored field, a compromised
 * file or a crafted link — does not run.
 *
 * Inline scripts run only with this response's nonce, which Vite, Livewire
 * and the layouts all stamp on their tags. Livewire runs its CSP build, so
 * Alpine never turns attribute strings into code and 'unsafe-eval' is never
 * needed. Styles stay open to inline use: they cannot run code, and the
 * pages lean on style attributes throughout.
 *
 * The admin panel lives on its own domain with its own Alpine bundle and is
 * left out of this policy.
 */
class ContentSecurityPolicy
{
    /**
     * Where Tag Manager and the GA4 tags it loads send their hits. The
     * container holds only Google's own tag types, with no custom HTML or
     * JavaScript, which is what lets the policy do without 'unsafe-eval'.
     *
     * @var list<string>
     */
    private const GOOGLE_TAG_HOSTS = [
        'https://*.googletagmanager.com',
        'https://*.google-analytics.com',
        'https://*.analytics.google.com',
        'https://*.doubleclick.net',
        'https://*.google.com',
        'https://*.google.com.sa',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy(Vite::cspNonce()));

        return $response;
    }

    private function policy(string $nonce): string
    {
        $devServer = $this->viteDevServer();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", 'https://*.googletagmanager.com', ...$devServer],
            'style-src' => ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', 'https://fonts.bunny.net', ...$devServer],
            'font-src' => ["'self'", 'data:', 'https://fonts.gstatic.com', 'https://fonts.bunny.net', ...$devServer],
            'img-src' => ["'self'", 'data:', 'blob:', 'https:', ...$devServer],
            'connect-src' => ["'self'", ...self::GOOGLE_TAG_HOSTS, ...$this->viteDevServerSockets($devServer)],
            'frame-src' => [
                'https://www.googletagmanager.com',
                'https://maps.google.com',
                'https://www.google.com',
                'https://www.youtube-nocookie.com',
                'https://www.youtube.com',
            ],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'self'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))
            ->implode('; ');
    }

    /**
     * The Vite dev server while `npm run dev` is up, which serves the scripts
     * and styles in its place. Production builds never write the hot file.
     *
     * @return list<string>
     */
    private function viteDevServer(): array
    {
        if (! Vite::isRunningHot()) {
            return [];
        }

        return [rtrim(trim((string) file_get_contents(Vite::hotFile())), '/')];
    }

    /**
     * @param  list<string>  $devServer
     * @return list<string>
     */
    private function viteDevServerSockets(array $devServer): array
    {
        return [...$devServer, ...array_map(fn (string $origin): string => preg_replace('#^http#', 'ws', $origin), $devServer)];
    }
}
