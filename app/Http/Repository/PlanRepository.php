<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PlanRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Mail\SubscriptionSuccessMail;
use App\Models\Notification;
use App\Models\Plan;
use App\Services\PaymentGateway;
use App\Services\ReferralRewardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PlanRepository implements PlanRepositoryInterface
{
    use AuthUserTrait;

    public function __construct(protected PaymentGateway $paymentGateway) {}

    public function all(array $filters = [])
    {
        $user = $this->user();
        $query = Plan::query();

        // Admin should see all plans by default, unless filtered
        if ($user && $user->role && $user->role->slug === 'admin') {
            // Keep query as is
        } else {
            $query->where('status', true);
        }

        if (! empty($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (! empty($filters['sub_account_type'])) {
            $query->where('sub_account_type', $filters['sub_account_type']);
        }

        if (! empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['created_at', 'updated_at', 'name', 'price'], true)
            ? $filters['sort_by']
            : 'created_at';
        $sortOrder = in_array(strtolower((string) ($filters['sort_order'] ?? 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) ($filters['sort_order'] ?? 'desc'))
            : 'desc';

        if (! empty($filters['page'])) {
            return $query->orderBy($sortBy, $sortOrder)->paginate($filters['per_page'] ?? 15);
        }

        return $query->orderBy($sortBy, $sortOrder)->get();
    }

    public function findById($id)
    {
        return Plan::findOrFail($id);
    }

    public function store(array $data)
    {
        $data['slug'] = Str::slug($data['name']);
        $data['duration'] = null;

        return Plan::create($data);
    }

    public function initializeSubscription($user, $planId, array $options = [])
    {
        $plan = $this->findById($planId);

        return $this->paymentGateway->initializeSubscription($user, $plan, $options);
    }

    public function update($id, array $data)
    {
        $plan = $this->findById($id);
        unset($data['duration']);
        $plan->update($data);

        return $plan;
    }

    public function delete($id)
    {
        $plan = $this->findById($id);

        if ($plan->users()->exists()) {
            throw new \Exception("Cannot delete plan '{$plan->name}' because it has existing subscribers.");
        }

        return $plan->delete();
    }

    public function subscribeUser($user, $planId, $duration = null)
    {
        return DB::transaction(function () use ($user, $planId, $duration) {
            $plan = Plan::findOrFail($planId);

            // Handle Payment if plan is not free
            if ($plan->price > 0) {
                $wallet = $user->wallet()->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);

                if ($wallet->balance < $plan->price) {
                    throw new \Exception('Insufficient wallet balance to subscribe to this plan.');
                }

                $wallet->decrement('balance', $plan->price);

                // Create transaction record
                $wallet->transactions()->create([
                    'amount' => $plan->price,
                    'type' => 'withdrawal',
                    'description' => "Subscription to {$plan->name} plan",
                    'reference' => 'SUB_'.str($plan->name)->slug().'_'.uniqid(),
                    'status' => 'completed',
                ]);
            }

            // Deactivate current active plans
            DB::table('user_plans')
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'inactive']);

            // Memberships are one-time lifetime payments: an admin may still grant a fixed duration.
            $startedAt = now();
            $expiresAt = $duration ? $startedAt->copy()->addDays($duration) : null;

            $user->plans()->attach($plan->id, [
                'id' => Str::uuid(),
                'started_at' => $startedAt,
                'expires_at' => $expiresAt,
                'status' => 'active',
                'auto_renew' => false,
            ]);

            // Create notification
            Notification::create([
                'user_id' => $user->id,
                'title' => 'Plan Subscribed',
                'message' => "You have successfully subscribed to the '{$plan->name}' plan.",
                'type' => 'plan_subscription',
                'data' => [
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'amount' => $plan->price,
                    'currency' => $plan->currency,
                ],
            ]);

            app(ReferralRewardService::class)->releaseIfEligible($user);

            // Send Email
            try {
                Mail::to($user->email)->send(new SubscriptionSuccessMail($user, $plan, $plan->price, $plan->currency));
            } catch (\Exception $e) {
                \Log::error('Failed to send subscription email: '.$e->getMessage());
            }

            return $user->currentPlan();
        });
    }
}
