<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSocialPlatformRequest;
use App\Http\Requests\Admin\UpdateSocialPlatformRequest;
use App\Http\Resources\Admin\SocialPlatformResource;
use App\Models\SocialPlatform;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SocialPlatformController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SocialPlatformResource::collection(
            SocialPlatform::query()
                ->with('tasks')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreSocialPlatformRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $applyTemplates = (bool) ($attributes['apply_templates'] ?? true);
        unset($attributes['apply_templates']);

        $platform = DB::transaction(function () use ($attributes, $applyTemplates): SocialPlatform {
            $platform = SocialPlatform::query()->create([
                ...$attributes,
                'sort_order' => (int) SocialPlatform::query()->max('sort_order') + 1,
            ]);

            if ($applyTemplates) {
                $platform->applyTaskTemplates();
            }

            return $platform;
        });

        return (new SocialPlatformResource($platform->load('tasks')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSocialPlatformRequest $request, SocialPlatform $socialPlatform): SocialPlatformResource
    {
        $attributes = $request->validated();

        // An absent password key leaves the stored one alone; an explicit null clears it.
        if (array_key_exists('password', $attributes) && $attributes['password'] === '') {
            $attributes['password'] = null;
        }

        $socialPlatform->update($attributes);

        return new SocialPlatformResource($socialPlatform->load('tasks'));
    }

    public function destroy(SocialPlatform $socialPlatform): JsonResponse
    {
        $id = $socialPlatform->id;
        $socialPlatform->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
