<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\PlanRepositoryInterface;
use App\Http\Requests\Plan\UpdateRequest;
use App\Http\Resources\PlanResource;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    protected $planRepository;

    public function __construct(PlanRepositoryInterface $planRepository)
    {
        $this->planRepository = $planRepository;
    }

    public function index()
    {
        $plans = $this->planRepository->all();
        return PlanResource::collection($plans);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:plans,id',
            'duration' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $plan = $this->planRepository->subscribeUser($user, $request->plan_id, $request->duration);

        return response()->json([
            'message' => 'Subscribed successfully',
            'data' => new PlanResource($plan),
        ]);
    }

    public function update(UpdateRequest $request, $id)
    {
        $plan = $this->planRepository->update($id, $request->validated());

        return response()->json([
            'message' => 'Plan updated successfully',
            'data' => new PlanResource($plan),
        ]);
    }
}
