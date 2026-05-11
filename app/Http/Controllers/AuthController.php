<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ODBController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ], [
            'email.required' => 'Email is required.',
            'password.required' => 'Password is required.',
        ]);

        if ($validated) {
            $credentials = $request->only('email', 'password');

            if (Auth::attempt($credentials)) {
                return redirect()->intended('api/documentation');
            }
        }
        return back()->withErrors(['login' => 'Invalid email or password. Please contact admin.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function tokenAuth(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string',
        ]);

        $record = DB::table('api_token')
            ->where('value', $request->input('api_key'))
            ->first();

        if (!$record) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $payload = [
            'iss'              => config('app.url'),
            'iat'              => time(),
            'exp'              => time() + (60 * 60 * 24),
            'staff_id'         => 0,
            'business_unit_id' => null,
            'status_semasa'    => 'api',
            'outlet'           => [],
            'referral'         => 1,
        ];

        return response()->json([
            'token'      => ODBController::generateJWT($payload),
            'expires_in' => 86400,
        ], 200);
    }
}
