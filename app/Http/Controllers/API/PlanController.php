<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\PlanRepositoryInterface;
use App\Http\Requests\Plan\StoreRequest;
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

    public function index(Request $request)
    {
        $plans = $this->planRepository->all($request->only(['account_type', 'currency']));
        return PlanResource::collection($plans);
    }

    public function store(StoreRequest $request)
    {
        $plan = $this->planRepository->store($request->validated());

        return $this->handleSuccessResponse('Plan created successfully', [
            'data' => new PlanResource($plan),
        ], 201);
    }

    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:plans,id',
            'duration' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $plan = $this->planRepository->subscribeUser($user, $request->plan_id, $request->duration);

        return $this->handleSuccessResponse('Subscribed successfully', [
            'data' => new PlanResource($plan),
        ]);
    }

    public function update(UpdateRequest $request, $id)
    {
        $plan = $this->planRepository->update($id, $request->validated());

        return $this->handleSuccessResponse('Plan updated successfully', [
            'data' => new PlanResource($plan),
        ]);
    }

    public function destroy($id)
    {
        try {
            $this->planRepository->delete($id);
            return $this->handleSuccessResponse('Plan deleted successfully');
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }
}
