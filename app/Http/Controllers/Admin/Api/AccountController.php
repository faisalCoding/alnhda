<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountRequest;
use App\Http\Requests\Admin\UpdateAccountRequest;
use App\Http\Resources\Admin\AccountResource;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AccountResource::collection(
            Account::query()
                ->with(['tasks', 'categories'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $categoryIds = $attributes['category_ids'] ?? [];
        unset($attributes['category_ids']);

        $account = DB::transaction(function () use ($attributes, $categoryIds): Account {
            $account = Account::query()->create([
                ...$attributes,
                'sort_order' => (int) Account::query()->max('sort_order') + 1,
            ]);

            $account->categories()->sync($categoryIds);

            // The checklist is pulled in on demand from the card, not seeded here.
            return $account;
        });

        return (new AccountResource($account->load(['tasks', 'categories'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        $attributes = $request->validated();

        // An absent password key leaves the stored one alone; an explicit null clears it.
        if (array_key_exists('password', $attributes) && $attributes['password'] === '') {
            $attributes['password'] = null;
        }

        if (array_key_exists('category_ids', $attributes)) {
            $account->categories()->sync($attributes['category_ids'] ?? []);
            unset($attributes['category_ids']);
        }

        $account->update($attributes);

        return new AccountResource($account->load(['tasks', 'categories']));
    }

    public function destroy(Account $account): JsonResponse
    {
        $id = $account->id;
        $account->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
