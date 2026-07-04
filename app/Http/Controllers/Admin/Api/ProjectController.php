<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Http\Resources\Admin\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            Project::query()->withCount('properties')->latest()->get()
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::query()->create($this->projectData($request->validated()));

        return (new ProjectResource($project->loadCount('properties')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $project->update($this->projectData($request->validated()));

        return new ProjectResource($project->loadCount('properties'));
    }

    public function destroy(Project $project): JsonResponse
    {
        if ($project->properties()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف مشروع يحتوي على وحدات. احذف وحداته أولًا.',
                'errors' => ['project' => ['لا يمكن حذف مشروع يحتوي على وحدات. احذف وحداته أولًا.']],
            ], 422);
        }

        $id = $project->id;
        $project->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * Normalize validated input into a Project attribute array.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function projectData(array $validated): array
    {
        $guarantees = collect($validated['guarantees'] ?? [])
            ->map(fn ($guarantee): string => trim((string) $guarantee))
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'location' => $validated['location'] ?? null,
            'status' => $validated['status'],
            'project_type' => $validated['project_type'],
            'map_url' => $validated['map_url'] ?? null,
            'guarantees' => $guarantees ?: null,
        ];
    }
}
