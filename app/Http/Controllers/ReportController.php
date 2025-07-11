<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\Referral;
use App\Models\ReferralHistory;
use Illuminate\Http\Request;

class ReportController extends Controller
{

    public function chart()
    {
        $referrals = Referral::with(['referral_histories.business_unit'])->get();
        $businessUnits = BusinessUnit::all();

        // Initialize all business units with zero counts
        $results = [];
        foreach ($businessUnits as $bu) {
            $results[$bu->name] = [
                [
                    'sent' => 0,
                    'received' => 0
                ]
            ];
        }

        if ($referrals->isEmpty()) {
            return response()->json($results, 200);
        }

        foreach ($referrals as $referral) {
            foreach ($referral->referral_histories as $rh) {
                if ($rh->business_unit != null) {
                    $businessUnit = $rh->business_unit->name;

                    // Count sent (sequence == 1) or received (sequence != 1)
                    if ($rh->sequence == 1) {
                        $results[$businessUnit][0]['sent']++;
                    } else {
                        $results[$businessUnit][0]['received']++;
                    }
                }
            }
        }

        return response()->json($results, 200);
    }

    public function dashboard()
    {
        $referrals = Referral::with(['referral_histories.business_unit'])->get();
        $businessUnits = BusinessUnit::all();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        // Count referrals by status
        $statusCounts = [
            'open' => 0,
            'in_progress' => 0,
            'referred' => 0,
            'closed' => 0
        ];

        // Initialize business unit counts
        $businessUnitCounts = [];
        foreach ($businessUnits as $bu) {
            $businessUnitCounts[$bu->id] = [
                'id' => $bu->id,
                'name' => $bu->name,
                'count' => 0
            ];
        }

        foreach ($referrals as $referral) {
            // Count by status (1=Open, 2=In Progress, 3=Referred, 4=Closed)
            switch ($referral->status) {
                case 1:
                    $statusCounts['open']++;
                    break;
                case 2:
                    $statusCounts['in_progress']++;
                    break;
                case 3:
                    $statusCounts['referred']++;
                    break;
                case 4:
                    $statusCounts['closed']++;
                    break;
            }

            // Count referrals per business unit
            foreach ($referral->referral_histories as $rh) {
                if ($rh->business_unit_id && isset($businessUnitCounts[$rh->business_unit_id])) {
                    $businessUnitCounts[$rh->business_unit_id]['count']++;
                }
            }
        }

        return response()->json([
            'total_referral' => $referrals->count(),
            'referrals' => $statusCounts,
            'total_business_unit' => $businessUnits->count(),
            'business_units' => array_values($businessUnitCounts)
        ], 200);
    }

    public function businessUnit($buId)
    {
        // $businessUnit = BusinessUnit::findOrFail($buId);
        $referralHistories = ReferralHistory::where('business_unit_id', $buId)->get();

        // $results = [];

        // foreach($referralHistories as $rh)
        // {

        // }

    }
}
