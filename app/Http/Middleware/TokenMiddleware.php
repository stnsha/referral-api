<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ODBController;
use Closure;
use Illuminate\Http\Request;
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
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return response()->json(['message' => 'Authorization header is required.'], Response::HTTP_UNAUTHORIZED);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        $payload = ODBController::verifyJWT($token);

        if (!$payload) {
            return response()->json(['message' => 'Invalid or expired token.'], Response::HTTP_UNAUTHORIZED);
        }

        $request->merge(['jwt_payload' => $payload]);

        return $next($request);
    }
}
