<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthODBController extends Controller
{
    public function auth(Request $request)
    {
        $validated = $request->validate([
            'staff_id'         => 'required|integer',
            'business_unit_id' => 'nullable|integer',
            'status_semasa'    => 'required|string',
            'outlet'           => 'nullable|array',
            'outlet.*'         => 'integer',
            'referral'         => 'nullable|integer|in:0,1,2',
        ]);

        $payload = [
            'iss'              => config('app.url'),
            'iat'              => time(),
            'exp'              => time() + (60 * 60 * 24),
            'staff_id'         => $validated['staff_id'],
            'business_unit_id' => $validated['business_unit_id'] ?? null,
            'status_semasa'    => $validated['status_semasa'],
            'outlet'           => $validated['outlet'] ?? [],
            'referral'         => $validated['referral'] ?? 0,
        ];

        $token = ODBController::generateJWT($payload);

        return response()->json([
            'token'      => $token,
            'expires_in' => 86400,
        ], 200);
    }
}
