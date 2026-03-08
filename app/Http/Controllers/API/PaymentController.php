<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\PaymentRepositoryInterface;
use Illuminate\Http\Request;
use App\Http\Traits\AuthUserTrait;

class PaymentController extends Controller
{
    use AuthUserTrait;

    public function __construct(protected PaymentRepositoryInterface $paymentRepository)
    {}

    /**
     * Initialize Wallet Funding
     */
    public function initialize(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'email' => 'required|email',
        ]);

        return $this->paymentRepository->initialize($this->user(), $request->all());
    }

    /**
     * Verify Payment and Credit Wallet
     */
    public function verify(Request $request)
    {
        $reference = $request->reference;

        if (!$reference) {
            return response()->json(['status' => 'error', 'message' => 'No reference provided'], 400);
        }

        return $this->paymentRepository->verify($reference);
    }

    /**
     * Handle Paystack Webhook
     */
    public function webhook(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $payload = $request->getContent();
        $event = json_decode($payload, true);

        return $this->paymentRepository->handleWebhook($event, $signature, $payload);
    }
}
