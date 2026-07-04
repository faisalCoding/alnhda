<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleRequest;
use App\Http\Requests\Admin\UpdateArticleRequest;
use App\Http\Resources\Admin\ArticleResource;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ArticleResource::collection(
            Article::query()->latest()->get()
        );
    }

    public function store(StoreArticleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $article = Article::query()->create([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'image_article' => '/img/article.jpg',
        ]);

        return (new ArticleResource($article))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateArticleRequest $request, Article $article): ArticleResource
    {
        $validated = $request->validated();

        $article->update([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
        ]);

        return new ArticleResource($article);
    }

    public function destroy(Article $article): JsonResponse
    {
        $id = $article->id;
        $article->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
