<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\GoogleRepositoryInterface;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;

class GoogleRepository implements GoogleRepositoryInterface
{

    public function __construct(private User $user)
    {}

    public function generateGoogleUrl()
    {
        $url = Socialite::driver('google')
            ->redirect()
            ->getTargetUrl();

        return response()->json([
            'url' => $url
        ]); 
    }

    public function handleGoogleCallback()
    {
        $googleUser = Socialite::driver('google')->user();

        $user = $this->user->updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),   
            ]
        );

        $name = $googleUser->getName();
        $nameArray = explode(' ', $name);
        $firstName = $nameArray[0];
        $lastName = $nameArray[count($nameArray) - 1];
        $middleName = count($nameArray) > 2 ? implode(' ', array_slice($nameArray, 1, -1)) : null;

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'middle_name' => $middleName,
                'avatar' => $googleUser->getAvatar(),
            ]
        );

        $token = $user->createToken($user->email)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
}