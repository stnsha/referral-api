<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/report/chart",
     *     summary="Get chart data for Total Referral by Business Unit",
     *     tags={"Reports"},
     *     @OA\Response(
     *         response=200,
     *         description="Chart data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="labels", type="array", @OA\Items(type="string"), example={"Emergency Department", "Cardiology", "Neurology"}),
     *             @OA\Property(property="datasets", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="options", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="No referrals found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No results."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function chart()
    {
        $referrals = Referral::with(['referral_histories.business_unit'])->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        // Count referrals by business unit
        $businessUnitCounts = [];

        foreach ($referrals as $referral) {
            foreach ($referral->referral_histories as $history) {
                if ($history->business_unit && $history->sequence == 1) {
                    $businessUnitName = $history->business_unit->name;
                    if (!isset($businessUnitCounts[$businessUnitName])) {
                        $businessUnitCounts[$businessUnitName] = 0;
                    }
                    $businessUnitCounts[$businessUnitName]++;
                }
            }
        }

        // Prepare chart data
        $labels = array_keys($businessUnitCounts);
        $values = array_values($businessUnitCounts);

        // Define colors for business units
        $colors = ["#1e4384", "#17b2a6", "#194621", "#19b8d3", "#21a2dc", "orange", "#204296"];
        $barColors = [];

        for ($i = 0; $i < count($labels); $i++) {
            $barColors[] = $colors[$i % count($colors)];
        }

        $chartData = [
            'labels' => $labels,
            'datasets' => [[
                'backgroundColor' => $barColors,
                'data' => $values
            ]],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'legend' => [
                    'display' => false
                ],
                'scales' => [
                    'xAxes' => [[
                        'scaleLabel' => [
                            'display' => true,
                            'labelString' => 'Business Unit'
                        ]
                    ]],
                    'yAxes' => [[
                        'scaleLabel' => [
                            'display' => true,
                            'labelString' => 'Total Referral'
                        ],
                        'ticks' => [
                            'beginAtZero' => true
                        ]
                    ]]
                ],
                'title' => [
                    'display' => true,
                    'text' => 'Total Referral by Business Unit'
                ]
            ]
        ];

        return response()->json($chartData, 200);
    }

    /**
     * @OA\Get(
     *     path="/api/report/business-unit",
     *     summary="Get referral report grouped by business units",
     *     tags={"Reports"},
     *     @OA\Response(
     *         response=200,
     *         description="Business unit report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="business_unit", type="string", example="Emergency Department"),
     *                 @OA\Property(property="total_referrals", type="integer", example=25),
     *                 @OA\Property(property="status_breakdown", type="object")
     *             )),
     *             @OA\Property(property="total_business_units", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="No referrals found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No results."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/report/priority",
     *     summary="Get referral report grouped by priority levels",
     *     tags={"Reports"},
     *     @OA\Response(
     *         response=200,
     *         description="Priority report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="priority", type="string", example="High"),
     *                 @OA\Property(property="count", type="integer", example=15),
     *                 @OA\Property(property="percentage", type="number", format="float", example=35.5)
     *             )),
     *             @OA\Property(property="total_referrals", type="integer", example=42)
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="No referrals found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No results."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/report/status",
     *     summary="Get referral report grouped by status",
     *     tags={"Reports"},
     *     @OA\Response(
     *         response=200,
     *         description="Status report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="status", type="string", example="In Progress"),
     *                 @OA\Property(property="count", type="integer", example=18),
     *                 @OA\Property(property="percentage", type="number", format="float", example=42.86)
     *             )),
     *             @OA\Property(property="total_referrals", type="integer", example=42)
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="No referrals found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No results."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/report/time-period",
     *     summary="Get referral report grouped by time periods",
     *     tags={"Reports"},
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Time period grouping (weekly or monthly)",
     *         @OA\Schema(type="string", enum={"weekly", "monthly"}, example="monthly")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Time period report retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="period", type="string", example="July 2024"),
     *                 @OA\Property(property="count", type="integer", example=12),
     *                 @OA\Property(property="date_key", type="string", example="2024-07")
     *             )),
     *             @OA\Property(property="period_type", type="string", example="monthly"),
     *             @OA\Property(property="total_periods", type="integer", example=6)
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="No referrals found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No results."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
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
