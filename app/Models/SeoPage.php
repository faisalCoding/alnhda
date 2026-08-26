<?php

namespace App\Models;

use App\Services\SeoPageDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Search and social presentation for one of the site's fixed pages.
 */
class SeoPage extends Model
{
    /** @use HasFactory<\Database\Factories\SeoPageFactory> */
    use HasFactory;

    protected $fillable = [
        'route_name',
        'title',
        'description',
        'image_path',
        'og_type',
        'noindex',
    ];

    /**
     * The pages an admin may tune. Read from the defaults rather than restated,
     * so a page can never be editable here and unknown there.
     *
     * @return list<string>
     */
    public static function editableRoutes(): array
    {
        return array_keys(SeoPageDefaults::PAGES);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'noindex' => 'boolean',
        ];
    }

    public function label(): string
    {
        return SeoPageDefaults::PAGES[$this->route_name]['label'] ?? $this->route_name;
    }
}
