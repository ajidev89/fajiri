<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\UsersRepositoryInterface;
use App\Http\Resources\User\UserResource;
use App\Http\Requests\User\UpdateRequest;
use App\Models\User;  

class UsersController extends Controller
{

    public function __construct(protected UsersRepositoryInterface $usersRepositoryInterface)
    {}

    public function index(){
        $users = $this->usersRepositoryInterface->index();
        return $this->handleSuccessCollectionResponse("Successfully fetched users", UserResource::collection($users));
    }

    public function show(User $user){
        $user = $this->usersRepositoryInterface->find($user);
        return $this->handleSuccessResponse("Successfully fetched user", new UserResource($user));
    }

    public function update(User $user, UpdateRequest $request){
        $user = $this->usersRepositoryInterface->update($user, $request->validated());
        return $this->handleSuccessResponse("Successfully updated user", new UserResource($user));
    }

    public function suspend(User $user){
        $user = $this->usersRepositoryInterface->suspend($user);
        return $this->handleSuccessResponse("Successfully suspended user", new UserResource($user));
    }

    public function unsuspend(User $user){
        $user = $this->usersRepositoryInterface->unsuspend($user);
        return $this->handleSuccessResponse("Successfully unsuspended user", new UserResource($user));
    }

    public function delete(User $user){
        $user = $this->usersRepositoryInterface->delete($user);
        return $this->handleSuccessResponse("Successfully deleted user", new UserResource($user));
    }

    public function audits(User $user){
        $audits = $this->usersRepositoryInterface->audits($user);
        return $this->handleSuccessCollectionResponse("Successfully fetched user audits", \App\Http\Resources\AuditResource::collection($audits));
    }

    public function transactions(User $user){
        $transactions = $this->usersRepositoryInterface->transactions($user);
        return $this->handleSuccessCollectionResponse("Successfully fetched user transactions", \App\Http\Resources\TransactionResource::collection($transactions));
    }
}
