<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Initiative\InitiativeRequest;
use App\Http\Resources\Initiative\InitiativeResource;
use App\Http\Repository\Contracts\InitiativeRepositoryInterface;
use App\Models\Initiative;
use Illuminate\Http\Request;

class InitiativeController extends Controller
{
    public function __construct(public InitiativeRepositoryInterface $initiativeRepositoryInterface) {}


    public function index()
    {
        $initiatives = $this->initiativeRepositoryInterface->index();
        return InitiativeResource::collection($initiatives);
    }

    public function store(InitiativeRequest $request)
    {
        $data = $request->validated();
        $data['added_by'] = auth()->id();
        $initiative = $this->initiativeRepositoryInterface->create($data);
        return new InitiativeResource($initiative);
    }

    public function show($id)
    {
        $initiative = $this->initiativeRepositoryInterface->find($id);
        $initiative->load('addedBy');
        return new InitiativeResource($initiative);
    }

    public function update(InitiativeRequest $request, Initiative $initiative)
    {
        $initiative = $this->initiativeRepositoryInterface->update($initiative->id, $request->validated());
        return new InitiativeResource($initiative);
    }

    public function destroy(Initiative $initiative)
    {
        if ($initiative->added_by !== auth()->id()) {
            return $this->handleErrorResponse('Unauthorized', 403);
        }

        $this->initiativeRepositoryInterface->delete($initiative->id);

        return $this->handleSuccessResponse('Initiative deleted successfully');
    }
}
