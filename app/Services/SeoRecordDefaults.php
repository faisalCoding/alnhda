<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CollectionPage;
use App\Models\Project;
use App\Models\Properties;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The title, description and image a record produces on its own, before anyone
 * overrides it.
 *
 * These rules used to live inside the three Blade files that render the pages,
 * which meant the panel could not show an editor what a record currently says
 * without restating them — and a restatement drifts. Both read from here now.
 */
class SeoRecordDefaults
{
    /**
     * Google shows roughly this much of a description before cutting it off.
     */
    public const DESCRIPTION_LIMIT = 155;

    /**
     * @return array{title: ?string, description: ?string, image: ?string, og_type: string}
     */
    public function for(Model $record): array
    {
        return match (true) {
            $record instanceof Project => $this->forProject($record),
            $record instanceof Article => $this->forArticle($record),
            $record instanceof Properties => $this->forUnit($record),
            $record instanceof CollectionPage => $this->forCollection($record),
            default => ['title' => null, 'description' => null, 'image' => null, 'og_type' => 'website'],
        };
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string, og_type: string}
     */
    private function forProject(Project $project): array
    {
        return [
            'title' => $project->name.' - مشروع سكني في جدة',
            'description' => Str::limit(strip_tags((string) $project->description), self::DESCRIPTION_LIMIT),
            'image' => $project->image_url ? asset('storage/'.$project->image_url) : asset('img/KNicon.png'),
            'og_type' => 'website',
        ];
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string, og_type: string}
     */
    private function forArticle(Article $article): array
    {
        $image = $article->image_article ?? 'img/article.webp';

        return [
            'title' => $article->title,
            'description' => Str::limit(strip_tags((string) $article->content), self::DESCRIPTION_LIMIT),
            'image' => filter_var($image, FILTER_VALIDATE_URL)
                ? $image
                : (Str::contains($image, ['articles/', 'uploads/', 'blogs/'])
                    ? asset('storage/'.$image)
                    : asset(str_replace('\\', '', $image))),
            'og_type' => 'article',
        ];
    }

    /**
     * A collection page owns no image of its own; the first record it gathers
     * is what a shared link should show.
     *
     * @return array{title: ?string, description: ?string, image: ?string, og_type: string}
     */
    private function forCollection(CollectionPage $collection): array
    {
        $first = $collection->items->first(fn (object $entry): bool => $entry->item !== null);

        return [
            'title' => $collection->title,
            'description' => Str::limit(strip_tags((string) $collection->description), self::DESCRIPTION_LIMIT),
            'image' => $first === null ? asset('img/KNicon.png') : $this->for($first->item)['image'],
            'og_type' => 'website',
        ];
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string, og_type: string}
     */
    private function forUnit(Properties $unit): array
    {
        $parts = array_filter([
            $unit->type ? $unit->name.' - '.$unit->type : $unit->name,
            $unit->project?->location ? 'في '.$unit->project->location : null,
            $unit->rooms ? $unit->rooms.' غرف' : null,
            $unit->bathrooms ? $unit->bathrooms.' دورات مياه' : null,
            $unit->area ? 'بمساحة '.$unit->area.' م²' : null,
            $unit->status ?: null,
        ]);

        $first = $unit->propertiesImages->first();

        return [
            'title' => implode(' - ', array_filter([$unit->name, $unit->type, $unit->project?->name])),
            'description' => Str::limit(implode('، ', $parts).'.', self::DESCRIPTION_LIMIT),
            'image' => $first ? asset('storage/'.$first->url) : asset('img/KNicon.png'),
            'og_type' => 'website',
        ];
    }
}
