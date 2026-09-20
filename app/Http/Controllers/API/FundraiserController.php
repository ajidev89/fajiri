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

    public function index(Request $request)
    {
        $fundraisers = $this->fundraiserRepository->index($request);
        return $this->handleSuccessCollectionResponse('Fundraisers fetched successfully', UserResource::collection($fundraisers));
    }

    public function export(Request $request)
    {
        $rows = $this->fundraiserRepository->filteredQuery($request)
            ->lazy(500)
            ->map(function (User $user) {
                return [
                    trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')),
                    $user->email,
                    $user->username,
                    $user->status,
                    $user->country?->name,
                    $user->campaigns?->count() ?? 0,
                    $user->needs?->count() ?? 0,
                    optional($user->created_at)?->toDateTimeString(),
                ];
            });

        return $this->streamCsv('fundraisers.csv', [
            'Name',
            'Email',
            'Username',
            'Status',
            'Country',
            'Campaigns',
            'Needs',
            'Date Joined',
        ], $rows);
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
