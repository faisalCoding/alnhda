<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Http\Requests\Admin\UpdateSubscriptionRequest;
use App\Http\Resources\Admin\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SubscriptionResource::collection(
            Subscription::query()
                ->orderByRaw('expires_on is null, expires_on asc')
                ->orderBy('id')
                ->get()
        );
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $subscription = Subscription::query()->create([
            ...$request->validated(),
            'sort_order' => (int) Subscription::query()->max('sort_order') + 1,
        ]);

        return (new SubscriptionResource($subscription))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $attributes = $request->validated();

        // An absent key leaves the stored billing detail alone; an empty one clears it.
        if (array_key_exists('payment_account', $attributes) && $attributes['payment_account'] === '') {
            $attributes['payment_account'] = null;
        }

        $subscription->update($attributes);

        return new SubscriptionResource($subscription);
    }

    public function destroy(Subscription $subscription): JsonResponse
    {
        $id = $subscription->id;
        $subscription->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
