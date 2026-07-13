<?php

namespace App\Http\Resources\Admin\Concerns;

trait ResolvesPublicUrl
{
    /**
     * Resolve a stored file path to a browser-usable URL.
     *
     * Uses asset() (request-root based, like the public site views) instead of
     * Storage::url() so the result stays valid even when APP_URL is misconfigured.
     */
    protected function publicUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }

        return asset('storage/'.$path);
    }
}
