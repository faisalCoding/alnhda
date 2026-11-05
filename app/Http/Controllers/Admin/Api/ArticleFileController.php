<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadArticleImageRequest;
use App\Http\Resources\Admin\ArticleResource;
use App\Models\Article;
use App\Services\ImageService;

class ArticleFileController extends Controller
{
    public function storeImage(UploadArticleImageRequest $request, Article $article): ArticleResource
    {
        $path = ImageService::uploadAndProcess($request->file('image'), 'articles', ImageService::ARTICLE_WIDTH);

        $previousImage = $article->image_article;
        if ($previousImage && ! str_starts_with($previousImage, '/') && ! str_starts_with($previousImage, 'http')) {
            ImageService::delete($previousImage);
        }

        $article->update(['image_article' => $path]);

        return new ArticleResource($article);
    }
}
