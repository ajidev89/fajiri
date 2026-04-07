<?php

namespace App\Services;

use Exception;

class StripeService
{
    /**
     * Create a connected account for the user.
     */
    public function createConnectedAccount($user)
    {
        // Placeholder implementation
        // $user->update(['stripe_connected_id' => 'acct_placeholder']);
        return (object)['id' => 'acct_placeholder'];
    }

    /**
     * Add a bank account to the connected user.
     */
    public function bankAccount($user, array $data)
    {
        // Placeholder implementation
        return (object)['id' => 'ba_placeholder'];
    }

    /**
     * Transfer funds to the connected account.
     */
    public function transfer(array $data)
    {
        // Placeholder implementation
        return (object)['id' => 'tr_placeholder'];
    }
}
