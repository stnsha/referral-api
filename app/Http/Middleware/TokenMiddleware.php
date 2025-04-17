<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization');

        if(!$token) {
            return response()->json(['message' => 'Token is required.'], Response::HTTP_UNAUTHORIZED);
        }

        $cacheKey = "token:$token";
        $isValid = Cache::remember($cacheKey, 3600, function() use 
        ($token) {
            return DB::table('token')->where('value', $token)->exists();
        });

        if(!$isValid) {
            return response()->json(['message' => 'Invalid token.'], Response::HTTP_UNAUTHORIZED);
        }
        return $next($request);
    }
}
