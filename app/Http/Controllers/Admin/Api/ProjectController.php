<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderProjectsRequest;
use App\Http\Requests\Admin\StoreProjectRequest;
use App\Http\Requests\Admin\UpdateProjectRequest;
use App\Http\Resources\Admin\ProjectResource;
use App\Models\Project;
use App\Services\Market\MarketPlaces;
use App\Services\Market\PlaceName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ProjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProjectResource::collection(
            Project::query()->withCount('properties')->ordered()->get()
        );
    }

    /**
     * Persists the order chosen by dragging. Positions are written in one
     * transaction so a half-applied order can never reach the public pages.
     */
    public function reorder(ReorderProjectsRequest $request): JsonResponse
    {
        $ids = $request->validated()['ids'];

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                Project::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });

        return response()->json(['data' => ['ordered' => count($ids)]]);
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

        // The placement goes last: a location written from the market's names
        // replaces whatever was typed.
        return array_merge([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'location' => $validated['location'] ?? null,
            'status' => $validated['status'],
            'project_type' => $validated['project_type'],
            'map_url' => $validated['map_url'] ?? null,
            'guarantees' => $guarantees ?: null,
        ], $this->marketPlace($validated));
    }

    /**
     * Where the project stands in the market's own terms, and the location a
     * buyer reads written from those same names.
     *
     * A request that does not mention the city at all — one queued by an
     * older copy of the panel — leaves the placement as it was. One that
     * names no city clears it and keeps whatever location was typed.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function marketPlace(array $validated): array
    {
        if (! array_key_exists('market_city_key', $validated)) {
            return [];
        }

        if (blank($validated['market_city_key'])) {
            return ['market_city_key' => null, 'market_district_key' => null];
        }

        $cityKey = PlaceName::key($validated['market_city_key']);
        $districtKey = filled($validated['market_district_key'] ?? null) ? PlaceName::key($validated['market_district_key']) : null;

        return [
            'market_city_key' => $cityKey,
            'market_district_key' => $districtKey,
            'location' => app(MarketPlaces::class)->location($cityKey, $districtKey) ?? $validated['location'] ?? null,
        ];
    }
}
