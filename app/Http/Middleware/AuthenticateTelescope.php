<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTelescope
{
    /**
     * Require an active admin session, Sanctum token, or HTTP basic login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->resolveUser($request);

        if (! $user instanceof User || ! $user->can('viewTelescope')) {
            return $this->denied($user instanceof User);
        }

        Auth::guard('web')->setUser($user);

        return $next($request);
    }

    private function resolveUser(Request $request): ?User
    {
        $current = Auth::user();

        if ($current instanceof User) {
            return $current;
        }

        if ($request->bearerToken()) {
            $tokenUser = Auth::guard('sanctum')->user();

            if ($tokenUser instanceof User) {
                return $tokenUser;
            }
        }

        $email = $request->getUser();

        if (! is_string($email) || $email === '') {
            return null;
        }

        if (! Auth::guard('web')->once([
            'email' => $email,
            'password' => (string) $request->getPassword(),
        ])) {
            return null;
        }

        $basicUser = Auth::guard('web')->user();

        return $basicUser instanceof User ? $basicUser : null;
    }

    private function denied(bool $authenticated): Response
    {
        if ($authenticated) {
            return response('Forbidden.', 403);
        }

        return response('Unauthorized.', 401, [
            'WWW-Authenticate' => 'Basic realm="Telescope"',
        ]);
    }
}
