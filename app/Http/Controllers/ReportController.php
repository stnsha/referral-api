<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\Referral;
use App\Models\ReferralHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\ReportExport;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="Reports",
 *     description="API endpoints for generating and managing reports"
 * )
 */
class ReportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/report",
     *     operationId="generateReport",
     *     tags={"Reports"},
     *     summary="Generate and export referral report",
     *     description="Generate a comprehensive Excel report of referral histories with optional filtering parameters",
     *     @OA\Parameter(
     *         name="business_unit_id",
     *         in="query",
     *         description="Filter by specific business unit ID",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="location",
     *         in="query",
     *         description="Filter by location",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             example="Main Office"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="is_external",
     *         in="query",
     *         description="Filter for external referees only",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean",
     *             example=false
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="priority",
     *         in="query",
     *         description="Filter by priority level (1=Low, 2=Medium, 3=High, 4=Critical)",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             enum={1, 2, 3, 4},
     *             example=2
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="is_referred",
     *         in="query",
     *         description="Filter for referrals that have been referred (more than 2 history entries)",
     *         required=false,
     *         @OA\Schema(
     *             type="boolean",
     *             example=false
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by referral status (1=Open, 2=In Progress, 3=Referred, 4=Closed)",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             enum={1, 2, 3, 4},
     *             example=1
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         description="Filter by month (1-12)",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             minimum=1,
     *             maximum=12,
     *             example=3
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         description="Filter by year",
     *         required=false,
     *         @OA\Schema(
     *             type="integer",
     *             example=2024
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report operation completed",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="download_url",
     *                         type="string",
     *                         example="http://localhost:8000/storage/report/referral_report_2024-03-15_14-30-25.xlsx",
     *                         description="URL to download the generated Excel report"
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     type="object",
     *                     @OA\Property(
     *                         property="message",
     *                         type="string",
     *                         example="No referral histories found matching the specified criteria"
     *                     ),
     *                     @OA\Property(
     *                         property="download_url",
     *                         type="string",
     *                         nullable=true,
     *                         example=null
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Validation error"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Invalid input parameters"
     *             ),
     *             @OA\Property(
     *                 property="details",
     *                 type="object",
     *                 example={"month": {"The month field must be between 1 and 12."}}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Database error occurred while fetching referral data"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Please check your filter parameters and try again"
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        // ... existing code ...
    }

    /**
     * @OA\Get(
     *     path="/api/report/chart",
     *     operationId="getReportChart",
     *     tags={"Reports"},
     *     summary="Get referral chart data by business unit",
     *     description="Retrieve sent and received referral counts grouped by business unit for chart visualization",
     *     @OA\Response(
     *         response=200,
     *         description="Chart data retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             additionalProperties={
     *                 "type": "array",
     *                 "items": {
     *                     "type": "object",
     *                     @OA\Property(
     *                         property="sent",
     *                         type="integer",
     *                         example=15,
     *                         description="Number of referrals sent by this business unit"
     *                     ),
     *                     @OA\Property(
     *                         property="received",
     *                         type="integer",
     *                         example=8,
     *                         description="Number of referrals received by this business unit"
     *                     )
     *                 }
     *             },
     *             example={
     *                 "Emergency Department": {
     *                     {
     *                         "sent": 15,
     *                         "received": 8
     *                     }
     *                 },
     *                 "Cardiology": {
     *                     {
     *                         "sent": 5,
     *                         "received": 12
     *                     }
     *                 },
     *                 "Orthopedics": {
     *                     {
     *                         "sent": 3,
     *                         "received": 7
     *                     }
     *                 }
     *             }
     *         )
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/report/dashboard",
     *     operationId="getDashboardStats",
     *     tags={"Reports"},
     *     summary="Get dashboard statistics",
     *     description="Retrieve comprehensive statistics for dashboard including total referrals, status counts, and business unit statistics",
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard statistics retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="total_referral",
     *                 type="integer",
     *                 example=45,
     *                 description="Total number of referrals in the system"
     *             ),
     *             @OA\Property(
     *                 property="referrals",
     *                 type="object",
     *                 @OA\Property(
     *                     property="open",
     *                     type="integer",
     *                     example=12,
     *                     description="Number of open referrals"
     *                 ),
     *                 @OA\Property(
     *                     property="in_progress",
     *                     type="integer",
     *                     example=18,
     *                     description="Number of referrals in progress"
     *                 ),
     *                 @OA\Property(
     *                     property="referred",
     *                     type="integer",
     *                     example=8,
     *                     description="Number of referred referrals"
     *                 ),
     *                 @OA\Property(
     *                     property="closed",
     *                     type="integer",
     *                     example=7,
     *                     description="Number of closed referrals"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="total_business_unit",
     *                 type="integer",
     *                 example=5,
     *                 description="Total number of business units"
     *             ),
     *             @OA\Property(
     *                 property="business_units",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         example=1,
     *                         description="Business unit ID"
     *                     ),
     *                     @OA\Property(
     *                         property="name",
     *                         type="string",
     *                         example="Emergency Department",
     *                         description="Business unit name"
     *                     ),
     *                     @OA\Property(
     *                         property="count",
     *                         type="integer",
     *                         example=23,
     *                         description="Number of referrals associated with this business unit"
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="No referrals found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No results."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object"),
     *                 example={}
     *             )
     *         )
     *     )
     * )
     */
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
}
