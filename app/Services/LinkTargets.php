<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CollectionPage;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Database\Eloquent\Model;

/**
 * The records one page may point at, and the records a page may be built from.
 *
 * The panel speaks in the same short keys the SEO screens use, while the column
 * stores the model class. Keeping a destination typed — rather than a pasted
 * address — is what lets a link survive a route change and disappear quietly
 * instead of leading to a 404 once its record is deleted.
 */
class LinkTargets
{
    /**
     * Short key => model. The key doubles as the route name for the record's
     * public page.
     *
     * @var array<string, class-string<Model>>
     */
    public const TYPES = [
        'project' => Project::class,
        'article' => Article::class,
        'properties' => Properties::class,
        'collection' => CollectionPage::class,
    ];

    /**
     * What a collection page may be built from. A collection page is left out
     * of its own list: a page whose items are pages leads a reader in circles.
     *
     * @var list<string>
     */
    public const ITEM_TYPES = ['project', 'article', 'properties'];

    /**
     * @return class-string<Model>|null
     */
    public static function classFor(?string $key): ?string
    {
        return self::TYPES[$key] ?? null;
    }

    public static function keyFor(?Model $record): ?string
    {
        if ($record === null) {
            return null;
        }

        $key = array_search($record::class, self::TYPES, strict: true);

        return $key === false ? null : $key;
    }

    public static function urlFor(?Model $record): ?string
    {
        $key = self::keyFor($record);

        return $key === null ? null : route($key, $record);
    }

    /**
     * What the record calls itself, used when an admin leaves a button's own
     * wording empty.
     */
    public static function nameFor(?Model $record): ?string
    {
        return match (true) {
            $record instanceof Article, $record instanceof CollectionPage => $record->title,
            $record instanceof Project, $record instanceof Properties => $record->name,
            default => null,
        };
    }
}
