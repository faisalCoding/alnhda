<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountCategoryRequest;
use App\Http\Requests\Admin\UpdateAccountCategoryRequest;
use App\Http\Resources\Admin\AccountCategoryResource;
use App\Models\AccountCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AccountCategoryResource::collection(
            AccountCategory::query()
                ->withCount('accounts')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreAccountCategoryRequest $request): JsonResponse
    {
        $category = AccountCategory::query()->create([
            ...$request->validated(),
            'sort_order' => (int) AccountCategory::query()->max('sort_order') + 1,
        ]);

        return (new AccountCategoryResource($category->loadCount('accounts')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAccountCategoryRequest $request, AccountCategory $accountCategory): AccountCategoryResource
    {
        $accountCategory->update($request->validated());

        return new AccountCategoryResource($accountCategory->loadCount('accounts'));
    }

    /**
     * Deleting a category leaves its accounts in place, merely uncategorised.
     */
    public function destroy(AccountCategory $accountCategory): JsonResponse
    {
        $id = $accountCategory->id;
        $accountCategory->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
