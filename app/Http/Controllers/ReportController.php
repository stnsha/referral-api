<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        $referrals = Referral::with(['referral_details', 'referral_histories'])->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        $result = [];

        foreach ($referrals as $referral) {
        }
    }
}
