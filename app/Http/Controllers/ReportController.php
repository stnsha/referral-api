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
     * @OA\Post(
     *     path="/api/report",
     *     operationId="generateReport",
     *     tags={"Reports"},
     *     summary="Generate and export referral report",
     *     description="Generate a comprehensive Excel report of referral histories with optional filtering parameters",
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="business_unit_id",
     *                 type="integer",
     *                 nullable=true,
     *                 example=null,
     *                 description="Filter by specific business unit ID"
     *             ),
     *             @OA\Property(
     *                 property="location",
     *                 type="integer",
     *                 nullable=true,
     *                 example=103,
     *                 description="Filter by location ID"
     *             ),
     *             @OA\Property(
     *                 property="is_external",
     *                 type="boolean",
     *                 example=false,
     *                 description="Filter for external referees only"
     *             ),
     *             @OA\Property(
     *                 property="priority",
     *                 type="integer",
     *                 nullable=true,
     *                 example=null,
     *                 description="Filter by priority level (1=Low, 2=Medium, 3=High, 4=Critical)"
     *             ),
     *             @OA\Property(
     *                 property="is_referred",
     *                 type="boolean",
     *                 example=false,
     *                 description="Filter for referrals that have been referred (more than 2 history entries)"
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="integer",
     *                 nullable=true,
     *                 example=null,
     *                 description="Filter by referral status (1=Open, 2=In Progress, 3=Referred, 4=Closed)"
     *             ),
     *             @OA\Property(
     *                 property="month",
     *                 type="integer",
     *                 nullable=true,
     *                 example=null,
     *                 description="Filter by month (1-12)"
     *             ),
     *             @OA\Property(
     *                 property="year",
     *                 type="integer",
     *                 nullable=true,
     *                 example=null,
     *                 description="Filter by year"
     *             ),
     *             example={
     *                 "business_unit_id": null,
     *                 "location": 103,
     *                 "is_external": false,
     *                 "priority": null,
     *                 "is_referred": false,
     *                 "status": null,
     *                 "month": null,
     *                 "year": null
     *             }
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
        try {
            $groupedResults = $this->getFilterResults($request->all());

            if ($groupedResults != null) {
                // Convert to indexed array
                $results = array_values($groupedResults);

                try {
                    // Generate unique filename
                    $fileName = 'referral_report_' . date('Y-m-d_H-i-s') . '.xlsx';
                    $filePath = 'report/' . $fileName;

                    // Ensure the directory exists
                    $fullPath = storage_path('app/public/report');
                    if (!file_exists($fullPath)) {
                        mkdir($fullPath, 0755, true);
                    }

                    // Store the Excel file in storage/app/public/report directory
                    Excel::store(new ReportExport($results), $filePath, 'public');

                    // Verify file was created
                    if (!file_exists(storage_path('app/public/' . $filePath))) {
                        throw new Exception('Failed to create Excel file');
                    }

                    return response()->json([
                        'download_url' => asset('storage/' . $filePath),
                    ], 200);
                } catch (LaravelExcelException $e) {
                    Log::error('Excel generation error: ' . $e->getMessage());
                    return response()->json([
                        'error' => 'Failed to generate Excel file',
                        'message' => 'There was an error creating the report file'
                    ], 500);
                } catch (Exception $e) {
                    Log::error('File creation error: ' . $e->getMessage());
                    return response()->json([
                        'error' => 'Failed to create report file',
                        'message' => 'Please check server permissions and try again'
                    ], 500);
                }
            }

            return response()->json([
                'download_url' => null
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation error',
                'message' => 'Invalid input parameters',
                'details' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error in ReportController: ' . $e->getMessage());
            return response()->json([
                'error' => 'An unexpected error occurred',
                'message' => 'Please try again later or contact support'
            ], 500);
        }
    }

    private function getFilterResults($request)
    {
        $businessUnitId = $request[0]['business_unit_id'] ?? null;
        $locationId = $request[0]['location'] ?? null;
        $isExternal = $request[0]['is_external'] ?? false;
        $priority = $request[0]['priority'] ?? null;
        $isReferred = $request[0]['is_referred'] ?? false;
        $status = $request[0]['status'] ?? null;
        $month = $request[0]['month'] ?? null;
        $year = $request[0]['year'] ?? null;

        // Check if all filter parameters are null/false
        $hasFilters = $businessUnitId || $locationId || $isExternal || $priority || $isReferred || $status || $month || $year;

        try {
            if (!$hasFilters) {
                // Get ALL ReferralHistory records when no filters are applied
                $allReferralHistories = ReferralHistory::with([
                    'referral',
                    'business_unit',
                    'referral_details.form.form_details'
                ])
                    ->orderBy('referral_id')
                    ->orderBy('sequence')
                    ->get()
                    ->makeHidden(['deleted_at', 'updated_at', 'created_at']);
            } else {
                // Apply filters when parameters are provided
                $rhQuery = ReferralHistory::with(['referral']);

                if ($businessUnitId) {
                    $rhQuery->where('business_unit_id', $businessUnitId);
                }

                if ($locationId) {
                    $rhQuery->where('location', $locationId);
                }

                if ($isExternal) {
                    $rhQuery->whereNotNull('external_referee_id');
                }

                if ($priority) {
                    $rhQuery->whereHas('referral', function ($query) use ($priority) {
                        $query->where('priority', $priority);
                    });
                }

                if ($isReferred) {
                    $rhQuery->whereIn('referral_id', function ($query) {
                        $query->select('referral_id')
                            ->from('referral_histories')
                            ->groupBy('referral_id')
                            ->havingRaw('COUNT(*) > 2');
                    });
                }

                if ($status) {
                    $rhQuery->whereHas('referral', function ($query) use ($status) {
                        $query->where('status', $status);
                    });
                }

                // Add month and year filtering on referral table
                if ($month || $year) {
                    $rhQuery->whereHas('referral', function ($query) use ($month, $year) {
                        if ($month) {
                            $query->whereMonth('created_at', $month);
                        }
                        if ($year) {
                            $query->whereYear('created_at', $year);
                        }
                    });
                }

                // Get the referral_ids that match the criteria
                $matchingReferralIds = $rhQuery->pluck('referral_id')->unique();

                // Now get ALL ReferralHistory records for those referral_ids
                $allReferralHistories = ReferralHistory::with([
                    'referral',
                    'business_unit',
                    'referral_details.form.form_details'
                ])
                    ->whereIn('referral_id', $matchingReferralIds)
                    ->orderBy('referral_id')
                    ->orderBy('sequence')
                    ->get()
                    ->makeHidden(['deleted_at', 'updated_at', 'created_at']);
            }
        } catch (QueryException $e) {
            Log::error('Database query error in ReportController: ' . $e->getMessage());
            return response()->json([
                'error' => 'Database error occurred while fetching referral data',
                'message' => 'Please check your filter parameters and try again'
            ], 500);
        }

        // Check if no data found
        if ($allReferralHistories->isEmpty()) {
            return null;
        }

        // Group by referral_id and process the data
        $groupedResults = [];
        try {
            foreach ($allReferralHistories as $rh) {
                // Process referral details to get actual values
                $processedReferralDetails = [];
                if ($rh->referral_details) {
                    foreach ($rh->referral_details as $detail) {
                        try {
                            $form = $detail->form;
                            $actualValue = $detail->value;

                            // Check if form type is checkbox or radio
                            if ($form && $form->form_details) {
                                $formDetail = $form->form_details->first();
                                if ($formDetail && in_array($formDetail->field_type, ['checkbox', 'radio'])) {
                                    // Find the actual field_value from form_details using the ID stored in value
                                    $selectedFormDetail = $form->form_details->where('id', $detail->value)->first();
                                    if ($selectedFormDetail) {
                                        $actualValue = $selectedFormDetail->field_value;
                                    }
                                }
                            }

                            $processedReferralDetails[] = [
                                'form_name' => $form ? $form->label_name : null,
                                'value' => $actualValue,
                            ];
                        } catch (Exception $e) {
                            Log::warning('Error processing referral detail: ' . $e->getMessage());
                            continue;
                        }
                    }
                }

                $historyData = [
                    'staff_id' => $rh->staff_id,
                    'business_unit' => $rh->business_unit ? $rh->business_unit->name : null,
                    'location' => $rh->location,
                    'sequence' => $rh->sequence,
                    'referral_reason' => $rh->referral_reason,
                    'referral_condition' => $rh->referral_condition,
                    'medical_history' => $rh->medical_history,
                    'additional_remarks' => $rh->additional_remarks,
                    'is_filled' => $rh->is_filled,
                    'external_referee_id' => $isExternal ? $rh->external_referee_id : null,
                    'referral_details' => $processedReferralDetails
                ];

                // Remove null values when isExternal is false
                if (!$isExternal) {
                    $historyData = array_filter($historyData, function ($value, $key) {
                        return !($key === 'external_referee_id' && $value === null);
                    }, ARRAY_FILTER_USE_BOTH);
                }

                // Group by referral_id
                if (!isset($groupedResults[$rh->referral_id])) {
                    try {
                        // Prepare referral data with status name
                        $referralData = $rh->referral ? $rh->referral->makeHidden(['id', 'deleted_at'])->toArray() : null;
                        if ($referralData && isset($referralData['status'])) {
                            $referralData['status_name'] = getStatus($referralData['status']);
                            unset($referralData['status']); // Remove the numeric status field
                        }

                        // Convert created_at and updated_at to formatted datetime strings
                        if ($referralData && isset($referralData['created_at'])) {
                            $referralData['created_at'] = Carbon::parse($referralData['created_at'])->format('l, d M Y');
                        }
                        if ($referralData && isset($referralData['updated_at'])) {
                            $referralData['updated_at'] = Carbon::parse($referralData['updated_at'])->format('l, d M Y');
                        }

                        $groupedResults[$rh->referral_id] = [
                            'referral_id' => createRefId($rh->referral_id),
                            'referral' => $referralData,
                            'referral_histories' => []
                        ];
                    } catch (Exception $e) {
                        Log::warning('Error processing referral data for ID ' . $rh->referral_id . ': ' . $e->getMessage());
                        // Continue with default structure
                        $groupedResults[$rh->referral_id] = [
                            'referral_id' => $rh->referral_id,
                            'referral' => null,
                            'referral_histories' => []
                        ];
                    }
                }

                $groupedResults[$rh->referral_id]['referral_histories'][] = $historyData;
            }

            return $groupedResults;
        } catch (Exception $e) {
            Log::error('Error processing referral histories: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error occurred while processing referral data',
                'message' => 'Please try again later'
            ], 500);
        }
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

    public function summary($businessUnitId)
    {
        // Get referral histories with all necessary relationships
        $referralHistories = ReferralHistory::with([
            'referral',
            'business_unit',
            'external_referee'
        ])->where('business_unit_id', $businessUnitId)->get();

        // Initialize statistics with all possible status values set to 0
        $stats = [
            'total_referrals' => 0,
            'sent_received' => [
                'sent' => 0,
                'received' => 0
            ],
            'staff_involvement' => [],
            'status' => [
                'Open' => 0,
                'In Progress' => 0,
                'Referred' => 0,
                'Closed' => 0,
                'Not Present' => 0,
                'Submitted' => 0
            ],
            'priority' => [
                'High' => 0,      // Priority 1
                'Standard' => 0   // Priority 2
            ],
            'location_details' => [],
            'location_summary' => [],
            'external_referrals' => 0,
            'referred_referrals' => 0
        ];

        // Get unique referral IDs to count total referrals
        $uniqueReferralIds = $referralHistories->pluck('referral_id')->unique();
        $stats['total_referrals'] = $uniqueReferralIds->count();

        // Count external referrals (where external_referee_id is not null)
        $stats['external_referrals'] = $referralHistories->whereNotNull('external_referee_id')->count();

        // Count referred referrals (referrals with more than 2 sequences)
        $referralSequenceCounts = $referralHistories->groupBy('referral_id')->map(function ($histories) {
            return $histories->count();
        });
        $stats['referred_referrals'] = $referralSequenceCounts->filter(function ($count) {
            return $count > 2;
        })->count();

        // Process each referral history
        foreach ($referralHistories as $history) {
            // Count sent/received based on sequence
            // sequence 1 = initial referee (sent), sequence 2+ = receiver
            if ($history->sequence == 1) {
                $stats['sent_received']['sent']++;
            } else {
                $stats['sent_received']['received']++;
            }

            // Process staff involvement
            if ($history->staff_id) {
                $staffId = $history->staff_id;
                if (!isset($stats['staff_involvement'][$staffId])) {
                    $stats['staff_involvement'][$staffId] = [
                        'sent' => 0,
                        'received' => 0,
                        'total' => 0
                    ];
                }

                if ($history->sequence == 1) {
                    $stats['staff_involvement'][$staffId]['sent']++;
                } else {
                    $stats['staff_involvement'][$staffId]['received']++;
                }
                $stats['staff_involvement'][$staffId]['total']++;
            }

            // Process location distribution
            if ($history->location) {
                $location = $history->location;
                if (!isset($stats['location_details'][$location])) {
                    $stats['location_details'][$location] = [
                        'sent' => 0,
                        'received' => 0,
                        'total' => 0
                    ];
                }

                if ($history->sequence == 1) {
                    $stats['location_details'][$location]['sent']++;
                } else {
                    $stats['location_details'][$location]['received']++;
                }
                $stats['location_details'][$location]['total']++;

                // Also add to location summary (just location and total)
                if (!isset($stats['location_summary'][$location])) {
                    $stats['location_summary'][$location] = 0;
                }
                $stats['location_summary'][$location]++;
            }

            // Process referral-specific data (status and priority)
            // Only count once per referral to avoid duplicates
            if ($history->referral && $history->sequence == 1) {
                // Status distribution using getStatus helper
                $statusName = getStatus($history->referral->status);
                $stats['status'][$statusName]++;

                // Priority distribution (1 = High, 2 = Standard)
                $priority = $history->referral->priority;
                if ($priority == 1) {
                    $stats['priority']['High']++;
                } elseif ($priority == 2) {
                    $stats['priority']['Standard']++;
                }
            }
        }

        // Sort arrays for better readability
        arsort($stats['staff_involvement']);
        ksort($stats['location_details']);
        arsort($stats['location_summary']); // Sort by total count descending
        // Don't sort status_distribution to maintain the logical order

        return response()->json([
            'statistics' => $stats,
        ], 200);
    }
}
