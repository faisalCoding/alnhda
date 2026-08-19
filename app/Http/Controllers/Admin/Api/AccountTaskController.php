<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountTaskRequest;
use App\Http\Requests\Admin\UpdateAccountTaskRequest;
use App\Http\Resources\Admin\AccountResource;
use App\Http\Resources\Admin\AccountTaskResource;
use App\Models\Account;
use App\Models\AccountTask;
use Illuminate\Http\JsonResponse;

class AccountTaskController extends Controller
{
    public function store(StoreAccountTaskRequest $request, Account $account): JsonResponse
    {
        $task = $account->tasks()->create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order', (int) $account->tasks()->max('sort_order') + 1),
        ]);

        return (new AccountTaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAccountTaskRequest $request, AccountTask $task): AccountTaskResource
    {
        $attributes = $request->validated();

        if (array_key_exists('is_done', $attributes)) {
            $attributes['completed_at'] = $attributes['is_done'] ? now() : null;
        }

        $task->update($attributes);

        return new AccountTaskResource($task);
    }

    public function destroy(AccountTask $task): JsonResponse
    {
        $id = $task->id;
        $task->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /**
     * Re-apply the current template checklist, skipping titles already present.
     */
    public function applyTemplates(Account $account): AccountResource
    {
        $existing = $account->tasks()->pluck('title');

        $missing = \App\Models\TaskTemplate::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->reject(fn ($template): bool => $existing->contains($template->title));

        $account->tasks()->createMany(
            $missing->map(fn ($template): array => [
                'title' => $template->title,
                'sort_order' => $template->sort_order,
            ])->all()
        );

        return new AccountResource($account->load(['tasks', 'categories']));
    }
}
