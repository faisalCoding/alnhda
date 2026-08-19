<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSocialPlatformTaskRequest;
use App\Http\Requests\Admin\UpdateSocialPlatformTaskRequest;
use App\Http\Resources\Admin\SocialPlatformResource;
use App\Http\Resources\Admin\SocialPlatformTaskResource;
use App\Models\SocialPlatform;
use App\Models\SocialPlatformTask;
use Illuminate\Http\JsonResponse;

class SocialPlatformTaskController extends Controller
{
    public function store(StoreSocialPlatformTaskRequest $request, SocialPlatform $socialPlatform): JsonResponse
    {
        $task = $socialPlatform->tasks()->create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order', (int) $socialPlatform->tasks()->max('sort_order') + 1),
        ]);

        return (new SocialPlatformTaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSocialPlatformTaskRequest $request, SocialPlatformTask $task): SocialPlatformTaskResource
    {
        $attributes = $request->validated();

        if (array_key_exists('is_done', $attributes)) {
            $attributes['completed_at'] = $attributes['is_done'] ? now() : null;
        }

        $task->update($attributes);

        return new SocialPlatformTaskResource($task);
    }

    public function destroy(SocialPlatformTask $task): JsonResponse
    {
        $id = $task->id;
        $task->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * Re-apply the current template checklist, skipping titles already present.
     */
    public function applyTemplates(SocialPlatform $socialPlatform): SocialPlatformResource
    {
        $existing = $socialPlatform->tasks()->pluck('title');

        $missing = \App\Models\TaskTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->reject(fn ($template): bool => $existing->contains($template->title));

        $socialPlatform->tasks()->createMany(
            $missing->map(fn ($template): array => [
                'title' => $template->title,
                'sort_order' => $template->sort_order,
            ])->all()
        );

        return new SocialPlatformResource($socialPlatform->load('tasks'));
    }
}
