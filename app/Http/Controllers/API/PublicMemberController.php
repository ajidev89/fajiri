<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\PublicMemberResource;
use App\Models\User;
use App\Enums\User\Status;

class PublicMemberController extends Controller
{
    /**
     * Get public details of a member by their member_id.
     */
    public function show(string $memberId)
    {
        $user = User::where('member_id', $memberId)
            ->where('status', Status::ACTIVE->value)
            ->with('profile')
            ->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member not found or inactive.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully fetched public member profile.',
            'data' => new PublicMemberResource($user),
        ]);
    }
}
