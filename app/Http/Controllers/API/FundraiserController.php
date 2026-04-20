<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\FundraiserRepositoryInterface;
use App\Http\Requests\User\StoreFundraiserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class FundraiserController extends Controller
{
    public function __construct(protected FundraiserRepositoryInterface $fundraiserRepository)
    {
    }

    public function index()
    {
        $fundraisers = $this->fundraiserRepository->index();
        return $this->handleSuccessResponse('Fundraisers fetched successfully', UserResource::collection($fundraisers));
    }

    public function store(StoreFundraiserRequest $request)
    {
        $fundraiser = $this->fundraiserRepository->store($request->validated());
        return $this->handleSuccessResponse('Fundraiser created successfully', new UserResource($fundraiser));
    }

    public function resetPassword(User $user)
    {
        if ($user->role->slug !== 'fundraiser') {
            return $this->handleErrorResponse('User is not a fundraiser', 403);
        }

        try {
            $this->fundraiserRepository->sendResetLink($user);
            return $this->handleSuccessResponse('Password reset link sent successfully');
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }
}
