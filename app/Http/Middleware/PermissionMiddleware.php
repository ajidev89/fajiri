<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!auth()->check()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated access'
            ], 401);
        }

        $user = auth()->user();

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden: You do not have the required permission: ' . $permission
            ], 403);
        }

        return $next($request);
    }
}
