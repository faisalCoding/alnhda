<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the local Node gateway pushing delivery acknowledgements. There
 * is no admin session behind these calls, so the shared key is the only proof
 * of origin — without it anyone could mark messages as delivered.
 */
class EnsureWhatsappGatewayKey
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.whatsapp.key');
        $provided = (string) $request->header('X-Api-Key', '');

        // An unset key would otherwise let every caller through.
        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        return $next($request);
    }
}
