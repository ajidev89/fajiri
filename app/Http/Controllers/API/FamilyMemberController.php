<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\FamilyMemberRepositoryInterface;
use App\Http\Requests\Family\FamilyMemberRequest;
use App\Http\Resources\FamilyMemberResource;
use Illuminate\Http\Request;

class FamilyMemberController extends Controller
{
    public function __construct(protected FamilyMemberRepositoryInterface $repository)
    {
    }

    public function index()
    {
        $members = $this->repository->all(auth()->id());
        return FamilyMemberResource::collection($members)->additional([
            'message' => 'Family tree fetched successfully',
            'status' => true,
        ]);
    }

    public function store(FamilyMemberRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $member = $this->repository->create($data);
        return (new FamilyMemberResource($member))->additional([
            'message' => 'Family member added successfully',
            'status' => true,
        ]);
    }

    public function show($id)
    {
        $member = $this->repository->find($id);
        
        if ($member->user_id !== auth()->id()) {
            return $this->handleErrorResponse('Unauthorized', 403);
        }

        $member->load(['children', 'parent']);
        return (new FamilyMemberResource($member))->additional([
            'message' => 'Family member details fetched successfully',
            'status' => true,
        ]);
    }

    public function update(FamilyMemberRequest $request, $id)
    {
        $member = $this->repository->find($id);

        if ($member->user_id !== auth()->id()) {
            return $this->handleErrorResponse('Unauthorized', 403);
        }

        $member = $this->repository->update($id, $request->validated());
        return (new FamilyMemberResource($member))->additional([
            'message' => 'Family member updated successfully',
            'status' => true,
        ]);
    }

    public function destroy($id)
    {
        $member = $this->repository->find($id);

        if ($member->user_id !== auth()->id()) {
            return $this->handleErrorResponse('Unauthorized', 403);
        }

        $this->repository->delete($id);
        return $this->handleSuccessResponse('Family member deleted successfully');
    }
}
