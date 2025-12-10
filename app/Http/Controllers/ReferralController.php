<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralRequest;
use App\Mail\ExternalReferralNotification;
use App\Models\BusinessUnit;
use App\Models\ExternalOrganization;
use App\Models\ExternalReferee;
use App\Models\FormDetails;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralCreateForm;
use App\Models\ReferralDetails;
use App\Models\ReferralHierarchy;
use App\Models\ReferralReplyForm;
use App\Traits\AccessControl;
use App\Traits\Octopus;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferralController extends Controller
{
    use Octopus;
    use AccessControl;

    /**
     * @OA\Get(
     *     path="/api/referral",
     *     summary="Get list of referrals",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of referrals",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="ref_id", type="string", example="#REF0001"),
     *                     @OA\Property(property="reason", type="string", example="Vestibular-Related Balance Issue"),
     *                     @OA\Property(property="business_unit", type="string", example="Alpro Physio"),
     *                     @OA\Property(property="status", type="string", example="Open"),
     *                     @OA\Property(property="created_at", type="string", example="1 July 2025, Sunday")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="No results found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No results."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Business unit ID not found in session.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $jwtPayload = $request->get('jwt_payload');
        $businessUnitId = $jwtPayload['business_unit_id'] ?? null;
        $listOutlets = $jwtPayload['outlet'] ?? null;

        // Only validate for non-superadmin users
        if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
            return response()->json([
                'message' => 'Business unit ID not found in session.',
                'data' => [],
            ], 401);
        }

        if ((!$listOutlets || !is_array($listOutlets)) && !$this->isSuperadmin($jwtPayload)) {
            return response()->json([
                'message' => 'Outlet list not found in session.',
                'data' => [],
            ], 401);
        }

        $referrals = Referral::with([
            'referral_hierarchies.business_unit',
            'referral_hierarchies.external_referee',
            'referral_hierarchies.external_organization',
            'referral_hierarchies.referral_create_form'
        ])
            ->whereHas('referral_hierarchies', function ($query) use ($jwtPayload, $businessUnitId, $listOutlets) {
                // Superadmin sees all referrals
                if (!$this->isSuperadmin($jwtPayload)) {
                    $query->where('business_unit_id', $businessUnitId)
                    ->whereIn('location', $listOutlets);
                }
            })
            ->orderByDesc('created_at')
            ->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [
                    'all' => [],
                    'sent' => [],
                    'received' => []
                ],
            ], 204);
        }

        $all = [];
        $sent = [];
        $received = [];

        foreach ($referrals as $ref) {
            // Find the latest sequence (maximum sequence number)
            $latestSequence = $ref->referral_hierarchies->max('sequence');

            // Get the referral hierarchy with the latest sequence
            $latestReferralHierarchy = $ref->referral_hierarchies->where('sequence', $latestSequence)->first();

            // Get the second-to-last sequence for 'from' business unit
            $secondToLastSequence = $latestSequence > 1 ? $latestSequence - 1 : 1;
            $secondToLastReferralHierarchy = $ref->referral_hierarchies->where('sequence', $secondToLastSequence)->first();

            // Check if current business unit is involved in this referral
            // Superadmin bypasses this check and sees ALL referrals
            if (!$this->isSuperadmin($jwtPayload)) {
                $currentBusinessUnitHierarchy = $ref->referral_hierarchies->where('business_unit_id', $businessUnitId)->first();

                if (!$currentBusinessUnitHierarchy) {
                    continue;
                }
            } else {
                // For superadmin, just get the first hierarchy to avoid null errors
                $currentBusinessUnitHierarchy = $ref->referral_hierarchies->first();
            }

            $is_external = $latestReferralHierarchy->external_organization_id != null ? true : false;

            // Get referral reason from the second-to-last hierarchy's create form
            $referralReason = null;
            if ($secondToLastReferralHierarchy && $secondToLastReferralHierarchy->referral_create_form) {
                $referralReason = $secondToLastReferralHierarchy->referral_create_form->referral_reason ?? null;
            }

            $referralData = [];
            if ($latestReferralHierarchy && $secondToLastReferralHierarchy && !$is_external) {
                $referralData = [
                    'id' => $ref->id,
                    'ref_id' => createRefId($ref->id),
                    'reason' => $referralReason,
                    'from_business_unit' => $secondToLastReferralHierarchy->business_unit->name,
                    'to_business_unit' => $latestReferralHierarchy->business_unit->name,
                    'priority' => $ref->priority,
                    'status' => $ref->status,
                    'created_at' => Carbon::parse($ref->created_at)->format('j F Y, l'),
                    'ori_created_at' => $ref->created_at,
                    'is_external' => $is_external
                ];
            } else {
                $referralData = [
                    'id' => $ref->id,
                    'ref_id' => createRefId($ref->id),
                    'reason' => $referralReason,
                    'from_business_unit' => $secondToLastReferralHierarchy->business_unit->name,
                    'to_business_unit' => $latestReferralHierarchy->external_referee ? $latestReferralHierarchy->external_referee->name : ($latestReferralHierarchy->external_organization ? $latestReferralHierarchy->external_organization->name : null),
                    'priority' => $ref->priority,
                    'status' => $ref->status,
                    'created_at' => Carbon::parse($ref->created_at)->format('j F Y, l'),
                    'ori_created_at' => $ref->created_at,
                    'is_external' => $is_external
                ];
            }

            // Add to all category (any referral that has business unit included)
            $all[] = $referralData;

            // Received: business unit is the LAST sequence (latest in the chain) AND matches the current business unit
            if ($currentBusinessUnitHierarchy->sequence == $latestSequence && $latestReferralHierarchy->business_unit_id == $businessUnitId) {
                $received[] = $referralData;
            }

            // Sent: sequence = 1 OR when business unit refers to another (not the last one)
            if (
                $currentBusinessUnitHierarchy->sequence == 1 ||
                ($currentBusinessUnitHierarchy->sequence < $latestSequence)
            ) {
                $sent[] = $referralData;
            }
        }

        return response()->json([
            'data' => [
                'all' => $all,
                'sent' => $sent,
                'received' => $received
            ]
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/referral",
     *     summary="Create a new internal referral",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_units", "referral"},
     *             @OA\Property(property="business_units", type="object",
     *                 @OA\Property(property="assignee", type="object",
     *                     @OA\Property(property="staff_id", type="integer", example=2222),
     *                     @OA\Property(property="business_unit_id", type="string", example="6"),
     *                     @OA\Property(property="location", type="string", example="101"),
     *                     @OA\Property(property="referral_reason", type="string", example="Vestibular-Related Balance Issue"),
     *                     @OA\Property(property="referral_condition", type="string", example="Patient reports persistent dizziness and unsteadiness during standing and walking exercises. Symptoms suggest possible vestibular involvement that is beyond musculoskeletal causes. Referral to audiology is requested for further assessment and vestibular testing."),
     *                     @OA\Property(property="medical_history", type="string", example="Mild scoliosis diagnosed during teenage years."),
     *                     @OA\Property(property="additional_remarks", type="string", example=null, nullable=true)
     *                 ),
     *                 @OA\Property(property="recipient", type="object",
     *                     @OA\Property(property="staff_id", type="integer", example=0),
     *                     @OA\Property(property="business_unit_id", type="string", example="1"),
     *                     @OA\Property(property="location", type="string", example="350")
     *                 )
     *             ),
     *             @OA\Property(property="referral", type="object",
     *                 @OA\Property(property="customer_id", type="integer", example=10),
     *                 @OA\Property(property="priority", type="integer", example=2)
     *             ),
     *             @OA\Property(property="form_data", type="object",
     *                 @OA\Property(property="6", type="object",
     *                     @OA\Property(property="targeted_area", type="string", example="Lower limbs and core"),
     *                     @OA\Property(property="pain_level", type="string", example="4"),
     *                     @OA\Property(property="previous_physiotherapy", type="string", example="30")
     *                 )
     *             ),
     *             @OA\Property(property="attachments", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string", example="Labs.png"),
     *                     @OA\Property(property="type", type="string", example="image/png"),
     *                     @OA\Property(property="size", type="integer", example=14040),
     *                     @OA\Property(property="base64", type="string", format="byte", example="iVBORw0KGgoAAAANSUhEUgAAALUAAAC2CAYAA"),
     *                     description="Attachments will be associated with the referral history that has is_filled=true"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Referral created successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1234),
     *             @OA\Property(property="pdf_base64", type="string", format="byte", example="JVBERi0xLjQKJaqrrK0KMSAwIG9iago...", description="Base64 encoded PDF with QR code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Business unit ID not found in session.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized: Cannot create referral for different business unit.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to create referral.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create referral."),
     *             @OA\Property(property="error", type="string", example="Connection Failure"),
     *             @OA\Property(property="line", type="integer", example=123),
     *             @OA\Property(property="file", type="string", example="/app/Http/Controllers/ReferralController.php")
     *         )
     *     )
     * )
     */
    public function store(StoreReferralRequest $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                    'data' => [],
                ], 401);
            }

            $validated = $request->validated();

            if ($validated) {
                DB::beginTransaction();
                //data from business units
                $businessUnits = $validated['business_units'];

                //get business unit id
                $business_unit_id = $businessUnits['assignee']['business_unit_id'];

                // Validate that the assignee business unit matches JWT business unit (skip for superadmin)
                if (!$this->canAccessBusinessUnit($jwtPayload, $business_unit_id)) {
                    return response()->json([
                        'message' => 'Unauthorized: Cannot create referral for different business unit.',
                    ], 403);
                }

                // Determine customer_id based on external referral
                $customerId = $validated['referral']['customer_id'];

                //create referral
                $referral = Referral::create([
                    'customer_id' => $customerId,
                    'priority' => $validated['referral']['priority'],
                    'status' => 1, //Open
                ]);


                //run through businessunits
                foreach (array_values($businessUnits) as $key => $value) {

                    //for second level of business unit
                    $is_filled = false;
                    $is_read = false;

                    if (isset($value['business_unit_id']) && $business_unit_id == $value['business_unit_id']) {
                        $is_filled = true;
                        $is_read = true;
                    }

                    $sequence = $key + 1;

                    //compile data for referral hierarchy (no form fields)
                    $data = [
                        'referral_id' => $referral->id,
                        'staff_id' => ($value['staff_id'] ?? 0) != 0 ? $value['staff_id'] : null,
                        'business_unit_id' => isset($value['business_unit_id']) ? $value['business_unit_id'] : null,
                        'location' => $value['location'] ?? null,
                        'sequence' => $sequence,
                        'additional_remarks' => $value['additional_remarks'] ?? null,
                        'is_filled' => $is_filled,
                        'is_read' => $is_read
                    ];

                    //create referral hierarchy
                    $referralHierarchy = ReferralHierarchy::create($data);
                    $referral_hierarchy_id = $referralHierarchy->id;
                    $business_unit = $is_filled ? $referralHierarchy->business_unit->name : null;

                    // Create form based on sequence (odd = create form, even = reply form)
                    if ($sequence % 2 === 1) {
                        // Odd sequence - Create Form
                        if (
                            isset($value['referral_reason']) ||
                            isset($value['referral_condition']) ||
                            isset($value['medical_history'])
                        ) {
                            ReferralCreateForm::create([
                                'referral_hierarchy_id' => $referral_hierarchy_id,
                                'referral_reason' => $value['referral_reason'] ?? null,
                                'referral_condition' => $value['referral_condition'] ?? null,
                                'medical_history' => $value['medical_history'] ?? null,
                            ]);
                        }
                    }

                    //run through first level of business unit
                    if ($is_filled) {
                        $formFields = $request->input("form_data.$business_unit_id", []);

                        // Only process form fields if form_data is not empty
                        if (!empty($formFields) && is_array($formFields)) {
                            foreach ($formFields as $field => $value) {
                                //get data from form details
                                $form_detail = FormDetails::where('field_name', $field)
                                    ->whereHas('form', function ($query) use ($business_unit_id) {
                                        $query->where('business_unit_id', $business_unit_id);
                                    })
                                    ->first();

                                //create referral details
                                if ($form_detail) {
                                    ReferralDetails::create([
                                        'referral_hierarchy_id' => $referral_hierarchy_id,
                                        'form_id' => $form_detail->form_id,
                                        'value' => is_array($value) ? json_encode($value) : $value,
                                    ]);
                                }
                            }
                        }
                    }

                    //run through attachments if exist
                    if (filled($request['attachments']) && $is_filled) {
                        $mimeMap = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'application/pdf' => 'pdf',
                            'application/msword' => 'doc',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                            'application/vnd.ms-excel' => 'xls',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
                        ];

                        foreach ($validated['attachments'] as $key => $atc) {
                            $referralAttachment = ReferralAttachment::create([
                                'referral_hierarchy_id' => $referral_hierarchy_id,
                                'file_name' => $atc['name'],
                                'file_type' => $atc['type'],
                                'file_size' => $atc['size'],
                                'encoded_base' => $atc['base64']
                            ]);

                            $extension = $mimeMap[$atc['type']] ?? pathinfo($atc['name'], PATHINFO_EXTENSION);
                            $newFileName = $business_unit != null
                                ? str_replace(' ', '_', $business_unit)
                                : pathinfo($atc['name'], PATHINFO_FILENAME);

                            $suffix = $referral->id . $referral_hierarchy_id . $referralAttachment->id;
                            $referralAttachment->file_name = $newFileName . '_' . $suffix . '.' . $extension;
                            $referralAttachment->save();
                        }
                    }
                }

                $referral->save();

                $response = ['id' => $referral->id];

                $this->exportReferral($referral);

                //return referral id if successfulD
                DB::commit();
                return response()->json($response, 201);
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create referral.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/referral/{id}",
     *     summary="Get detailed referral information by ID",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Referral ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Referral details retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="referral_id", type="string", example="#REF0001"),
     *             @OA\Property(property="status", type="integer", example=1),
     *             @OA\Property(property="status_note", type="string", nullable=true, example=null),
     *             @OA\Property(property="customer_id", type="integer", example=10),
     *             @OA\Property(property="priority", type="integer", example=2),
     *             @OA\Property(property="referralDetails", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="sequence", type="integer", example=1),
     *                     @OA\Property(property="staff_id", type="integer", nullable=true, example=2222),
     *                     @OA\Property(property="location", type="string", example="101"),
     *                     @OA\Property(property="business_unit_id", type="integer", example=6),
     *                     @OA\Property(property="created_at", type="string", example="19 June 2025"),
     *                     @OA\Property(property="referral_reason", type="string", nullable=true, example="Vestibular-Related Balance Issue"),
     *                     @OA\Property(property="referral_condition", type="string", nullable=true, example="Patient reports persistent dizziness..."),
     *                     @OA\Property(property="medical_history", type="string", nullable=true, example="Mild scoliosis diagnosed during teenage years."),
     *                     @OA\Property(property="additional_remarks", type="string", nullable=true, example=null),
     *                     @OA\Property(property="referral_details", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="form_id", type="integer", example=5),
     *                             @OA\Property(property="label_name", type="string", example="Pharmacy Form"),
     *                             @OA\Property(property="is_hidden", type="boolean", example=false),
     *                             @OA\Property(property="form_details", type="array",
     *                                 @OA\Items(type="object",
     *                                     @OA\Property(property="field_name", type="string", example="drug_allergies"),
     *                                     @OA\Property(property="field_type", type="string", example="radio"),
     *                                     @OA\Property(property="is_required", type="boolean", example=true),
     *                                     @OA\Property(property="field_data", type="array",
     *                                         @OA\Items(type="object",
     *                                             @OA\Property(property="form_detail_id", type="integer", example=25),
     *                                             @OA\Property(property="field_value", type="string", example="No"),
     *                                             @OA\Property(property="is_answer", type="boolean", example=false, description="false when ReferralDetails value is null, true when answered")
     *                                         )
     *                                     )
     *                                 )
     *                             )
     *                         )
     *                     ),
     *                     @OA\Property(property="attachments", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="attachment_id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="BloodReport.pdf"),
     *                             @OA\Property(property="size", type="string", example="application/pdf"),
     *                             @OA\Property(property="type", type="integer", example=14040),
     *                             @OA\Property(property="encoded", type="string", format="byte", example="JVBERi0xLjQKJaqrrK0KMSAwIG9iao8PC9U... (truncated)")
     *                         )
     *                     ),
     *                     @OA\Property(property="external_referral", type="array",
     *                         @OA\Items(type="object",
     *                             @OA\Property(property="external_referee_id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Dr. John Smith"),
     *                             @OA\Property(property="email", type="string", example="john.smith@hospital.com"),
     *                             @OA\Property(property="phone", type="string", example="+1234567890"),
     *                             @OA\Property(property="position", type="string", example="Cardiologist"),
     *                             @OA\Property(property="external_organization_id", type="integer", example=1),
     *                             @OA\Property(property="organization", type="string", example="City General Hospital"),
     *                             @OA\Property(property="address", type="string", example="123 Medical Center Dr"),
     *                             @OA\Property(property="postcode", type="string", example="12345"),
     *                             @OA\Property(property="state", type="string", example="State"),
     *                             @OA\Property(property="country", type="string", example="Country")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="pdf_base64", type="string", format="byte", nullable=true, example=null, description="PDF base64 string available for all referrals")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid or expired token.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Referral not accessible.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Referral not accessible.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Referral not found.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Referral not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error.")
     *         )
     *     )
     * )
     */
    public function show(Request $request, Referral $referral)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            // Only validate for non-superadmin users
            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                    'data' => [],
                ], 401);
            }

            //check if referral exist
            if (!$referral) {
                return response()->json(['message' => 'Referral not found.'], 404);
            }

            // Load necessary relationships
            $referral->load([
                'referral_hierarchies.business_unit',
                'referral_hierarchies.external_referee.organization',
                'referral_hierarchies.external_organization',
                'referral_hierarchies.referral_create_form',
                'referral_hierarchies.referral_reply_form',
                'referral_hierarchies.referral_details.form.form_details',
                'referral_hierarchies.referral_attachments'
            ]);

            // Check view_only parameter
            $viewOnly = $request->query('view_only', false);

            //check if referral accessible by this business unit (skip if view_only or superadmin)
            if (!$viewOnly && !$this->isSuperadmin($jwtPayload)) {
                $exists = $referral->referral_hierarchies->contains('business_unit_id', $businessUnitId);

                if (!$exists) {
                    return response()->json(['message' => 'Referral not accessible.'], 403);
                }
            }

            //initialize for default value
            $is_external = false;
            //get referral hierarchies
            $referralHierarchies = $referral->referral_hierarchies
                ->sortBy('sequence')
                ->values()
                ->map(function ($rh) use (
                    &$is_external,
                ) {
                    $forms = [];

                    //get details
                    foreach ($rh->referral_details as $rd) {
                        $formDetails = [];
                        $form_details = $rd->form->form_details;
                        $value = $rd->value ? (json_decode($rd->value, true) ?: $rd->value) : null;

                    //run through form details
                    foreach ($form_details as $fd) {
                            $key = $fd->field_name;

                            if (!isset($formDetails[$key])) {
                                $formDetails[$key] = [
                                    'field_name' => $fd->field_name,
                                    'field_type' => $fd->field_type,
                                    'is_required' => $fd->is_required != 0 ? true : false,
                                    'field_data' => [],
                                ];
                            }

                            //map answer to form details based on type
                            $is_answer = false;

                            if ($value === null) {
                                // Handle null values - show field but not answered
                                $formDetails[$key]['field_data'][] = [
                                    'form_detail_id' => $fd->id,
                                    'field_value' => $fd->field_value,
                                    'is_answer' => false
                                ];
                            } elseif ($fd->field_type == 'checkbox' && is_array($value)) {
                                $is_answer = in_array($fd->id, $value);
                                $formDetails[$key]['field_data'][] = [
                                    'form_detail_id' => $fd->id,
                                    'field_value' => $fd->field_value,
                                    'is_answer' => $is_answer
                                ];
                            } elseif ($fd->field_type == 'radio') {
                                $is_answer = ($fd->id == $value);
                                $formDetails[$key]['field_data'][] = [
                                    'form_detail_id' => $fd->id,
                                    'field_value' => $fd->field_value,
                                    'is_answer' => $is_answer
                                ];
                            } else {
                                $formDetails[$key]['field_data'] = [
                                    [
                                        'form_detail_id' => $fd->id,
                                        'field_value' => $rd->value,
                                        'is_answer' => $rd->value !== null
                                    ]
                                ];
                            }
                        }

                        //group by index
                        $formDetails = array_values($formDetails);

                        //grouped form details with form
                        $form = [
                            'form_id' => $rd->form->id,
                            'label_name' => $rd->form->label_name,
                            'is_hidden' => $rd->form->is_hidden != 0,
                            'form_details' => $formDetails,
                        ];

                        //add to array
                        $forms[] = $form;
                    }

                    //get attachments for this history
                    $attachments = $rh->referral_attachments->map(function ($atc) {
                        return [
                            'attachment_id' => $atc->id,
                            'name' => $atc->file_name,
                            'size' => $atc->file_size,
                            'type' => $atc->file_type,
                            'encoded' => $atc->encoded_base
                        ];
                    });

                //get external referee or organization
                $external_referral = [];

                $referee = $rh->external_referee;
                $org = $rh->external_organization;

                // Check if referee is valid (not null and name != N/A)
                $validReferee = $referee
                    && $referee->name
                    && strtolower($referee->name) !== 'n/a';

                if ($validReferee) {

                    $is_external = true;
                    $external_referral[] = [
                        'referee' => [
                            'external_referee_id' => $referee->id,
                            'name' => $referee->name,
                            'email' => $referee->email,
                            'phone' => $referee->phone,
                            'position' => $referee->position,
                        ],
                        'organization' => [
                            'external_organization_id' => $referee->organization->id ?? null,
                            'name' => $referee->organization->name ?? null,
                            'address' => $referee->organization->address ?? null,
                            'postcode' => $referee->organization->postcode ?? null,
                            'state' => $referee->organization->state ?? null,
                            'country' => $referee->organization->country ?? null,
                        ]
                    ];
                } elseif ($org) {

                    // fallback: use organization
                    $is_external = true;
                    $external_referral[] = [
                        'referee' => null,
                        'organization' => [
                            'external_organization_id' => $org->id,
                            'name' => $org->name,
                            'address' => $org->address,
                            'postcode' => $org->postcode,
                            'state' => $org->state,
                            'country' => $org->country,
                        ]
                    ];
                }


                // Get form data from CreateForm and ReplyForm if they exist
                $createForm = [];
                $replyForm = [];

                // Get Create Form data if exists
                if ($rh->referral_create_form) {
                    $createForm = [
                        'referral_reason' => $rh->referral_create_form->referral_reason,
                        'referral_condition' => $rh->referral_create_form->referral_condition,
                        'medical_history' => $rh->referral_create_form->medical_history,
                    ];
                }

                // Get Reply Form data if exists
                if ($rh->referral_reply_form) {
                    $replyForm = [
                        'post_diagnosis' => $rh->referral_reply_form->post_diagnosis,
                        'outcome' => $rh->referral_reply_form->outcome,
                        'feedback' => $rh->referral_reply_form->feedback,
                    ];
                    }

                    //return histories data with attachments
                    return [
                        'sequence' => $rh->sequence,
                        'staff_id' => $rh->staff_id,
                        'location' => $rh->location,
                        'business_unit_id' => $rh->business_unit_id,
                    'created_at' => Carbon::parse($rh->created_at)->format('d F Y'),
                        'additional_remarks' => $rh->additional_remarks,
                    'is_filled' => $rh->is_filled,
                    'createForm' => $createForm,
                    'replyForm' => $replyForm,
                        'referral_details' => $forms,
                        'attachments' => $attachments,
                        'external_referral' => $external_referral,
                    ];
                });

            //grouped all data
            $data = [
                'referral_id' => createRefId($referral->id),
                'status' => $referral->status,
                'status_note' => $referral->status_note,
                'customer_id' => $referral->customer_id,
                'priority' => $referral->priority,
                'referralDetails' => $referralHierarchies,
            ];

            // Generate PDF base64 for ALL referrals (both internal and external)
            // Use stored PDF if available, otherwise generate new one
            if ($referral->encoded_base && !empty($referral->encoded_base)) {
                $data['pdf_base64'] = $referral->encoded_base;
            } else {
                $pdf = $this->exportPdf($data);
                if ($pdf) {
                    $data['pdf_base64'] = $pdf;
                    // Store for future use
                    $referral->encoded_base = $pdf;
                    $referral->save();
                }
            }

            return response()->json($data, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Referral not found.'
            ], 404);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Database error occurred.'], 500);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/referral",
     *     summary="Update an existing referral and optionally refer to another business unit",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"referral"},
     *             @OA\Property(property="referral", type="object",
     *                 @OA\Property(property="referral_id", type="string", example="1"),
     *                 @OA\Property(property="updated_recipient_to", type="string", example="3333"),
     *                 @OA\Property(property="business_unit_id_reply", type="string", example="5"),
     *                 @OA\Property(property="status", type="integer", example=3),
     *                 @OA\Property(property="additional_remarks", type="string", example="No current medications apart from pain relief.")
     *             ),
     *             @OA\Property(property="refer_another", type="object",
     *                 @OA\Property(property="refer_business_unit", type="string", example="2"),
     *                 @OA\Property(property="refer_location", type="string", example="303"),
     *                 @OA\Property(property="refer_to", type="string", example=""),
     *                 @OA\Property(property="referral_reason", type="string", example="Medical assessment on lower back pain"),
     *                 @OA\Property(property="referral_condition", type="string", example="Lower back pain rated at 7/10, persistent despite physiotherapy and pharmacy treatment. Patient reports stiffness after prolonged sitting and minimal improvement. Pharmacist advised on pain relief medication (paracetamol and muscle rub), but symptoms persist."),
     *                 @OA\Property(property="medical_history", type="string", example="Mild scoliosis diagnosed during teenage years. No history of trauma or major illness."),
     *                 @OA\Property(property="additional_remarks_refer", type="string", example="Patient has been cooperative and compliant with both physiotherapy and pharmacy recommendations. However, the ongoing pain and limited response to conservative treatment suggest the need for further clinical evaluation. Consider ruling out structural or neurological causes. Patient open to further diagnostic tests if required.")
     *             ),
     *             @OA\Property(property="attachments", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string", example="Labs.png"),
     *                     @OA\Property(property="type", type="string", example="image/png"),
     *                     @OA\Property(property="size", type="integer", example=14040),
     *                     @OA\Property(property="base64", type="string", format="byte", example="iVBORw0KGgoAAAANSUhEUgAAALUAAAC2CAYAA"),
     *                     description="Attachments will be associated with the referral history that has is_filled=true"
     *                 )
     *             ),
     *             @OA\Property(property="form_data", type="object",
     *                 @OA\Property(property="5", type="object",
     *                     @OA\Property(property="drug_allergies", type="string", example="No"),
     *                     @OA\Property(property="prescription_status", type="string", example="25"),
     *                     @OA\Property(property="pickup_time", type="string", example="14:20")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Referral updated successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Referral updated successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid or expired token.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to update referral.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to update referral."),
     *             @OA\Property(property="error", type="string", example="SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'referral_id' cannot be null")
     *         )
     *     )
     * )
     */
    public function update(UpdateReferralRequest $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $staffId = $jwtPayload['staff_id'] ?? null;

            $validated = $request->validated();

            if ($validated) {
                DB::beginTransaction();
                $referral_id = $validated['referral']['referral_id'];
                $business_unit_id = $validated['referral']['business_unit_id_reply'];
                $referral = Referral::with(['referral_hierarchies', 'referral_hierarchies.referral_details'])->find($referral_id);

                //update status
                $referral->status = $validated['referral']['status'];

                if ($validated['referral']['status'] == 5 && isset($validated['referral']['status_note']) && $validated['referral']['status_note'] != '') {
                    $referral->status_note = $validated['referral']['status_note'];
                }

                $referral_hierarchy_id = null;

                // Find the current business unit's hierarchy (superadmin can update any business unit)
                foreach ($referral->referral_hierarchies as $rh) {
                    if ($rh->business_unit_id == $business_unit_id || $this->isSuperadmin($jwtPayload)) {
                        // For superadmin, only process the hierarchy matching the business_unit_id from request
                        if ($this->isSuperadmin($jwtPayload) && $rh->business_unit_id != $business_unit_id) {
                            continue;
                        }
                        $referral_hierarchy_id = $rh->id;

                        // Update staff_id if not set
                        if (is_null($rh->staff_id)) {
                            $rh->staff_id = !empty($validated['referral']['updated_recipient_to'])
                                ? $validated['referral']['updated_recipient_to']
                                : $staffId;
                        }

                        // Update location if provided
                        if (!empty($validated['referral']['location_to'])) {
                            $rh->location = $validated['referral']['location_to'];
                        }

                        // Update additional_remarks
                        $rh->additional_remarks = $validated['referral']['additional_remarks'] ?? $rh->additional_remarks;
                        $rh->is_read = true;
                        $rh->is_filled = true;
                        $rh->save();

                        // Create or update ReferralReplyForm with post_diagnosis, outcome, feedback
                        ReferralReplyForm::updateOrCreate(
                            ['referral_hierarchy_id' => $referral_hierarchy_id],
                            [
                                'post_diagnosis' => $validated['referral']['post_diagnosis'] ?? null,
                                'outcome' => $validated['referral']['outcome'] ?? null,
                                'feedback' => $validated['referral']['feedback'] ?? null,
                            ]
                        );

                        // If referring to another unit, create ReferralCreateForm for current hierarchy
                        if (isset($validated['refer_another'])) {
                            ReferralCreateForm::updateOrCreate(
                                ['referral_hierarchy_id' => $referral_hierarchy_id],
                                [
                                    'referral_reason' => $validated['refer_another']['referral_reason'] ?? null,
                                    'referral_condition' => $validated['refer_another']['referral_condition'] ?? null,
                                    'medical_history' => $validated['refer_another']['medical_history'] ?? null,
                                ]
                            );
                        }

                        // Skip form_data and attachments processing if status is 5 (patient not present)
                        if ($validated['referral']['status'] != 5) {
                            //update referral details
                            if (isset($validated['form_data']) && filled($validated['form_data'])) {
                                $formFields = $validated['form_data'][$business_unit_id] ?? [];

                                foreach ($formFields as $field => $value) {
                                    $form_detail = FormDetails::where('field_name', $field)
                                        ->whereHas('form', function ($query) use ($business_unit_id) {
                                            $query->where('business_unit_id', $business_unit_id);
                                        })
                                        ->first();

                                    if ($form_detail) {
                                        ReferralDetails::updateOrCreate(
                                            [
                                                'referral_hierarchy_id' => $referral_hierarchy_id,
                                                'form_id' => $form_detail->form_id,
                                            ],
                                            [
                                                'value' => is_array($value) ? json_encode($value) : $value,
                                            ]
                                        );
                                    }
                                }
                            }

                            //run through attachments if exist
                            if (filled($request['attachments'])) {
                                $mimeMap = [
                                    'image/jpeg' => 'jpg',
                                    'image/png' => 'png',
                                    'application/pdf' => 'pdf',
                                    'application/msword' => 'doc',
                                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                                    'application/vnd.ms-excel' => 'xls',
                                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
                                ];

                                foreach ($validated['attachments'] as $atc) {
                                    $referralAttachment = ReferralAttachment::create([
                                        'referral_hierarchy_id' => $referral_hierarchy_id,
                                        'file_name' => $atc['name'],
                                        'file_type' => $atc['type'],
                                        'file_size' => $atc['size'],
                                        'encoded_base' => $atc['base64']
                                    ]);

                                    $business_unit = $rh->business_unit->name;
                                    $extension = $mimeMap[$atc['type']] ?? pathinfo($atc['name'], PATHINFO_EXTENSION);
                                    $newFileName = $business_unit != null ? str_replace(' ', '_', $business_unit) : pathinfo($atc['name'], PATHINFO_FILENAME);
                                    $suffix = $referral->id . $referral_hierarchy_id . $referralAttachment->id;

                                    $referralAttachment->file_name = $newFileName . '_' . $suffix . '.' . $extension;
                                    $referralAttachment->save();
                                }
                            }
                        }
                        break; // Found the business unit, exit loop
                    }
                }

                $referral->save();

                //create new hierarchy for refer_another
                if (isset($validated['refer_another'])) {
                    $total_rh = count($referral->referral_hierarchies);
                    $new_sequence = $total_rh + 1;

                    // Handle external referral
                    $newOrganizationId = null;
                    $newRefereeId = null;
                    $isExternalReferral = isset($validated['refer_another']['is_external_referral']) && $validated['refer_another']['is_external_referral'];

                    if ($isExternalReferral) {
                        // Create new organization if provided
                        if (isset($validated['refer_another']['new_organization'])) {
                            $newOrgData = $validated['refer_another']['new_organization'];
                            $newOrganization = ExternalOrganization::create([
                                'name' => $newOrgData['name'],
                                'address' => $newOrgData['address'] ?? null,
                                'postcode' => $newOrgData['postcode'] ?? null,
                                'state' => $newOrgData['state'] ?? null,
                                'country' => $newOrgData['country'] ?? null,
                            ]);
                            $newOrganizationId = $newOrganization->id;
                        }

                        // Create new recipient if provided
                        if (isset($validated['refer_another']['new_recipient'])) {
                            $newRecipientData = $validated['refer_another']['new_recipient'];
                            $orgId = $newOrganizationId ?? $validated['refer_another']['refer_organization'] ?? null;

                            $newReferee = ExternalReferee::create([
                                'name' => $newRecipientData['name'],
                                'email' => $newRecipientData['email'] ?? null,
                                'phone' => $newRecipientData['phone'] ?? null,
                                'position' => $newRecipientData['position'] ?? null,
                                'external_organization_id' => $orgId,
                            ]);
                            $newRefereeId = $newReferee->id;
                        }
                    }

                    // Determine external IDs
                    $externalOrganizationId = null;
                    $externalRefereeId = null;

                    if ($isExternalReferral) {
                        if ($newRefereeId) {
                            $externalRefereeId = $newRefereeId;
                        } elseif (isset($validated['refer_another']['refer_referee'])) {
                            $externalRefereeId = $validated['refer_another']['refer_referee'];
                        }

                        if ($newOrganizationId) {
                            $externalOrganizationId = $newOrganizationId;
                        } elseif (isset($validated['refer_another']['refer_organization'])) {
                            $externalOrganizationId = $validated['refer_another']['refer_organization'];
                        }

                        // Update referral status to external
                        $referral->status = 4;
                    }

                    // Create new referral hierarchy (recipient - TO WHO)
                    $newReferralHierarchy = ReferralHierarchy::create([
                        'referral_id' => $referral->id,
                        'staff_id' => !$isExternalReferral && isset($validated['refer_another']['refer_to']) ? $validated['refer_another']['refer_to'] : null,
                        'business_unit_id' => !$isExternalReferral && isset($validated['refer_another']['refer_business_unit']) ? $validated['refer_another']['refer_business_unit'] : null,
                        'location' => $validated['refer_another']['refer_location'] ?? null,
                        'sequence' => $new_sequence,
                        'additional_remarks' => $validated['refer_another']['additional_remarks_refer'] ?? null,
                        'is_filled' => false,
                        'external_referee_id' => $externalRefereeId,
                        'external_organization_id' => $externalOrganizationId,
                    ]);
                }

                $referral->save();
                DB::commit();
                return response()->json(['message' => 'Referral updated successfully.'], 200);
            }
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update referral.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/referral/{id}/download",
     *     summary="Download referral PDF",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Referral ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="pdfBase64", type="string", format="byte", example="JVBERi0xLjQKJaqrrK0KMSAwIG9iago...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Business unit ID not found in session.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Referral or PDF not found.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="PDF not found for this referral.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error.")
     *         )
     *     )
     * )
     */
    public function download(Request $request, $id)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                    'data' => [],
                ], 401);
            }

            $referral = Referral::find($id);

            //check if referral exist
            if (!$referral) {
                return response()->json(['message' => 'Referral not found.'], 404);
            }

            // Check if PDF exists in the database
            if (!$referral->encoded_base || empty($referral->encoded_base)) {
                return response()->json([
                    'message' => 'PDF not found for this referral.'
                ], 404);
            }

            // Return the stored PDF base64
            return response()->json(['pdfBase64' => $referral->encoded_base], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Database error occurred.'], 500);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function exportPdf($data, $returnPdf = false)
    {
        try {
            $pdf = Pdf::loadView('pdf.report', $data);
            $pdf->setPaper('A4', 'portrait');

            // Convert PDF to base64 for JSON response (commented out)
            $pdfContent = $pdf->output();
            $base64Pdf = base64_encode($pdfContent);
            return $base64Pdf;
        } catch (Throwable $e) {
            Log::error('PDF generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @OA\Get(
     *     path="/api/referral/notification",
     *     summary="Get unread referral notifications",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Notifications retrieved successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="count", type="integer", example=3),
     *             @OA\Property(property="notifications", type="string", example="<a href='../../odb/referral/view.php?id=1'><span style='font-size:12px; font-weight:bold;'>New Referral #REF0001</span></a>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Business unit ID not found in session.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error.")
     *         )
     *     )
     * )
     */
    public function notification(Request $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;
            $listOutlets = $jwtPayload['outlet'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                    'data' => [],
                ], 401);
            }

            if (!$listOutlets || !is_array($listOutlets)) {
                return response()->json([
                    'message' => 'Outlet list not found in session.',
                    'data' => [],
                ], 401);
            }

            // Fetch unread notifications for the staff (only if business unit is the last sequence)
            $notifications = ReferralHierarchy::with(['referral'])
                ->where('business_unit_id', $businessUnitId)
                ->whereIn('location', $listOutlets)
                ->where('is_read', false)
                ->whereRaw('sequence = (SELECT MAX(sequence) FROM referral_histories WHERE referral_id = referral_histories.referral_id)')
                ->orderBy('created_at', 'desc')
                ->get();

            $noti_list = '';
            foreach ($notifications as $noti) {
                $referralId = $noti->referral_id;
                $title = "New Referral " . createRefId($referralId);
                $noti_list .= "<a href='../../odb/referral/view.php?id={$referralId}'><span style='font-size:12px; font-weight:bold;'>{$title}</span></a>";
            }

            $num = $notifications->count();

            return response()->json([
                'count' => $num,
                'notifications' => $noti_list
            ], 200, [], JSON_UNESCAPED_SLASHES);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Database error occurred.'], 500);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/referral/search",
     *     summary="Search referrals by customer ID or ref ID (Public search - cross business unit)",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="customer_id", type="integer", example=10, description="Customer ID to search for (optional if ref_id is provided)"),
     *             @OA\Property(property="ref_id", type="string", example="#REF0001", description="Referral ID to search for (optional if customer_id is provided, takes priority if both provided)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Referrals found successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="ref_id", type="string", example="#REF0001"),
     *                     @OA\Property(property="reason", type="string", example="Vestibular-Related Balance Issue"),
     *                     @OA\Property(property="from_business_unit", type="string", example="Alpro Physio"),
     *                     @OA\Property(property="to_business_unit", type="string", example="Alpro Audiology"),
     *                     @OA\Property(property="priority", type="integer", example=2),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", example="1 July 2025, Sunday"),
     *                     @OA\Property(property="ori_created_at", type="string", example="2025-07-01T10:30:00"),
     *                     @OA\Property(property="is_external", type="boolean", example=false)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Business unit ID not found in session.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Customer or referral not found.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Customer not found."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="At least one search parameter is required.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="At least one search parameter (customer_id or ref_id) is required."),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to search referrals.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to search referrals."),
     *             @OA\Property(property="error", type="string", example="Database error")
     *         )
     *     )
     * )
     */
    public function search(Request $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            // Only validate for non-superadmin users (basic JWT validation)
            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                    'data' => [],
                ], 401);
            }

            $customerId = $request->input('customer_id', '');
            $refId = $request->input('ref_id', '');

            // Validate that at least one parameter is provided
            if (empty($customerId) && empty($refId)) {
                return response()->json([
                    'message' => 'At least one search parameter (customer_id or ref_id) is required.',
                    'data' => [],
                ], 422);
            }

            // Build query based on priority: ref_id takes precedence
            // PUBLIC SEARCH - No business unit filtering
            $query = Referral::with([
                'referral_hierarchies.business_unit',
                'referral_hierarchies.external_referee',
                'referral_hierarchies.external_organization',
                'referral_hierarchies.referral_create_form'
            ]);

            if (!empty($refId)) {
                // Search by ref_id (convert to integer ID)
                $parsedId = parseRefId($refId);
                $query->where('id', $parsedId);
            } else {
                // Search by customer_id
                $query->where('customer_id', $customerId);
            }

            $referrals = $query->orderByDesc('created_at')->get();

            if ($referrals->isEmpty()) {
                return response()->json([
                    'message' => 'Customer not found.',
                    'data' => [],
                ], 404);
            }

            $results = [];

            foreach ($referrals as $ref) {
                // Find the latest sequence (maximum sequence number)
                $latestSequence = $ref->referral_hierarchies->max('sequence');

                // Get the referral hierarchy with the latest sequence
                $latestReferralHierarchy = $ref->referral_hierarchies->where('sequence', $latestSequence)->first();

                // Get the second-to-last sequence for 'from' business unit
                $secondToLastSequence = $latestSequence > 1 ? $latestSequence - 1 : 1;
                $secondToLastReferralHierarchy = $ref->referral_hierarchies->where('sequence', $secondToLastSequence)->first();

                $is_external = $latestReferralHierarchy->external_organization_id != null ? true : false;

                // Get referral reason from the second-to-last hierarchy's create form
                $referralReason = null;
                if ($secondToLastReferralHierarchy && $secondToLastReferralHierarchy->referral_create_form) {
                    $referralReason = $secondToLastReferralHierarchy->referral_create_form->referral_reason ?? null;
                }

                $referralData = [];
                if ($latestReferralHierarchy && $secondToLastReferralHierarchy && !$is_external) {
                    $referralData = [
                        'id' => $ref->id,
                        'ref_id' => createRefId($ref->id),
                        'reason' => $referralReason,
                        'from_business_unit' => $secondToLastReferralHierarchy->business_unit->name,
                        'to_business_unit' => $latestReferralHierarchy->business_unit->name,
                        'priority' => $ref->priority,
                        'status' => $ref->status,
                        'created_at' => Carbon::parse($ref->created_at)->format('j F Y, l'),
                        'ori_created_at' => $ref->created_at,
                        'is_external' => $is_external
                    ];
                } else {
                    $refereeName = optional($latestReferralHierarchy->external_referee)->name;

                    $toBusinessUnit = ($refereeName && strtolower($refereeName) !== 'n/a')
                        ? $refereeName
                        : optional($latestReferralHierarchy->external_organization)->name;


                    $referralData = [
                        'id' => $ref->id,
                        'ref_id' => createRefId($ref->id),
                        'reason' => $referralReason,
                        'from_business_unit' => $secondToLastReferralHierarchy->business_unit->name,
                        'to_business_unit' => $toBusinessUnit,
                        'priority' => $ref->priority,
                        'status' => $ref->status,
                        'created_at' => Carbon::parse($ref->created_at)->format('j F Y, l'),
                        'ori_created_at' => $ref->created_at,
                        'is_external' => $is_external
                    ];
                }

                $results[] = $referralData;
            }

            return response()->json([
                'data' => $results
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to search referrals.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function successful(Request $request, $id)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                    'data' => [],
                ], 401);
            }

            $referral = Referral::find($id);

            //check if referral exist
            if (!$referral) {
                return response()->json(['message' => 'Referral not found.'], 404);
            }

            // Check if PDF exists in the database
            if (!$referral->encoded_base || empty($referral->encoded_base)) {
                return response()->json([
                    'message' => 'PDF not found for this referral.'
                ], 404);
            }

            $customerId = $referral->customer_id;
            $patient = $this->customerDetails($customerId);
            $patientPhone = null;

            if (blank($patient)) {
                Log::info('Patient not found in ODB', [
                    'referral_id' => $referral->id,
                    'customer_id' => $customerId,
                ]);
            } else {
                $data = $patient[0] ?? $patient;
                $rawPatientPhone = data_get($data, 'phone', null);
                $patientPhone = formatMalaysianPhone($rawPatientPhone);
            }

            $lastHierarchy = $referral->referral_hierarchies->sortByDesc('sequence')->first();
            $outletPhone = $organizationPhone = $refereePhone = null;

            if (!is_null($lastHierarchy->external_organization_id)) {

                $organizationPhone = $lastHierarchy->external_organization
                    ? formatMalaysianPhone($lastHierarchy->external_organization->phone)
                    : null;
                $refereePhone = $lastHierarchy->external_referee
                    ? formatMalaysianPhone($lastHierarchy->external_referee->phone)
                    : null;
            } else {

                $locationId = $lastHierarchy->location;
                $outlet = $this->outletDetails($locationId);

                if (blank($outlet)) {

                    Log::info('Outlet not found in ODB', [
                        'referral_id' => $referral->id,
                        'outlet_id' => $locationId,
                    ]);
                } else {
                    $outlet = $outlet[0] ?? $outlet;
                    $rawOutletPhone = $outlet['office1'];
                    $outletPhone = formatMalaysianPhone($rawOutletPhone);
                }
            }

            // Return the stored PDF base64 with formatted phone numbers
            return response()->json([
                'pdfBase64' => $referral->encoded_base,
                'patientPhone' => $patientPhone,
                'outletPhone' => $outletPhone,
                'organizationPhone' => $organizationPhone,
                'refereePhone' => $refereePhone
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (QueryException $e) {
            return response()->json(['message' => 'Database error occurred.'], 500);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show NRIC verification form for public referral viewing
     *
     * @param int $id Referral ID
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function showNricForm($id)
    {
        try {
            $referral = Referral::findOrFail($id);

            // Check if session is already verified
            $sessionKey = "referral_verified_{$id}";
            if (session()->has($sessionKey) && session($sessionKey) === true) {
                // Session is valid, redirect to show PDF/status
                return $this->displayReferralContent($referral);
            }

            // Show NRIC verification form
            return view('referral.nric-form', [
                'referralId' => $id,
                'refId' => createRefId($id)
            ]);
        } catch (ModelNotFoundException $e) {
            return view('referral.not-found');
        } catch (Throwable $e) {
            Log::error('Error in showNricForm', [
                'referral_id' => $id,
                'error' => $e->getMessage()
            ]);
            return view('referral.error');
        }
    }

    /**
     * Verify NRIC and show PDF or completion message
     *
     * @param Request $request
     * @param int $id Referral ID
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function verifyAndShowPdf(Request $request, $id)
    {
        try {
            // Rate limiting: max 3 attempts per IP per referral per hour
            $rateLimitKey = "nric_verify_{$id}_{$request->ip()}";
            $attempts = cache()->get($rateLimitKey, 0);

            if ($attempts >= 3) {
                return back()->withErrors([
                    'nric' => 'Too many attempts. Please try again later.'
                ])->withInput();
            }

            // Validate input
            $request->validate([
                'nric_last4' => 'required|string|size:4'
            ]);

            $referral = Referral::findOrFail($id);
            $nricLast4 = $request->input('nric_last4');

            // Get customer NRIC from Octopus
            $customerId = $referral->customer_id;
            $patient = $this->customerDetails($customerId);

            if (blank($patient)) {
                Log::warning('Patient not found in ODB for NRIC verification', [
                    'referral_id' => $id,
                    'customer_id' => $customerId,
                ]);

                // Increment attempts
                cache()->put($rateLimitKey, $attempts + 1, now()->addHour());

                return back()->withErrors([
                    'nric' => 'Unable to verify. Please contact support.'
                ])->withInput();
            }

            $data = $patient[0] ?? $patient;
            $fullNric = data_get($data, 'ic', null);

            if (!$fullNric) {
                // Increment attempts
                cache()->put($rateLimitKey, $attempts + 1, now()->addHour());

                return back()->withErrors([
                    'nric' => 'Unable to verify. Please contact support.'
                ])->withInput();
            }

            // Verify last 4 digits
            $last4Digits = substr($fullNric, -4);

            if ($nricLast4 !== $last4Digits) {
                // Increment attempts
                cache()->put($rateLimitKey, $attempts + 1, now()->addHour());

                Log::warning('Failed NRIC verification attempt', [
                    'referral_id' => $id,
                    'ip' => $request->ip(),
                    'attempts' => $attempts + 1
                ]);

                return back()->withErrors([
                    'nric' => 'Invalid NRIC. Please check and try again.'
                ])->withInput();
            }

            // NRIC verified successfully
            Log::info('Successful NRIC verification', [
                'referral_id' => $id,
                'ip' => $request->ip()
            ]);

            // Clear rate limit on successful verification
            cache()->forget($rateLimitKey);

            // Create session (5 minutes)
            $sessionKey = "referral_verified_{$id}";
            session([
                $sessionKey => true,
                $sessionKey . '_expires_at' => now()->addMinutes(5)->timestamp
            ]);

            // Redirect back to the same route (GET) which will now show the content
            return redirect()->route('referral.nric.form', ['id' => $id]);
        } catch (ModelNotFoundException $e) {
            return view('referral.not-found');
        } catch (Throwable $e) {
            Log::error('Error in verifyAndShowPdf', [
                'referral_id' => $id,
                'error' => $e->getMessage()
            ]);
            return view('referral.error');
        }
    }

    /**
     * Display referral content (PDF or completion message)
     *
     * @param Referral $referral
     * @return \Illuminate\View\View
     */
    private function displayReferralContent($referral)
    {
        // Check if session is still valid (5 minutes)
        $sessionKey = "referral_verified_{$referral->id}";
        $expiresAt = session($sessionKey . '_expires_at');

        if (!$expiresAt || now()->timestamp > $expiresAt) {
            // Session expired, clear it and redirect to NRIC form
            session()->forget([$sessionKey, $sessionKey . '_expires_at']);
            return redirect()->route('referral.nric.form', ['id' => $referral->id]);
        }

        // Check referral status
        // Assuming status 6 = Closed (adjust based on your status values)
        if ($referral->status == 6) {
            // Status is closed - show thank you page
            return view('referral.completed', [
                'referralId' => createRefId($referral->id),
                'referral' => $referral
            ]);
        }

        // Status is not closed - return PDF directly
        $pdfBase64 = $referral->encoded_base;

        if (!$pdfBase64) {
            return view('referral.error', [
                'message' => 'PDF not available for this referral.'
            ]);
        }

        // Decode and return PDF directly to browser
        $pdfContent = base64_decode($pdfBase64);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="referral_' . createRefId($referral->id) . '.pdf"');
    }

    public function exportReferral(Referral $referral)
    {

        // Generate PDF base64 for ALL referrals (both internal and external)
        $referralData = $referral->load([
            'referral_hierarchies.business_unit',
            'referral_hierarchies.external_referee.organization',
            'referral_hierarchies.referral_create_form'
        ]);

        // Prepare data for PDF
        $firstHierarchy = $referralData->referral_hierarchies->where('sequence', 1)->first();
        $lastHierarchy = $referralData->referral_hierarchies->sortByDesc('sequence')->first();

        // Collect PDF data
        $referralId = createRefId($referral->id);
        $dateCreated = $referral->created_at->format('d F Y');

        /************************************************** Assignee *****************************************/
        // Get referral data from create form
        $referralReason = null;
        $referralCondition = null;
        $medicalHistory = null;
        $additionalRemarks = null;
        if ($firstHierarchy && $firstHierarchy->referral_create_form) {
            $referralReason = $firstHierarchy->referral_create_form->referral_reason ?? null;
            $referralCondition = normalizeValue($firstHierarchy->referral_create_form->referral_condition ?? null);
            $medicalHistory = normalizeValue($firstHierarchy->referral_create_form->medical_history ?? null);
            $additionalRemarks = normalizeValue($firstHierarchy->additional_remarks ?? null);
        }

        // Get referral details (form data)
        $referralDetailsList = [];
        if ($firstHierarchy) {
            $details = ReferralDetails::where('referral_hierarchy_id', $firstHierarchy->id)
                ->with(['form'])
                ->get();

            foreach ($details as $detail) {
                $formName = $detail->form ? $detail->form->label_name : 'Detail';
                $formValue = $detail->value;

                // If value is a form_detail_id (FK), get the field_value
                if (is_numeric($formValue)) {
                    $formDetail = FormDetails::find($formValue);
                    if ($formDetail && $formDetail->field_value) {
                        $formValue = $formDetail->field_value;
                    }
                }

                $referralDetailsList[] = [
                    'form_name' => $formName,
                    'form_value' => $formValue
                ];
            }
        }

        $assigneeBusinessUnit = $firstHierarchy->business_unit->name;
        // Get staff data from assignee
        $staffId = $firstHierarchy->staff_id;
        $assignee = $this->staffDetails($staffId);
        $assigneeName = $assigneeDesignation = $assigneePhone = $assigneeEmail = $assigneeOutletPhone = $assigneeOutletAddr = null;

        if (blank($assignee)) {
            Log::info('Staff not found in ODB', [
                'referral_id' => $referral->id,
                'staff_id' => $staffId,
            ]);
        } else {
            $staff = $assignee[0] ?? $assignee;
            $assigneeName = data_get($staff, 'nama_staff', null);
            $assigneeDesignation = data_get($staff, 'status_semasa', null);
            $assigneePhone = data_get($staff, 'hp', null);
            $assigneeEmail = data_get($staff, 'email', null);
        }

        $locationId = $firstHierarchy->location;
        $outlet = $this->outletDetails($locationId);
        if (blank($outlet)) {
            $assigneeOutletInfo = $assigneeOutletEmail = null;

            Log::info('Outlet not found in ODB', [
                'referral_id' => $referral->id,
                'outlet_id' => $locationId,
            ]);
        } else {
            $outlet = $outlet[0] ?? $outlet;
            $assigneeOutletInfo = $outlet['code'] . ', ' . $assigneeBusinessUnit;
            $assigneeOutletEmail = data_get($outlet, 'email', null);
            $assigneeOutletPhone = implode('/', array_filter([
                $outlet['office1'] ?? null,
                $outlet['office2'] ?? null,
            ]));
            $assigneeOutletAddr = data_get($outlet, 'addr', null);
        }

        /************************************************** End of Assignee *****************************************/
        /************************************************** Recipient *****************************************/

        $recipientName = $recipientPosition = $recipientPhone = $recipientEmail = null;
        $recipientBusinessUnit = $lastHierarchy->business_unit->name;

        if (!is_null($lastHierarchy->staff_id)) {

            // Get staff id from recipient
            $staffId = $lastHierarchy->staff_id;
            $recipient = $this->staffDetails($staffId);

            if (blank($recipient)) {
                Log::info('Staff not found in ODB', [
                    'referral_id' => $referral->id,
                    'staff_id' => $staffId,
                ]);
            } else {
                $staff = $recipient[0] ?? $recipient;
                $recipientName = data_get($staff, 'nama_staff', null);
                $recipientPosition = data_get($staff, 'status_semasa', null);
                $recipientBusinessUnit = $lastHierarchy->business_unit->name ?? null;
                $recipientPhone = data_get($staff, 'hp', null);
                $recipientEmail = data_get($staff, 'email', null);
            }
        }

        /************************************************** Location *****************************************/

        // Get location id from recipient
        $locationId = $lastHierarchy->location;
        $outlet = $this->outletDetails($locationId);

        if (blank($outlet)) {
            $recipientOutletInfo = $recipientOutletEmail = $recipientOutletPhone = $recipientOutletAddr = null;

            Log::info('Outlet not found in ODB', [
                'referral_id' => $referral->id,
                'outlet_id' => $locationId,
            ]);
        } else {
            $outlet = $outlet[0] ?? $outlet;
            $recipientOutletInfo = $outlet['code'] . ', ' . $recipientBusinessUnit;
            $recipientOutletEmail = data_get($outlet, 'email', null);
            $recipientOutletPhone = implode('/', array_filter([
                $outlet['office1'] ?? null,
                $outlet['office2'] ?? null,
            ]));
            $recipientOutletAddr = data_get($outlet, 'addr', null);
        }


        /************************************************** End of Location *****************************************/
        /************************************************** End of Recipient *****************************************/

        /************************************************** Customer *****************************************/

        // Get customer_id
        $customerId = $referral->customer_id;
        $patient = $this->customerDetails($customerId);
        $patientName = $patientIcNo = $patientPhone = $patientAddress = $patientEmail = null;

        if (blank($patient)) {

            Log::info('Patient not found in ODB', [
                'referral_id' => $referral->id,
                'customer_id' => $customerId,
            ]);
        } else {
            $data = $patient[0] ?? $patient;
            $patientName = data_get($data, 'customer_name', null);
            $patientIcNo = data_get($data, 'ic', null);
            $patientPhone = data_get($data, 'phone', null);
            $patientAddress = data_get($data, 'c_addr', null);
            $patientEmail = data_get($data, 'email', null);
        }

        /************************************************** End of Customer *****************************************/

        $data = [
            'is_external' => false,
            'referralId' => $referralId,
            'dateCreated' => $dateCreated,

            'assigneeName' => $assigneeName,
            'assigneeDesignation' => $assigneeDesignation,
            'assigneeBusinessUnit' => $assigneeBusinessUnit,
            'assigneePhone' => $assigneePhone,
            'assigneeEmail' => $assigneeEmail,

            'assigneeOutletInfo' => $assigneeOutletInfo,
            'assigneeOutletAddr' => $assigneeOutletAddr,
            'assigneeOutletPhone' => $assigneeOutletPhone,
            'assigneeOutletEmail' => $assigneeOutletEmail,

            'referralReason' => $referralReason,
            'referralCondition' => $referralCondition,
            'medicalHistory' => $medicalHistory,
            'additionalRemarks' => $additionalRemarks,

            'referralDetails' => $referralDetailsList,

            'recipientName' => $recipientName,
            'recipientPosition' => $recipientPosition,
            'recipientPhone' => $recipientPhone,
            'recipientEmail' => $recipientEmail,
            'recipientBusinessUnit' => $recipientBusinessUnit,

            'recipientOutletInfo' => $recipientOutletInfo,
            'recipientOutletEmail' => $recipientOutletEmail,
            'recipientOutletPhone' => $recipientOutletPhone,
            'recipientOutletAddr' => $recipientOutletAddr,

            'patientName' => $patientName,
            'patientIcNo' => $patientIcNo,
            'patientPhone' => $patientPhone,
            'patientAddress' => $patientAddress,
            'patientEmail' => $patientEmail,
        ];

        // Generate PDF with QR code using helper function
        $pdfBase64 = generateReferralPdfWithQr($referral->id, $data);
        if ($pdfBase64) {
            $response['pdf_base64'] = $pdfBase64;

            // $pdfContent = base64_decode($pdfBase64);

            // return response($pdfContent)
            //     ->header('Content-Type', 'application/pdf')
            //     ->header('Content-Disposition', 'inline; filename="referral.pdf"');

            // Store the PDF base64 in referral record
            $referral->encoded_base = $pdfBase64;
            $referral->save();
        }

        // Send email to recipient outlet if email exists and is valid
        if ($recipientOutletEmail && filter_var($recipientOutletEmail, FILTER_VALIDATE_EMAIL)) {
            try {
                // Get referral attachments
                $referralAttachments = $firstHierarchy->referral_attachments->map(function ($attachment) {
                    return [
                        'file_name' => $attachment->file_name,
                        'file_type' => $attachment->file_type,
                        'encoded_base' => $attachment->encoded_base
                    ];
                })->toArray();

                Mail::to($recipientOutletEmail)->send(
                    new ExternalReferralNotification(
                        $referralId,
                        $dateCreated,
                        $referralReason,
                        $referralCondition,
                        $medicalHistory,
                        $additionalRemarks,
                        $referralDetailsList,
                        $recipientName,
                        $recipientPosition,
                        $recipientBusinessUnit, // organization name (business unit for internal)
                        $recipientOutletAddr, // organization address (outlet address)
                        $patientName,
                        $patientIcNo,
                        $patientPhone,
                        $patientAddress,
                        $patientEmail,
                        $assigneeName, // referrer name
                        $assigneeDesignation, // referrer designation
                        $assigneeBusinessUnit, // referrer business unit
                        $assigneePhone, // referrer phone
                        $assigneeEmail, // referrer email
                        $pdfBase64,
                        $referralAttachments
                    )
                );

                Log::info('Referral notification email sent', [
                    'referral_id' => $referral->id,
                    'recipient_email' => $recipientOutletEmail
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to send referral notification email', [
                    'referral_id' => $referral->id,
                    'recipient_email' => $recipientOutletEmail,
                    'error' => $e->getMessage()
                ]);
                // Don't fail the request if email fails
            }
        } else {
            Log::info('Skipping email notification - no valid recipient email', [
                'referral_id' => $referral->id,
                'recipient_outlet_email' => $recipientOutletEmail
            ]);
        }
    }
}