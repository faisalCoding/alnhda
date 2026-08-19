<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTaskTemplateRequest;
use App\Http\Requests\Admin\UpdateTaskTemplateRequest;
use App\Http\Resources\Admin\TaskTemplateResource;
use App\Models\TaskTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TaskTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return TaskTemplateResource::collection(
            TaskTemplate::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(StoreTaskTemplateRequest $request): JsonResponse
    {
        $template = TaskTemplate::query()->create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order', (int) TaskTemplate::query()->max('sort_order') + 1),
        ]);

        return (new TaskTemplateResource($template))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateTaskTemplateRequest $request, TaskTemplate $taskTemplate): TaskTemplateResource
    {
        $taskTemplate->update($request->validated());

        return new TaskTemplateResource($taskTemplate);
    }

    public function destroy(TaskTemplate $taskTemplate): JsonResponse
    {
        $id = $taskTemplate->id;
        $taskTemplate->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
