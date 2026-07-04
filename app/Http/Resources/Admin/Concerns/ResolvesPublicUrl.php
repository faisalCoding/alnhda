<?php

namespace App\Http\Resources\Admin\Concerns;

use Illuminate\Support\Facades\Storage;

trait ResolvesPublicUrl
{
    /**
     * Resolve a stored file path to a browser-usable URL.
     */
    protected function publicUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
