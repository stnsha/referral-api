<?php

namespace App\Http\Controllers;

use App\Exports\ReportExport;
use App\Models\BusinessUnit;
use App\Models\Referral;
use App\Models\ReferralHierarchy;
use App\Traits\AccessControl;
use App\Traits\Octopus;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Exceptions\LaravelExcelException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * @OA\Tag(
 *     name="Reports",
 *     description="API endpoints for generating and managing reports"
 * )
 */
class ReportController extends Controller
{
    use AccessControl;
    use Octopus;

    /**
     * @OA\Post(
     *     path="/api/report",
     *     operationId="generateReport",
     *     tags={"Reports"},
     *     summary="Generate and export referral report",
     *     security={{"bearerAuth": {}}},
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
     *         description="Report generated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="filename",
     *                 type="string",
     *                 example="referral_report_2024-03-15_14-30-25.xlsx",
     *                 description="Generated filename for the report"
     *             ),
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
     *                 description="MIME type of the generated file"
     *             ),
     *             @OA\Property(
     *                 property="size",
     *                 type="integer",
     *                 example=51200,
     *                 description="File size in bytes"
     *             ),
     *             @OA\Property(
     *                 property="base64",
     *                 type="string",
     *                 format="byte",
     *                 example="UEsDBBQACgAAAAAAeXdLTQAAAAAAAAAAAAAAABYAAABfcmVscz8uaW5za1orcmVscy8uanM=",
     *                 description="Base64 encoded Excel file content"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No data found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="No referral histories found"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="No referral histories found matching the specified criteria"
     *             )
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
            // Get business unit from JWT payload
            $jwtPayload = $request->get('jwt_payload');

            // Check if user has write permission to export reports (referral 1 or 2 only)
            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can export reports.',
                ], 403);
            }

            $groupedResults = $this->getFilterResults($request);

            if ($groupedResults != null) {
                // Convert to indexed array
                $results = array_values($groupedResults);

                try {
                    // Generate unique filename
                    $fileName = 'referral_report_' . date('Y-m-d_H-i-s') . '.xlsx';

                    // Generate Excel file in memory and get base64 content
                    $excelContent = Excel::raw(new ReportExport($results), \Maatwebsite\Excel\Excel::XLSX);
                    $base64Content = base64_encode($excelContent);

                    // Calculate file size
                    $fileSize = strlen($excelContent);

                    return response()->json([
                        'filename' => $fileName,
                        'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'size' => $fileSize,
                        'base64' => $base64Content
                    ], 200);
                } catch (LaravelExcelException $e) {
                    Log::error('Excel generation error: ' . $e->getMessage());
                    return response()->json([
                        'error' => 'Failed to generate Excel file',
                        'message' => 'There was an error creating the report file'
                    ], 500);
                } catch (Throwable $e) {
                    Log::error('File creation error: ' . $e->getMessage());
                    return response()->json([
                        'error' => 'Failed to create report file',
                        'message' => 'Please check server permissions and try again'
                    ], 500);
                }
            }

            return response()->json([
                'error' => 'No referral histories found',
                'message' => 'No referral histories found matching the specified criteria'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => 'Validation error',
                'message' => 'Invalid input parameters',
                'details' => $e->errors()
            ], 422);
        } catch (Throwable $e) {
            Log::error('Unexpected error in ReportController: ' . $e->getMessage());
            return response()->json([
                'error' => 'An unexpected error occurred',
                'message' => 'Please try again later or contact support'
            ], 500);
        }
    }

    private function getFilterResults(Request $request)
    {
        $businessUnitId = $request->input('business_unit_id');
        $locationId = $request->input('location');
        $isExternal = filter_var($request->input('is_external', false), FILTER_VALIDATE_BOOLEAN);
        $priority = $request->input('priority');
        $isReferred = filter_var($request->input('is_referred', false), FILTER_VALIDATE_BOOLEAN);
        $status = $request->input('status');
        $month = $request->input('month');
        $year = $request->input('year');

        // Get business unit from JWT payload
        $jwtPayload = $request->get('jwt_payload');
        $userBusinessUnitId = $this->getBusinessUnitIdForFilter($jwtPayload);

        // If userBusinessUnitId is provided (not null), force filter by that business unit
        // Note: null means superadmin, should see all
        if ($userBusinessUnitId !== null) {
            $businessUnitId = $userBusinessUnitId;
        }

        // Check if all filter parameters are null/false
        $hasFilters = $businessUnitId || $locationId || $isExternal || $priority || $isReferred || $status || $month || $year;

        try {
            if (!$hasFilters) {
                // Get ALL ReferralHierarchy records when no filters are applied
                $allReferralHistories = ReferralHierarchy::with([
                    'referral',
                    'business_unit',
                    'referral_details.form.form_details',
                    'referral_create_form',
                    'referral_reply_form'
                ])
                    ->orderBy('referral_id')
                    ->orderBy('sequence')
                    ->get()
                    ->makeHidden(['deleted_at', 'updated_at', 'created_at']);
            } else {
                // Apply filters when parameters are provided
                $rhQuery = ReferralHierarchy::with(['referral']);

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
                            ->from('referral_hierarchies')
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
                            $query->whereMonth('created_at', (int) $month);
                        }
                        if ($year) {
                            $query->whereYear('created_at', (int) $year);
                        }
                    });
                }

                Log::info('Main query SQL: ' . $rhQuery->toSql());
                Log::info('Main query Bindings: ' . json_encode($rhQuery->getBindings()));


                // Get the referral_ids that match the criteria
                $matchingReferralIds = $rhQuery->pluck('referral_id')->unique();

                // Now get ALL ReferralHierarchy records for those referral_ids
                $allReferralHistories = ReferralHierarchy::with([
                    'referral',
                    'business_unit',
                    'referral_details.form.form_details',
                    'referral_create_form',
                    'referral_reply_form'
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
                        } catch (Throwable $e) {
                            Log::warning('Error processing referral detail: ' . $e->getMessage());
                            continue;
                        }
                    }
                }

                // Get form data from create and reply forms
                $createFormData = [];
                $replyFormData = [];

                if ($rh->referral_create_form) {
                    $createFormData = [
                        'referral_reason' => $rh->referral_create_form->referral_reason,
                        'referral_condition' => $rh->referral_create_form->referral_condition,
                        'medical_history' => $rh->referral_create_form->medical_history,
                    ];
                }

                if ($rh->referral_reply_form) {
                    $replyFormData = [
                        'post_diagnosis' => $rh->referral_reply_form->post_diagnosis,
                        'outcome' => $rh->referral_reply_form->outcome,
                        'feedback' => $rh->referral_reply_form->feedback,
                    ];
                }

                $historyData = [
                    'staff_id' => $rh->staff_id,
                    'business_unit' => $rh->business_unit ? $rh->business_unit->name : null,
                    'location' => $rh->location,
                    'sequence' => $rh->sequence,
                    'create_form' => $createFormData,
                    'reply_form' => $replyFormData,
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
                            $referralData['created_at'] = Carbon::parse(
                                $referralData['created_at'],
                                'UTC'
                            )->setTimezone('Asia/Kuala_Lumpur')
                                ->format('l, d M Y');
                        }

                        if ($referralData && isset($referralData['updated_at'])) {
                            $referralData['updated_at'] = Carbon::parse(
                                $referralData['updated_at'],
                                'UTC'
                            )->setTimezone('Asia/Kuala_Lumpur')
                                ->format('l, d M Y');
                        }


                        $groupedResults[$rh->referral_id] = [
                            'referral_id' => createRefId($rh->referral_id),
                            'referral' => $referralData,
                            'referral_histories' => []
                        ];
                    } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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
     *     security={{"bearerAuth": {}}},
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
    public function chart(Request $request)
    {
        // Get business unit from JWT payload
        $jwtPayload = $request->get('jwt_payload');
        $userBusinessUnitId = $jwtPayload['business_unit_id'] ?? null;

        $referrals = Referral::with(['referral_hierarchies.business_unit'])
            ->whereHas('referral_hierarchies', function ($query) use ($userBusinessUnitId, $jwtPayload) {
                // Apply business unit filter only for non-superadmin users
                if (!$this->isSuperadmin($jwtPayload)) {
                    $query->where('business_unit_id', $userBusinessUnitId);
                }
                // Superadmin sees ALL business units (no filter applied)
            })
            ->get();

        // Superadmin sees all business units, others see only their own
        if ($this->isSuperadmin($jwtPayload)) {
            $businessUnits = BusinessUnit::all();
        } else {
            $businessUnits = BusinessUnit::where('id', $userBusinessUnitId)->get();
        }

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
            foreach ($referral->referral_hierarchies as $rh) {
                // Superadmin sees all business units, others see only theirs
                if ($rh->business_unit != null) {
                    if ($this->isSuperadmin($jwtPayload) || $rh->business_unit_id == $userBusinessUnitId) {
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
        }

        return response()->json($results, 200);
    }

    /**
     * @OA\Get(
     *     path="/api/report/dashboard",
     *     operationId="getDashboardStats",
     *     tags={"Reports"},
     *     summary="Get dashboard statistics",
     *     security={{"bearerAuth": {}}},
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
     *                 property="priority",
     *                 type="object",
     *                 @OA\Property(
     *                     property="low",
     *                     type="integer",
     *                     example=15,
     *                     description="Number of low priority referrals"
     *                 ),
     *                 @OA\Property(
     *                     property="medium",
     *                     type="integer",
     *                     example=20,
     *                     description="Number of medium priority referrals"
     *                 ),
     *                 @OA\Property(
     *                     property="high",
     *                     type="integer",
     *                     example=10,
     *                     description="Number of high priority referrals"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="total_priority",
     *                 type="integer",
     *                 example=45,
     *                 description="Total count of all priority referrals"
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
    public function dashboard(Request $request)
    {
        // Get business unit from JWT payload
        $jwtPayload = $request->get('jwt_payload');
        $userBusinessUnitId = $jwtPayload['business_unit_id'] ?? null;
        $listOutlets = $jwtPayload['outlet'] ?? null;

        // Return empty results if user has no outlets (non-superadmin)
        if ((!$listOutlets || !is_array($listOutlets)) && !$this->isSuperadmin($jwtPayload)) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        $referrals = Referral::with(['referral_hierarchies.business_unit'])
            ->whereHas('referral_hierarchies', function ($query) use ($userBusinessUnitId, $listOutlets, $jwtPayload) {
                // Apply business unit filter only for non-superadmin users
                if (!$this->isSuperadmin($jwtPayload)) {
                    $query->where('business_unit_id', $userBusinessUnitId);

                    // Apply outlet filter only for non-superadmin users
                    if ($listOutlets && is_array($listOutlets)) {
                        $query->whereIn('location', $listOutlets);
                    }
                }
                // Superadmin sees ALL referrals (no filters applied)
            })
            ->get();

        // Get all business units for superadmin, or specific business unit for others
        if ($this->isSuperadmin($jwtPayload)) {
            $businessUnits = BusinessUnit::all();
        } else {
            $businessUnits = BusinessUnit::where('id', $userBusinessUnitId)->get();
        }

        Log::info('Dashboard query executed', [
            'user_business_unit_id' => $userBusinessUnitId,
            'is_superadmin' => $this->isSuperadmin($jwtPayload),
            'referrals_count' => $referrals->count(),
            'outlets' => $listOutlets
        ]);

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        // Count referrals by status
        $statusCounts = [
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
        ];

        // Count referrals by priority (1=Low, 2=Medium, 3=High)
        $priorityCounts = [
            '1' => 0,     // Priority 1
            '2' => 0,  // Priority 2
            '3' => 0,    // Priority 3
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
                    $statusCounts['1']++;
                    break;
                case 2:
                    $statusCounts['2']++;
                    break;
                case 3:
                    $statusCounts['3']++;
                    break;
                case 4:
                    $statusCounts['4']++;
                    break;
                case 5:
                    $statusCounts['5']++;
                    break;
            }

            // Count by priority (1=Low, 2=Medium, 3=High)
            switch ($referral->priority) {
                case 1:
                    $priorityCounts['1']++;
                    break;
                case 2:
                    $priorityCounts['2']++;
                    break;
                case 3:
                    $priorityCounts['3']++;
                    break;
            }

            // Count referrals per business unit
            foreach ($referral->referral_hierarchies as $rh) {
                if ($rh->business_unit_id && isset($businessUnitCounts[$rh->business_unit_id])) {
                    // Superadmin: count all business units
                    // Non-superadmin: only count their business unit
                    if ($this->isSuperadmin($jwtPayload) || $rh->business_unit_id == $userBusinessUnitId) {
                        $businessUnitCounts[$rh->business_unit_id]['count']++;
                    }
                }
            }
        }

        return response()->json([
            'total_referral' => $referrals->count(),
            'status_count' => $statusCounts,
            'priority_count' => $priorityCounts,
            'total_priority' => array_sum($priorityCounts),
            'total_business_unit' => $businessUnits->count(),
            'business_units' => array_values($businessUnitCounts)
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/report/summary",
     *     summary="Get referral summary breakdown",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Summary data breakdown",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="by_priority", type="object"),
     *             @OA\Property(property="by_status", type="object"),
     *             @OA\Property(property="by_business_unit", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function summary(Request $request)
    {
        $jwtPayload = $request->get('jwt_payload');
        $businessUnitIdFromJwt = $jwtPayload['business_unit_id'] ?? null;

        // Optional filter params from query string
        $requestedBusinessUnitId = $request->query('business_unit_id');
        $month = $request->query('month', Carbon::now()->month);
        $year = $request->query('year', Carbon::now()->year);
        $locationFilter = $request->query('location');
        $statusFilter = $request->query('status');
        $priorityFilter = $request->query('priority');

        if ($this->isSuperadmin($jwtPayload)) {
            // Superadmin: filter by requested BU if provided, otherwise show all
            $businessUnitId = $requestedBusinessUnitId ?: null;
        } else {
            $businessUnitId = $businessUnitIdFromJwt;
            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }
        }

        // Get referral hierarchies with all necessary relationships
        $referralHistories = ReferralHierarchy::with([
            'referral',
            'business_unit',
            'external_referee'
        ]);

        if ($businessUnitId) {
            $referralHistories->where('business_unit_id', $businessUnitId);
        }

        $referralHistories->whereMonth('created_at', $month)
            ->whereYear('created_at', $year);

        if ($locationFilter) {
            $referralHistories->where('location', $locationFilter);
        }

        if ($statusFilter) {
            $referralHistories->whereHas('referral', function ($q) use ($statusFilter) {
                $q->where('status', $statusFilter);
            });
        }

        if ($priorityFilter) {
            $referralHistories->whereHas('referral', function ($q) use ($priorityFilter) {
                $q->where('priority', $priorityFilter);
            });
        }

        $referralHistories = $referralHistories->get();

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
            ],
            'priority' => [
                'Low' => 0,      // Priority 1
                'Medium' => 0,   // Priority 2
                'High' => 0      // Priority 3
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

                // Priority distribution (1 = Low, 2 = Medium, 3 = High)
                $priority = $history->referral->priority;
                if ($priority == 1) {
                    $stats['priority']['Low']++;
                } elseif ($priority == 2) {
                    $stats['priority']['Medium']++;
                } elseif ($priority == 3) {
                    $stats['priority']['High']++;
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

    /**
     * @OA\Get(
     *     path="/api/report/yearly",
     *     operationId="getYearlyReport",
     *     tags={"Reports"},
     *     summary="Get yearly cross-business-unit comparison data",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *         required=false,
     *         description="Year to summarize (defaults to current year)",
     *         @OA\Schema(type="integer", example=2025)
     *     ),
     *     @OA\Response(response=200, description="Yearly summary data per business unit"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function yearly(Request $request)
    {
        $jwtPayload = $request->get('jwt_payload');
        $year = $request->query('year', Carbon::now()->year);

        if ($this->isSuperadmin($jwtPayload)) {
            $businessUnits = BusinessUnit::where('is_active', true)->orderBy('name')->get();
        } else {
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;
            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }
            $businessUnits = BusinessUnit::where('id', $businessUnitId)
                ->where('is_active', true)
                ->get();
        }

        $buResults    = [];
        $allLocations = [];

        // Collect all histories first so we can resolve location IDs in one batch
        $allHistories = [];
        foreach ($businessUnits as $bu) {
            $histories = ReferralHierarchy::with(['referral'])
                ->where('business_unit_id', $bu->id)
                ->whereYear('created_at', $year)
                ->get();
            $allHistories[$bu->id] = $histories;
        }

        // Resolve unique location IDs -> codes via ODB API
        $uniqueLocationIds = collect($allHistories)
            ->flatten()
            ->pluck('location')
            ->filter()
            ->unique()
            ->values();

        $locationCodes = [];
        foreach ($uniqueLocationIds as $locationId) {
            try {
                $detail = $this->outletDetails($locationId);
                if (!blank($detail)) {
                    $detail = $detail[0] ?? $detail;
                    if (isset($detail['code'])) {
                        $locationCodes[$locationId] = $detail['code'];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to resolve outlet code', ['location_id' => $locationId, 'error' => $e->getMessage()]);
            }
        }

        foreach ($businessUnits as $bu) {
            $histories = $allHistories[$bu->id];

            $buData = [
                'id'        => $bu->id,
                'name'      => $bu->name,
                'total'     => 0,
                'sent'      => 0,
                'received'  => 0,
                'status'    => [
                    'Open'        => 0,
                    'In Progress' => 0,
                    'Referred'    => 0,
                    'Closed'      => 0,
                    'Not Present' => 0,
                ],
                'locations' => [],
            ];

            $countedReferralIds = [];

            foreach ($histories as $history) {
                $buData['total']++;

                if ($history->sequence == 1) {
                    $buData['sent']++;
                } else {
                    $buData['received']++;
                }

                if ($history->location) {
                    $locCode = $locationCodes[$history->location] ?? ('LOC-' . $history->location);
                    $buData['locations'][$locCode] = ($buData['locations'][$locCode] ?? 0) + 1;
                    $allLocations[$locCode]        = ($allLocations[$locCode] ?? 0) + 1;
                }

                if ($history->referral && $history->sequence == 1 && !in_array($history->referral_id, $countedReferralIds)) {
                    $countedReferralIds[] = $history->referral_id;

                    $statusName = getStatus($history->referral->status);
                    if (isset($buData['status'][$statusName])) {
                        $buData['status'][$statusName]++;
                    }
                }
            }

            arsort($buData['locations']);
            $buData['top_locations'] = array_slice($buData['locations'], 0, 5, true);
            unset($buData['locations']);

            $buResults[] = $buData;
        }

        arsort($allLocations);
        $topLocations = array_slice($allLocations, 0, 10, true);

        return response()->json([
            'year'           => (int) $year,
            'business_units' => $buResults,
            'top_locations'  => $topLocations,
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/report/multi-year",
     *     operationId="getMultiYearReport",
     *     tags={"Reports"},
     *     summary="Get per-business-unit referral totals from 2025 to present year",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Multi-year BU comparison data"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function multiYear(Request $request)
    {
        $jwtPayload = $request->get('jwt_payload');

        if ($this->isSuperadmin($jwtPayload)) {
            $businessUnits = BusinessUnit::where('is_active', true)->orderBy('name')->get();
        } else {
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;
            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }
            $businessUnits = BusinessUnit::where('id', $businessUnitId)
                ->where('is_active', true)
                ->get();
        }

        $startYear = 2025;
        $currentYear = Carbon::now()->year;
        $years = range($startYear, $currentYear);

        $emptyStatus = [
            'Open'        => 0,
            'In Progress' => 0,
            'Referred'    => 0,
            'Closed'      => 0,
            'Not Present' => 0,
        ];

        $buResults = [];

        foreach ($businessUnits as $bu) {
            $yearlyTotals = [];
            $yearlyData   = [];

            foreach ($years as $year) {
                $histories = ReferralHierarchy::with(['referral'])
                    ->where('business_unit_id', $bu->id)
                    ->whereYear('created_at', $year)
                    ->get();

                $entry = [
                    'year'            => $year,
                    'total'           => $histories->count(),
                    'sent'            => 0,
                    'received'        => 0,
                    'sent_status'     => $emptyStatus,
                    'received_status' => $emptyStatus,
                ];

                foreach ($histories as $history) {
                    $isSent = $history->sequence == 1;

                    if ($isSent) {
                        $entry['sent']++;
                    } else {
                        $entry['received']++;
                    }

                    if ($history->referral) {
                        $statusName = getStatus($history->referral->status);
                        if ($isSent) {
                            $entry['sent_status'][$statusName]++;
                        } else {
                            $entry['received_status'][$statusName]++;
                        }
                    }
                }

                $yearlyTotals[] = $entry['total'];
                $yearlyData[]   = $entry;
            }

            $buResults[] = [
                'id'            => $bu->id,
                'name'          => $bu->name,
                'yearly_totals' => $yearlyTotals,
                'yearly_data'   => $yearlyData,
            ];
        }

        return response()->json([
            'years'          => $years,
            'business_units' => $buResults,
        ], 200);
    }
}