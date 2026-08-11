<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLeadRequest;
use App\Http\Requests\Admin\UpdateLeadRequest;
use App\Http\Resources\Admin\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return LeadResource::collection(
            Lead::query()->latest()->get()
        );
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $lead = Lead::query()->create($request->validated());

        return (new LeadResource($lead))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateLeadRequest $request, Lead $lead): LeadResource
    {
        $lead->update($request->validated());

        return new LeadResource($lead);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $id = $lead->id;
        $lead->delete();

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }
}
