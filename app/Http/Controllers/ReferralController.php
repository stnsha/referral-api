<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralRequest;
use App\Models\BusinessUnit;
use App\Models\FormDetails;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralDetails;
use App\Models\ReferralHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferralController extends Controller
{
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

        if (!$businessUnitId) {
            return response()->json([
                'message' => 'Business unit ID not found in session.',
                'data' => [],
            ], 401);
        }

        $referrals = Referral::with(['referral_histories.business_unit', 'referral_histories.external_referee'])
            ->whereHas('referral_histories', function ($query) use ($businessUnitId) {
                $query->where('business_unit_id', $businessUnitId);
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
            $latestSequence = $ref->referral_histories->max('sequence');

            // Get the referral history with the latest sequence
            $latestReferralHistory = $ref->referral_histories->where('sequence', $latestSequence)->first();

            // Get the first sequence for referral reason (from original logic)
            $firstReferralHistory = $ref->referral_histories->where('sequence', 1)->first();

            // Check if current business unit is involved in this referral
            $currentBusinessUnitHistory = $ref->referral_histories->where('business_unit_id', $businessUnitId)->first();

            if (!$currentBusinessUnitHistory) {
                continue;
            }

            $is_external = $latestReferralHistory->external_referee_id != null ? true : false;

            $referralData = [];
            if ($latestReferralHistory && $firstReferralHistory && !$is_external) {
                $referralData = [
                    'id' => $ref->id,
                    'ref_id' => createRefId($ref->id),
                    'reason' => $firstReferralHistory->referral_reason,
                    'from_business_unit' => $firstReferralHistory->business_unit->name,
                    'to_business_unit' => $latestReferralHistory->business_unit->name,
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
                    'reason' => $firstReferralHistory->referral_reason,
                    'from_business_unit' => $firstReferralHistory->business_unit->name,
                    'to_business_unit' => $latestReferralHistory->external_referee->name,
                    'priority' => $ref->priority,
                    'status' => $ref->status,
                    'created_at' => Carbon::parse($ref->created_at)->format('j F Y, l'),
                    'ori_created_at' => $ref->created_at,
                    'is_external' => $is_external
                ];
            }

            // Add to all category (any referral that has business unit included)
            $all[] = $referralData;

            // Sent: sequence = 1 OR when business unit refers to another (not the last one)
            if (
                $currentBusinessUnitHistory->sequence == 1 ||
                ($currentBusinessUnitHistory->sequence < $latestSequence)
            ) {
                $sent[] = $referralData;
            }

            // Received: sequence not equal to 1
            if ($currentBusinessUnitHistory->sequence != 1) {
                $received[] = $referralData;
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
     *     summary="Create a new referral",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_units", "referral", "required_treatment"},
     *             @OA\Property(property="business_units", type="object",
     *                 @OA\Property(property="assignee", type="object",
     *                     @OA\Property(property="staff_id", type="integer", example=2222),
     *                     @OA\Property(property="business_unit_id", type="string", example="6"),
     *                     @OA\Property(property="location", type="string", example="101"),
     *                     @OA\Property(property="referral_reason", type="string", example="Vestibular-Related Balance Issue"),
     *                     @OA\Property(property="referral_condition", type="string", example="Patient reports persistent dizziness and unsteadiness during standing and walking exercises. Symptoms suggest possible vestibular involvement that is beyond musculoskeletal causes. Referral to audiology is requested for further assessment and vestibular testing."),
     *                     @OA\Property(property="medical_history", type="string", example=""),
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
     *             @OA\Property(property="required_treatment", type="array",
     *                 @OA\Items(type="integer", example=1),
     *                 example={1, 2, 3},
     *                 description="Array of treatment IDs (PK). Must have at least 1 item. Associated with the referral history that has the highest sequence (recipient)."
     *             ),
     *             @OA\Property(property="attachments", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string", example="Labs.png"),
     *                     @OA\Property(property="type", type="string", example="image/png"),
     *                     @OA\Property(property="size", type="integer", example=14040),
     *                     @OA\Property(property="base64", type="string", format="byte", example="iVBORw0KGgoAAAANSUhEUgAAALUAAAC2CAYAA"),
     *                     description="Attachments will be associated with the referral history that has sequence 1 (assignee)."
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Referral created successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1234)
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
     *         response=422,
     *         description="Validation failed.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed."),
     *             @OA\Property(property="errors", type="object")
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

                // Validate that the assignee business unit matches JWT business unit
                if ($business_unit_id != $businessUnitId) {
                    return response()->json([
                        'message' => 'Unauthorized: Cannot create referral for different business unit.',
                    ], 403);
                }

                //create referral
                $referral = Referral::create([
                    'customer_id' => $validated['referral']['customer_id'],
                    'priority' => $validated['referral']['priority'],
                    'status' => 1, //Open
                ]);


                //run through businessunits
                foreach (array_values($businessUnits) as $key => $value) {
                    //compile data
                    $data = [
                        'referral_id' => $referral->id,
                        'staff_id' => ($value['staff_id'] ?? 0) != 0 ? $value['staff_id'] : null,
                        'business_unit_id' => isset($value['business_unit_id']) ? $value['business_unit_id'] : null,
                        'location' =>  isset($value['location']) ? $value['location'] : null,
                        'sequence' => $key + 1,
                        'external_referee_id' =>  isset($value['referee']) ? $value['referee'] : null
                    ];

                    $new_status = isset($value['referee']) ? 4 : 1;
                    $referral->status = $new_status;

                    //check if exist
                    if (
                        isset($value['referral_reason']) ||
                        isset($value['referral_condition']) ||
                        isset($value['medical_history']) ||
                        isset($value['additional_remarks'])
                    ) {
                        $data['referral_reason'] = $value['referral_reason'] ?? '';
                        $data['referral_condition'] = $value['referral_condition'] ?? '';
                        $data['medical_history'] = $value['medical_history'] ?? '';
                        $data['additional_remarks'] = $value['additional_remarks'] ?? '';
                    }

                    //create referral history
                    ReferralHistory::create($data);
                }

                // Check if this is an external referral
                $isExternalReferral = false;
                foreach ($validated['business_units'] as $value) {
                    if (isset($value['referee']) || (isset($value['recipient']) && isset($value['recipient']['referee']))) {
                        $isExternalReferral = true;
                        break;
                    }
                }

                // Get referral history with max sequence for required_treatment (only for non-external referrals)
                if (!$isExternalReferral) {
                    $maxSequenceHistory = ReferralHistory::where('referral_id', $referral->id)
                        ->orderBy('sequence', 'desc')
                        ->first();

                    if (filled($request['required_treatment']) && $maxSequenceHistory) {
                        foreach ($request['required_treatment'] as $form_id) {
                            ReferralDetails::create([
                                'referral_history_id' => $maxSequenceHistory->id,
                                'form_id' => $form_id,
                            ]);
                        }
                    }
                }

                // Get referral history with sequence 1 for attachments
                $firstSequenceHistory = ReferralHistory::where('referral_id', $referral->id)
                    ->where('sequence', 1)
                    ->first();

                //run through attachments if exist
                if (filled($request['attachments']) && $firstSequenceHistory) {
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
                            'referral_history_id' => $firstSequenceHistory->id,
                            'file_name' => $atc['name'],
                            'file_type' => $atc['type'],
                            'file_size' => $atc['size'],
                            'encoded_base' => $atc['base64']
                        ]);

                        $extension = $mimeMap[$atc['type']] ?? pathinfo($atc['name'], PATHINFO_EXTENSION);
                        $newFileName = $firstSequenceHistory->business_unit->name != null
                            ? str_replace(' ', '_', $firstSequenceHistory->business_unit->name)
                            : pathinfo($atc['name'], PATHINFO_FILENAME);

                        $suffix = $referral->id . $firstSequenceHistory->id . $referralAttachment->id;
                        $referralAttachment->file_name = $newFileName . '_' . $suffix . '.' . $extension;
                        $referralAttachment->save();
                    }
                }

                $referral->save();

                $response = ['id' => $referral->id];

                // Generate PDF base64 if external referral
                if ($isExternalReferral) {
                    $referralData = $referral->load(['referral_histories']);
                    $data = [
                        'referral_id' => createRefId($referral->id),
                        'status' => $referral->status,
                        'status_note' => $referral->status_note,
                        'customer_id' => $referral->customer_id,
                        'priority' => $referral->priority,
                        'referralDetails' => $referralData->referral_histories,
                    ];

                    $pdf = $this->exportPdf($data);
                    if ($pdf) {
                        $response['pdf_base64'] = base64_encode($pdf->output());
                    }
                }

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
                'error' => $e->getMessage(),
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
     *                             @OA\Property(property="specialty", type="string", example="Cardiology"),
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
     *             @OA\Property(property="pdf_base64", type="string", format="byte", nullable=true, example=null, description="Present only for external referrals")
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

            if (!$businessUnitId) {
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
                'referral_histories.business_unit',
                'referral_histories.external_referee.organization',
                'referral_histories.referral_details.form.form_details',
                'referral_histories.referral_attachments'
            ]);

            //check if referral accessible by this business unit
            $exists = $referral->referral_histories->contains('business_unit_id', $businessUnitId);

            if (!$exists) {
                return response()->json(['message' => 'Referral not accessible.'], 403);
            }

            //initialize for default value
            $referral_reason = '';
            $business_unit_id = '';
            $referral_condition = '';
            $medical_history  = '';
            $is_external = false;
            //get referral histories
            $referralHistories = $referral->referral_histories
                ->sortBy('sequence')
                ->values()
                ->map(function ($rh) use (
                    &$referral_reason,
                    &$business_unit_id,
                    &$referral_condition,
                    &$medical_history,
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

                    //get external referee
                    $external_referral = [];

                    if ($rh->external_referee_id) {
                        $is_external = true;
                        $external_referee = $rh->external_referee;
                        $external_referral[] = [
                            'external_referee_id' => $external_referee->id,
                            'name' => $external_referee->name,
                            'email' => $external_referee->email,
                            'phone' => $external_referee->phone,
                            'position' => $external_referee->position,
                            'specialty' => $external_referee->specialty,
                            'external_organization_id' => $external_referee->organization->id,
                            'organization' => $external_referee->organization->name,
                            'address' => $external_referee->organization->address,
                            'postcode' => $external_referee->organization->postcode,
                            'state' => $external_referee->organization->state,
                            'country' => $external_referee->organization->country,
                        ];
                    }

                    // Determine is_filled based on sequence and ReferralDetails values
                    $is_filled = true; // Default for sequence 1
                    if ($rh->sequence != 1) {
                        // For non-first sequences, check if referral details exist and ALL have non-null values
                        if ($rh->referral_details->isEmpty()) {
                            $is_filled = false;
                        } else {
                            $is_filled = $rh->referral_details->every(function ($rd) {
                                return $rd->value !== null;
                            });
                        }
                    }

                    //return histories data with attachments
                    return [
                        'sequence' => $rh->sequence,
                        'staff_id' => $rh->staff_id,
                        'location' => $rh->location,
                        'business_unit_id' => $rh->business_unit_id,
                        'created_at' => Carbon::parse($rh->created_at)->format('d F Y'),
                        'referral_reason' => $rh->referral_reason,
                        'referral_condition' => $rh->referral_condition,
                        'medical_history' => $rh->medical_history,
                        'additional_remarks' => $rh->additional_remarks,
                        'is_filled' => $is_filled,
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
                'referralDetails' => $referralHistories,
                // 'referringIndication' => $referringIndication,
            ];

            if ($is_external) {
                //get pdf base64
                $pdf = $this->exportPdf($data);
                if ($pdf) {
                    $data['pdf_base64'] = base64_encode($pdf->output());
                }
            }

            return response()->json($data, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Referral not found.'
            ], 404);
        } catch (\Exception $e) {
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
                $referral = Referral::with(['referral_histories', 'referral_histories.referral_details'])->find($referral_id);

                //update status
                $referral->status = $validated['referral']['status'];

                if ($validated['referral']['status'] == 5 && $validated['referral']['status_note'] != '') {
                    $referral->status_note = $validated['referral']['status_note'];
                }

                $referral_history_id = '';

                foreach ($referral->referral_histories as $rh) {
                    $is_external = is_null($rh->external_referee_id) ? true : false;

                    if ($rh->business_unit_id == $business_unit_id && $is_external) {
                        $referral_reason = isset($validated['referral']['referral_reason']) ? $validated['referral']['referral_reason'] : null;
                        $referral_condition = isset($validated['referral']['referral_condition']) ? $validated['referral']['referral_condition'] : null;
                        $medical_history = isset($validated['referral']['medical_history']) ? $validated['referral']['medical_history'] : null;
                        $referral_history_id = $rh->id;

                        if (is_null($rh->staff_id)) {
                            $rh->staff_id = !empty($validated['referral']['updated_recipient_to'])
                                ? $validated['referral']['updated_recipient_to']
                                : $staffId;
                        }

                        $rh->additional_remarks = $validated['referral']['additional_remarks'] ?? $rh->additional_remarks;

                        $rh->referral_reason = $referral_reason ?? $rh->referral_reason;
                        $rh->referral_condition = $referral_condition ?? $rh->referral_condition;
                        $rh->medical_history = $medical_history ?? $rh->medical_history;
                        $rh->save();

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
                                            'referral_history_id' => $referral_history_id,
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
                                Log::info('Processing attachment', ['raw' => $atc]);

                                $referralAttachment = ReferralAttachment::create([
                                    'referral_history_id' => $referral_history_id,
                                    'file_name' => $atc['name'],
                                    'file_type' => $atc['type'],
                                    'file_size' => $atc['size'],
                                    'encoded_base' => $atc['base64']
                                ]);

                                Log::info('Created referral attachment record', [
                                    'id' => $referralAttachment->id,
                                    'initial_file_name' => $atc['name'],
                                    'file_type' => $atc['type']
                                ]);

                                $business_unit = $rh->business_unit->name;
                                $extension = $mimeMap[$atc['type']] ?? pathinfo($atc['name'], PATHINFO_EXTENSION);

                                Log::info('Extension determined', [
                                    'mime_type' => $atc['type'],
                                    'mapped_extension' => $mimeMap[$atc['type']] ?? null,
                                    'final_extension' => $extension
                                ]);

                                $newFileName = $business_unit != null ? str_replace(' ', '_', $business_unit) : pathinfo($atc['name'], PATHINFO_FILENAME);
                                $suffix = $referral->id . $referral_history_id . $referralAttachment->id;

                                Log::info('Building final file name', [
                                    'business_unit' => $business_unit,
                                    'newFileName' => $newFileName,
                                    'suffix' => $suffix
                                ]);

                                $referralAttachment->file_name = $newFileName . '_' . $suffix . '.' . $extension;
                                $referralAttachment->save();

                                Log::info('Final file name saved', [
                                    'saved_file_name' => $referralAttachment->file_name
                                ]);
                            }
                        }
                    }
                }

                $referral->save();

                //create history for next referee
                if (isset($validated['refer_another'])) {
                    $refer_business_unit = isset($validated['refer_another']['refer_business_unit']) ? $validated['refer_another']['refer_business_unit'] : null;
                    $refer_location = isset($validated['refer_another']['refer_location']) ? $validated['refer_another']['refer_location'] : null;
                    $refer_to = isset($validated['refer_another']['refer_to']) ? $validated['refer_another']['refer_to'] : null;

                    $total_rh = count($referral->referral_histories);

                    $referral_history = ReferralHistory::create([
                        'referral_id' => $referral->id,
                        'staff_id' => $refer_to,
                        'business_unit_id' => $refer_business_unit,
                        'location' => $refer_location,
                        'sequence' => $total_rh + 1
                    ]);

                    $referral_history_id = $referral_history->id;
                }
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

            // Load necessary relationships
            $referral->load([
                'referral_histories.business_unit',
                'referral_histories.external_referee.organization',
                'referral_histories.referral_details.form.form_details',
                'referral_histories.referral_attachments'
            ]);

            //check if referral accessible by this business unit
            $exists = $referral->referral_histories->contains('business_unit_id', $businessUnitId);

            if (!$exists) {
                return response()->json(['message' => 'Referral not accessible.'], 403);
            }

            //initialize for default value
            $is_external = false;
            //get referral histories
            $referralHistories = $referral->referral_histories
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

                    //get external referee
                    $external_referral = [];

                    if ($rh->external_referee_id) {
                        $is_external = true;
                        $external_referee = $rh->external_referee;
                        $external_referral[] = [
                            'external_referee_id' => $external_referee->id,
                            'name' => $external_referee->name,
                            'email' => $external_referee->email,
                            'phone' => $external_referee->phone,
                            'position' => $external_referee->position,
                            'specialty' => $external_referee->specialty,
                            'external_organization_id' => $external_referee->organization->id,
                            'organization' => $external_referee->organization->name,
                            'address' => $external_referee->organization->address,
                            'postcode' => $external_referee->organization->postcode,
                            'state' => $external_referee->organization->state,
                            'country' => $external_referee->organization->country,
                        ];
                    }

                    // Determine is_filled based on sequence and ReferralDetails values
                    $is_filled = true; // Default for sequence 1
                    if ($rh->sequence != 1) {
                        // For non-first sequences, check if referral details exist and ALL have non-null values
                        if ($rh->referral_details->isEmpty()) {
                            $is_filled = false;
                        } else {
                            $is_filled = $rh->referral_details->every(function ($rd) {
                                return $rd->value !== null;
                            });
                        }
                    }

                    //return histories data with attachments
                    return [
                        'sequence' => $rh->sequence,
                        'staff_id' => $rh->staff_id,
                        'location' => $rh->location,
                        'business_unit_id' => $rh->business_unit_id,
                        'created_at' => Carbon::parse($rh->created_at)->format('d F Y'),
                        'referral_reason' => $rh->referral_reason,
                        'referral_condition' => $rh->referral_condition,
                        'medical_history' => $rh->medical_history,
                        'additional_remarks' => $rh->additional_remarks,
                        'is_filled' => $is_filled,
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
                'referralDetails' => $referralHistories,
                // 'referringIndication' => $referringIndication,
            ];

            // Generate and return PDF for download
            $pdf = $this->exportPdf($data, true);
            if ($pdf) {
                return $pdf->download('referral_' . createRefId($referral->id) . '.pdf');
            } else {
                return response()->json(['message' => 'Failed to generate PDF'], 500);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function exportPdf($data, $returnPdf = false)
    {
        try {
            $pdf = Pdf::loadView('pdf.report', $data);
            $pdf->setPaper('A4', 'portrait');

            // Return PDF object for download
            if ($returnPdf) {
                return $pdf;
            }

            // Generate PDF using barryvdh/laravel-dompdf and return the PDF object
            return $pdf;

            // Convert PDF to base64 for JSON response (commented out)
            // $pdfContent = $pdf->output();
            // $base64Pdf = base64_encode($pdfContent);
            // return $base64Pdf;
        } catch (\Exception $e) {
            Log::error('PDF generation failed: ' . $e->getMessage());
            return null;
        }
    }
}