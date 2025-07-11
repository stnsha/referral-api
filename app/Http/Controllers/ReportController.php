<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use Illuminate\Http\Request;

class ReportController extends Controller
{

    public function chart()
    {

        $referrals = Referral::with(['referral_histories.business_unit'])->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        $results = [];

        foreach ($referrals as $referral) {
            foreach ($referral->referral_histories as $rh) {
                if ($rh->business_unit != null) {
                    $businessUnit = $rh->business_unit->name;

                    // Initialize business unit if not exists
                    if (!isset($results[$businessUnit])) {
                        $results[$businessUnit] = [
                            [
                                'sent' => 0,
                                'received' => 0
                            ]
                        ];
                    }

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

    public function reportByBusinessUnit()
    {
        $referrals = Referral::with(['referral_histories.business_unit'])->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        $businessUnitReport = [];

        foreach ($referrals as $referral) {
            foreach ($referral->referral_histories as $history) {
                if ($history->business_unit && $history->sequence == 1) {
                    $businessUnitName = $history->business_unit->name;

                    if (!isset($businessUnitReport[$businessUnitName])) {
                        $businessUnitReport[$businessUnitName] = [
                            'business_unit' => $businessUnitName,
                            'total_referrals' => 0,
                            'status_breakdown' => [
                                'open' => 0,
                                'in_progress' => 0,
                                'referred' => 0,
                                'closed' => 0
                            ]
                        ];
                    }

                    $businessUnitReport[$businessUnitName]['total_referrals']++;

                    // Count by status
                    switch ($referral->status) {
                        case 1:
                            $businessUnitReport[$businessUnitName]['status_breakdown']['open']++;
                            break;
                        case 2:
                            $businessUnitReport[$businessUnitName]['status_breakdown']['in_progress']++;
                            break;
                        case 3:
                            $businessUnitReport[$businessUnitName]['status_breakdown']['referred']++;
                            break;
                        case 4:
                            $businessUnitReport[$businessUnitName]['status_breakdown']['closed']++;
                            break;
                    }
                }
            }
        }

        return response()->json([
            'data' => array_values($businessUnitReport),
            'total_business_units' => count($businessUnitReport)
        ], 200);
    }

    public function reportByPriority()
    {
        $referrals = Referral::with(['referral_histories.business_unit'])->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        $priorityReport = [
            1 => ['priority' => 'Urgent', 'count' => 0, 'percentage' => 0],
            2 => ['priority' => 'Standard', 'count' => 0, 'percentage' => 0],
        ];

        $totalReferrals = $referrals->count();

        foreach ($referrals as $referral) {
            if (isset($priorityReport[$referral->priority])) {
                $priorityReport[$referral->priority]['count']++;
            }
        }

        // Calculate percentages
        foreach ($priorityReport as $priority => &$data) {
            $data['percentage'] = $totalReferrals > 0 ? round(($data['count'] / $totalReferrals) * 100, 2) : 0;
        }

        return response()->json([
            'data' => array_values($priorityReport),
            'total_referrals' => $totalReferrals
        ], 200);
    }

    public function reportByStatus()
    {
        $referrals = Referral::all();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        $statusReport = [
            1 => ['status' => 'Open', 'count' => 0, 'percentage' => 0],
            2 => ['status' => 'In Progress', 'count' => 0, 'percentage' => 0],
            3 => ['status' => 'Referred', 'count' => 0, 'percentage' => 0],
            4 => ['status' => 'Closed', 'count' => 0, 'percentage' => 0]
        ];

        $totalReferrals = $referrals->count();

        foreach ($referrals as $referral) {
            if (isset($statusReport[$referral->status])) {
                $statusReport[$referral->status]['count']++;
            }
        }

        // Calculate percentages
        foreach ($statusReport as $status => &$data) {
            $data['percentage'] = $totalReferrals > 0 ? round(($data['count'] / $totalReferrals) * 100, 2) : 0;
        }

        return response()->json([
            'data' => array_values($statusReport),
            'total_referrals' => $totalReferrals
        ], 200);
    }

    public function reportByTimePeriod(Request $request)
    {
        $period = $request->query('period', 'monthly'); // weekly or monthly
        $referrals = Referral::orderBy('created_at')->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        $timeReport = [];

        foreach ($referrals as $referral) {
            $date = $referral->created_at;

            if ($period === 'weekly') {
                // Group by week (Year-Week format)
                $timeKey = $date->format('Y-W');
                $displayDate = 'Week ' . $date->format('W, Y');
            } else {
                // Group by month (Year-Month format)
                $timeKey = $date->format('Y-m');
                $displayDate = $date->format('F Y');
            }

            if (!isset($timeReport[$timeKey])) {
                $timeReport[$timeKey] = [
                    'period' => $displayDate,
                    'count' => 0,
                    'date_key' => $timeKey
                ];
            }

            $timeReport[$timeKey]['count']++;
        }

        // Sort by date key
        ksort($timeReport);

        return response()->json([
            'data' => array_values($timeReport),
            'period_type' => $period,
            'total_periods' => count($timeReport)
        ], 200);
    }
}
