<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ArticleResource;
use App\Http\Resources\Admin\LeadResource;
use App\Http\Resources\Admin\ProjectResource;
use App\Http\Resources\Admin\PropertyResource;
use App\Http\Resources\Admin\VisitorResource;
use App\Models\Article;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Properties;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'data' => [
                'counts' => [
                    'projects' => Project::query()->count(),
                    'properties' => Properties::query()->count(),
                    'articles' => Article::query()->count(),
                    'visitors' => Visitor::query()->count(),
                    'leads' => Lead::query()->count(),
                ],
                'latest' => [
                    'projects' => ProjectResource::collection(
                        Project::query()->withCount('properties')->latest()->limit(5)->get()
                    ),
                    'properties' => PropertyResource::collection(
                        Properties::query()->with('propertiesImages')->latest()->limit(5)->get()
                    ),
                    'articles' => ArticleResource::collection(
                        Article::query()->latest()->limit(5)->get()
                    ),
                    'visitors' => VisitorResource::collection(
                        Visitor::query()->latest()->limit(5)->get()
                    ),
                    'leads' => LeadResource::collection(
                        Lead::query()->latest()->limit(5)->get()
                    ),
                ],
            ],
        ]);
    }
}
