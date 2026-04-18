<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\UserRepositoryInterface;
use App\Http\Resources\Transaction\TransactionResource;
use App\Http\Resources\User\UserResource;
use App\Http\Services\CloudinaryService;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\AuthUserTrait;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserRepository implements UserRepositoryInterface {

    use ResponseTrait, AuthUserTrait;

    public function __construct(private User $user) {}

    public function index() {
        return $this->handleSuccessResponse("Successfully fetched user", new UserResource($this->user()));
    }

    public function changePassword($request) {
        try {
            $user = $this->user();
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->password)
            ]);

            return $this->handleSuccessResponse("Password successfully updated");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    public function transactions($request) {
        $wallet = $this->user()->wallet;
        $transactions = $wallet->transactions()->latest()->when($request->type, function ($query) use ($request) {
            return $query->where('type', $request->type);
        })->when($request->status, function ($query) use ($request) {
            return $query->where('status', $request->status);
        })->when($request->start_date, function ($query) use ($request) {
            return $query->where('created_at', '>=', $request->start_date);
        })->when($request->end_date, function ($query) use ($request) {
            return $query->where('created_at', '<=', $request->end_date);
        })->paginate(10);
        return $this->handleSuccessCollectionResponse("Transactions successfully fetched", TransactionResource::collection($transactions));
    }

    public function transfer($request) {
        return DB::transaction(function () use ($request) {
            $user = $this->user();

            // 1. Verify PIN
            if (!Hash::check($request->pin, $user->pin)) {
                return $this->handleErrorResponse("Invalid transaction PIN", 400);
            }

            // 2. Find Recipient
            $recipient = $this->user->where('username', $request->username)->first();
            if (!$recipient) {
                return $this->handleErrorResponse("Recipient not found", 404);
            }

            // 3. Prevent self-transfer
            if ($recipient->id === $user->id) {
                return $this->handleErrorResponse("You cannot transfer money to yourself", 400);
            }

            try {
                // Perform Transfer
                // Deposit to recipient first
                $recipient->deposit((float)$request->amount, "Transfer from {$user->username}");

                // Withdraw from sender (HasWallet trait handles balance check)
                $withdrawal = $user->withdraw((float)$request->amount, "Transfer to {$recipient->username}");

                return $this->handleSuccessResponse("Transfer successful", new TransactionResource($withdrawal));
            } catch (\Exception $e) {
                return $this->handleErrorResponse($e->getMessage(), 400);
            }
        });
    }

    public function updateAvatar($request) {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $user = $this->user();
            $profile = $user->profile;

            if ($request->hasFile('avatar')) {
                // Delete old avatar if it exists
                if ($profile->avatar && \Illuminate\Support\Facades\Storage::disk('public')->exists($profile->avatar)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($profile->avatar);
                }

                $path = app(CloudinaryService::class)->uploadImage($request->file('avatar'));
                $profile->update(['avatar' => $path]);

                return $this->handleSuccessResponse("Avatar successfully updated", ['avatar_url' => $path]);
            }

            return $this->handleErrorResponse("Avatar file not found", 400);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    public function updatePin($request) {
        try {
            $user = $this->user();
            if($user->pin) {
                if(!Hash::check($request->current_pin, $user->pin)) {
                    return $this->handleErrorResponse("Incorrect current pin", 400);
                }
            }
            $user->update([
                'pin' => Hash::make($request->pin)
            ]);

            return $this->handleSuccessResponse("Pin successfully updated");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    public function withdrawAccount() {
        try {
            $withdrawalAccount = $this->user()->withdrawalAccounts()->where("default", true)->first();
            if(!$withdrawalAccount) {
                return $this->handleErrorResponse("No default withdrawal account found", 404);
            }

            return $this->handleSuccessResponse("Withdrawal successful", $withdrawalAccount);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    public function referrals() {
        $user = $this->user();
        return $this->handleSuccessResponse("Referral details fetched successfully", [
            'referral_code' => $user->referral_code,
            'referrals_count' => $user->referrals()->count()
        ]);
    }
}