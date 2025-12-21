<?php

namespace App\Http\Middleware;

use App\Http\Traits\ResponseTrait;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyAppMiddleware
{
    use ResponseTrait;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appName = $request->header('X-App-Name');

        $validAppNames = ['personal', 'business', 'back-office'];

        if ($appName && in_array($appName, $validAppNames)) {
 
            $request->merge(['app_name' => $appName]);

            return $next($request);
        }

        return $this->handleErrorResponse("Add X-App-Name to the header", 401);
    }
}
