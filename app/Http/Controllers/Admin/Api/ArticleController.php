<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Http\Resources\Admin\ArticleResource;
use App\Models\Article;
use App\Services\LinkTargets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ArticleResource::collection(
            Article::query()->with('ctaTarget')->latest()->get()
        );
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $article = Article::query()->create([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'image_article' => '/img/article.jpg',
            ...$this->ctaAttributes($validated),
        ]);

        return (new ArticleResource($article->load('ctaTarget')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateArticleRequest $request, Article $article): ArticleResource
    {
        $validated = $request->validated();

        $article->update([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            ...$this->ctaAttributes($validated),
        ]);

        return new ArticleResource($article->load('ctaTarget'));
    }

    /**
     * The button's destination, translated from the short key the panel sends
     * into the model the column stores. A payload without a chosen destination
     * clears the button rather than leaving the previous one in place.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function ctaAttributes(array $validated): array
    {
        $target = LinkTargets::classFor($validated['cta_target_type'] ?? null);

        return [
            'cta_label' => $validated['cta_label'] ?? null,
            'cta_target_type' => $target,
            'cta_target_id' => $target === null ? null : $validated['cta_target_id'],
        ];
    }

    public function destroy(Article $article): JsonResponse
    {
        $id = $article->id;
        $article->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
