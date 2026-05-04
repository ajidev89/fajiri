<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RevenueCatWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $webhookKey = config('services.revenuecat.webhook_key');
        $authorizationHeader = $request->header('Authorization');
        info("Authorization Header: " . $authorizationHeader);
        // Extract Bearer token
        if (Str::startsWith($authorizationHeader, 'Bearer ')) {
            $token = Str::after($authorizationHeader, 'Bearer ');
        } else {
            $token = $authorizationHeader;
        }

        if ($token !== $webhookKey) {
            return response()->json(['message' => 'Invalid webhook signature'], 400);
        }

        $payload = $request->all();
        $event = $payload['event'] ?? null;

        if (!$event) {
            return response()->json(['message' => 'No event type found'], 400);
        }

        $type = $event['type'] ?? '';
        $appUserId = $event['app_user_id'] ?? null;
        $productId = $event['product_id'] ?? null;

        Log::info('RevenueCat Webhook Received', ['type' => $type, 'app_user_id' => $appUserId]);

        $user = User::find($appUserId);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        switch ($type) {
            case 'INITIAL_PURCHASE':
            case 'RENEWAL':
            case 'UNCANCELLATION':
                $this->handleSubscriptionActive($user, $productId, $event);
                break;

            case 'EXPIRATION':
            case 'CANCELLATION':
                $this->handleSubscriptionInactive($user, $productId, $event);
                break;
            
            default:
                Log::info('Unhandled RevenueCat Event Type: ' . $type);
                break;
        }

        return response()->json(['message' => 'Webhook handled']);
    }

    protected function handleSubscriptionActive(User $user, $productId, $event)
    {
        // Find the plan that matches this product ID
        $plan = Plan::where('rc_product_id_ios', $productId)
                    ->orWhere('rc_product_id_android', $productId)
                    ->first();

        if (!$plan) {
            // Fallback: search by slug if product IDs aren't set
            $plan = Plan::where('slug', $productId)->first();
        }

        if ($plan) {
            DB::transaction(function () use ($user, $plan, $event) {
                // Deactivate current active plans
                $user->plans()->updateExistingPivotAttributes(
                    $user->plans()->wherePivot('status', 'active')->pluck('user_plans.id'), 
                    ['status' => 'inactive']
                );

                $startedAt = now();
                // RevenueCat provides expiration dates in the event
                $expiresAt = isset($event['expiration_at_ms']) 
                    ? \Carbon\Carbon::createFromTimestampMs($event['expiration_at_ms']) 
                    : $startedAt->copy()->addDays($plan->duration);

                $user->plans()->attach($plan->id, [
                    'id' => \Illuminate\Support\Str::uuid(),
                    'started_at' => $startedAt,
                    'expires_at' => $expiresAt,
                    'status' => 'active',
                    'auto_renew' => true, // RevenueCat handles renewal
                ]);
            });
        }
    }

    protected function handleSubscriptionInactive(User $user, $productId, $event)
    {
        // Deactivate all active plans for this user
        $user->plans()->updateExistingPivotAttributes(
            $user->plans()->wherePivot('status', 'active')->pluck('user_plans.id'), 
            ['status' => 'inactive']
        );
    }
}
