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
use App\Models\ReferralHistory;
use App\Models\ReferralReplyForm;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        $referrals = Referral::with([
                'referral_hierarchies.business_unit',
                'referral_hierarchies.external_referee',
                'referral_hierarchies.external_organization',
                'referral_hierarchies.referral_create_form'
            ])
            ->whereHas('referral_hierarchies', function ($query) use ($businessUnitId) {
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
            $latestSequence = $ref->referral_hierarchies->max('sequence');

            // Get the referral hierarchy with the latest sequence
            $latestReferralHierarchy = $ref->referral_hierarchies->where('sequence', $latestSequence)->first();

            // Get the second-to-last sequence for 'from' business unit
            $secondToLastSequence = $latestSequence > 1 ? $latestSequence - 1 : 1;
            $secondToLastReferralHierarchy = $ref->referral_hierarchies->where('sequence', $secondToLastSequence)->first();

            // Check if current business unit is involved in this referral
            $currentBusinessUnitHierarchy = $ref->referral_hierarchies->where('business_unit_id', $businessUnitId)->first();

            if (!$currentBusinessUnitHierarchy) {
                continue;
            }

            $is_external = $latestReferralHierarchy->external_organization_id != null ? true : false;

            // Get referral reason from the second-to-last hierarchy's create form
            $referralReason = 'N/A';
            if ($secondToLastReferralHierarchy && $secondToLastReferralHierarchy->referral_create_form) {
                $referralReason = $secondToLastReferralHierarchy->referral_create_form->referral_reason ?? 'N/A';
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
                    'to_business_unit' => $latestReferralHierarchy->external_referee ? $latestReferralHierarchy->external_referee->name : ($latestReferralHierarchy->external_organization ? $latestReferralHierarchy->external_organization->name : 'N/A'),
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
     *     summary="Create a new referral",
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

                // Determine customer_id based on external referral
                $isExternalReferral = isset($businessUnits['recipient']['referee'])
                    || isset($businessUnits['recipient']['new_organization'])
                    || isset($businessUnits['recipient']['new_recipient'])
                    || isset($businessUnits['recipient']['organization']);
                $customerId = $isExternalReferral && isset($validated['referral']['patient']['id'])
                    ? $validated['referral']['patient']['id']
                    : $validated['referral']['customer_id'];

                //create referral
                $referral = Referral::create([
                    'customer_id' => $customerId,
                    'priority' => $validated['referral']['priority'],
                    'status' => 1, //Open
                ]);

                // Handle new organization and recipient creation for external referrals
                $newOrganizationId = null;
                $newRefereeId = null;

                if (isset($businessUnits['recipient']['new_organization'])) {
                    $newOrgData = $businessUnits['recipient']['new_organization'];
                    $newOrganization = ExternalOrganization::create([
                        'name' => $newOrgData['name'],
                        'address' => $newOrgData['address'] ?? null,
                        'postcode' => $newOrgData['postcode'] ?? null,
                        'state' => $newOrgData['state'] ?? null,
                        'country' => $newOrgData['country'] ?? null,
                    ]);
                    $newOrganizationId = $newOrganization->id;
                }

                if (isset($businessUnits['recipient']['new_recipient'])) {
                    $newRecipientData = $businessUnits['recipient']['new_recipient'];

                    // Priority: newly created org > existing org ID > null
                    $orgId = $newOrganizationId ?? $businessUnits['recipient']['organization'] ?? null;

                    $newReferee = ExternalReferee::create([
                        'name' => $newRecipientData['name'],
                        'email' => $newRecipientData['email'] ?? null,
                        'phone' => $newRecipientData['phone'] ?? null,
                        'position' => $newRecipientData['position'] ?? null,
                        'external_organization_id' => $orgId,
                    ]);
                    $newRefereeId = $newReferee->id;
                }

                // If new organization created but no new recipient, still need to update recipient to use new org
                if ($newOrganizationId && !$newRefereeId && isset($businessUnits['recipient']['organization'])) {
                    // This case shouldn't happen based on the examples, but keeping for safety
                }

                //run through businessunits
                foreach (array_values($businessUnits) as $key => $value) {

                    //for second level of business unit
                    $is_filled = false;
                    $is_read = false;

                    if (isset($value['business_unit_id']) && $business_unit_id == $value['business_unit_id']) {
                        $is_filled = true;
                        $is_read = true;
                    }

                    // Determine if this is the recipient (has external referee/organization data)
                    $isRecipient = isset($value['referee']) || isset($value['new_recipient']) || isset($value['new_organization']) || isset($value['organization']);

                    // Determine external_organization_id for external referrals
                    $externalOrganizationId = null;
                    if ($isRecipient) {
                        // If only existing organization is selected (no referee)
                        if (isset($value['organization']) && !isset($value['referee']) && !isset($value['new_recipient'])) {
                            $externalOrganizationId = $value['organization'];
                        }
                        // If new organization was created (with or without new recipient)
                        elseif ($newOrganizationId) {
                            $externalOrganizationId = $newOrganizationId;
                        }
                        // If existing organization is specified for new recipient
                        elseif (isset($value['new_recipient']) && isset($value['organization'])) {
                            $externalOrganizationId = $value['organization'];
                        }
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
                        'is_read' => $is_read,
                        'external_referee_id' =>  $isRecipient ? ($newRefereeId ?? (isset($value['referee']) ? $value['referee'] : null)) : null,
                        'external_organization_id' => $externalOrganizationId
                    ];

                    $new_status = $isRecipient ? 4 : 1;
                    $referral->status = $new_status;

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

                // Check if any referral history has external referee (is_external)
                $hasExternalReferee = false;
                foreach ($validated['business_units'] as $value) {
                    if (isset($value['referee']) || $newRefereeId) {
                        $hasExternalReferee = true;
                        break;
                    }
                }

                $response = ['id' => $referral->id];

                // Generate PDF base64 if external referral
                if ($hasExternalReferee) {
                    $referralData = $referral->load(['referral_hierarchies.external_referee.organization', 'referral_hierarchies.referral_create_form']);

                    // Prepare data for PDF (reuse data collection from email)
                    $firstHierarchy = $referralData->referral_hierarchies->where('sequence', 1)->first();
                    $externalRefereeHierarchy = $referralData->referral_hierarchies->firstWhere('external_referee_id', '!=', null);

                    // Collect PDF data
                    $referralId = createRefId($referral->id);
                    $dateCreated = $referral->created_at->format('d F Y');

                    // Get referral data from create form
                    $referralReason = 'N/A';
                    $referralCondition = '';
                    $medicalHistory = '';
                    if ($firstHierarchy && $firstHierarchy->referral_create_form) {
                        $referralReason = $firstHierarchy->referral_create_form->referral_reason ?? 'N/A';
                        $referralCondition = $firstHierarchy->referral_create_form->referral_condition ?? '';
                        $medicalHistory = $firstHierarchy->referral_create_form->medical_history ?? '';
                    }
                    $additionalRemarks = $firstHierarchy ? $firstHierarchy->additional_remarks : '';

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

                    // Get external referee and organization data
                    $recipientName = '';
                    $recipientPosition = '';
                    $recipientPhone = '';
                    $recipientEmail = '';
                    $organizationName = '';
                    $organizationAddress = '';

                    if ($externalRefereeHierarchy && $externalRefereeHierarchy->external_referee) {
                        $externalReferee = $externalRefereeHierarchy->external_referee;
                        $recipientName = $externalReferee->name ?? '';
                        $recipientPosition = $externalReferee->position ?? '';
                        $recipientPhone = $externalReferee->phone ?? '';
                        $recipientEmail = $externalReferee->email ?? '';

                        if ($externalReferee->organization) {
                            $organizationName = $externalReferee->organization->name ?? '';
                            $addressParts = array_filter([
                                $externalReferee->organization->address ?? '',
                                $externalReferee->organization->postcode ?? '',
                                $externalReferee->organization->state ?? '',
                                $externalReferee->organization->country ?? ''
                            ]);
                            $organizationAddress = implode(', ', $addressParts);
                        }
                    }

                    // If recipient data is empty (e.g., new organization without recipient), use organization data
                    if (empty($recipientEmail) && $newOrganizationId && !$newRefereeId) {
                        // This is the case where only new organization was created
                        $newOrg = ExternalOrganization::find($newOrganizationId);
                        if ($newOrg) {
                            $organizationName = $newOrg->name;
                            $addressParts = array_filter([
                                $newOrg->address,
                                $newOrg->postcode,
                                $newOrg->state,
                                $newOrg->country
                            ]);
                            $organizationAddress = implode(', ', $addressParts);
                        }
                    }

                    // Get patient data from request
                    $patient = $validated['referral']['patient'] ?? null;
                    $patientName = $patient['name'] ?? 'N/A';
                    $patientIcNo = $patient['ic'] ?? 'N/A';
                    $patientPhone = $patient['phone'] ?? 'N/A';
                    $patientAddress = $patient['address'] ?? 'N/A';
                    $patientEmail = $patient['email'] ?? '';

                    // Get referee (staff) data from assignee
                    $assignee = $validated['business_units']['assignee'] ?? null;
                    $referrerName = $assignee['nama_staff'] ?? 'N/A';
                    $referrerDesignation = $assignee['designation'] ?? 'N/A';
                    $referrerBusinessUnit = BusinessUnit::find($businessUnitId)->name ?? 'N/A';
                    $referrerPhone = $assignee['contact'] ?? 'N/A';
                    $referrerEmail = $assignee['email'] ?? 'N/A';

                    $data = [
                        'referralId' => $referralId,
                        'dateCreated' => $dateCreated,
                        'referralReason' => $referralReason,
                        'referralCondition' => $referralCondition,
                        'medicalHistory' => $medicalHistory,
                        'additionalRemarks' => $additionalRemarks,
                        'referralDetails' => $referralDetailsList,
                        'recipientName' => $recipientName,
                        'recipientPosition' => $recipientPosition,
                        'recipientPhone' => $recipientPhone,
                        'organizationName' => $organizationName,
                        'organizationAddress' => $organizationAddress,
                        'patientName' => $patientName,
                        'patientIcNo' => $patientIcNo,
                        'patientPhone' => $patientPhone,
                        'patientAddress' => $patientAddress,
                        'patientEmail' => $patientEmail,
                        'referrerName' => $referrerName,
                        'referrerDesignation' => $referrerDesignation,
                        'referrerBusinessUnit' => $referrerBusinessUnit,
                        'referrerPhone' => $referrerPhone,
                        'referrerEmail' => $referrerEmail,
                    ];

                    $pdfBase64 = $this->exportPdf($data);
                    if ($pdfBase64) {
                        $response['pdf_base64'] = $pdfBase64;

                        // Store the PDF base64 in referral record
                        $referral->encoded_base = $pdfBase64;
                        $referral->save();
                    }

                    // Send email to external referee if email exists
                    if ($externalRefereeHierarchy && $externalRefereeHierarchy->external_referee && $pdfBase64) {
                        $externalReferee = $externalRefereeHierarchy->external_referee;

                        if ($externalReferee->email) {
                            try {
                                // Get attachments from the referral hierarchy with is_filled = true
                                $referralAttachments = [];
                                $filledHierarchy = $referralData->referral_hierarchies->where('is_filled', true)->first();
                                if ($filledHierarchy) {
                                    $attachments = ReferralAttachment::where('referral_hierarchy_id', $filledHierarchy->id)->get();
                                    $referralAttachments = $attachments->map(function ($atc) {
                                        return [
                                            'file_name' => $atc->file_name,
                                            'file_type' => $atc->file_type,
                                            'encoded_base' => $atc->encoded_base
                                        ];
                                    })->toArray();
                                }

                                // Reuse data already collected for PDF
                                Mail::to($recipientEmail)->send(
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
                                        $organizationName,
                                        $organizationAddress,
                                        $patientName,
                                        $patientIcNo,
                                        $patientPhone,
                                        $patientAddress,
                                        $patientEmail,
                                        $referrerName,
                                        $referrerDesignation,
                                        $referrerBusinessUnit,
                                        $referrerPhone,
                                        $referrerEmail,
                                        $pdfBase64,
                                        $referralAttachments
                                    )
                                );
                                Log::info('External referral email sent', [
                                    'referral_id' => $referral->id,
                                    'email' => $externalReferee->email,
                                    'attachments_count' => count($referralAttachments)
                                ]);
                            } catch (Exception $e) {
                                Log::error('Failed to send external referral email', [
                                    'referral_id' => $referral->id,
                                    'email' => $externalReferee->email,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
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
                'referral_hierarchies.business_unit',
                'referral_hierarchies.external_referee.organization',
                'referral_hierarchies.external_organization',
                'referral_hierarchies.referral_create_form',
                'referral_hierarchies.referral_reply_form',
                'referral_hierarchies.referral_details.form.form_details',
                'referral_hierarchies.referral_attachments'
            ]);

            //check if referral accessible by this business unit
            $exists = $referral->referral_hierarchies->contains('business_unit_id', $businessUnitId);

            if (!$exists) {
                return response()->json(['message' => 'Referral not accessible.'], 403);
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

                    if ($rh->external_referee_id) {
                        $is_external = true;
                        $external_referee = $rh->external_referee;
                        $external_referral[] = [
                            'external_referee_id' => $external_referee->id,
                            'name' => $external_referee->name,
                            'email' => $external_referee->email,
                            'phone' => $external_referee->phone,
                            'position' => $external_referee->position,
                        'external_organization_id' => $external_referee->organization->id ?? null,
                        'organization' => $external_referee->organization->name ?? null,
                        'address' => $external_referee->organization->address ?? null,
                        'postcode' => $external_referee->organization->postcode ?? null,
                        'state' => $external_referee->organization->state ?? null,
                        'country' => $external_referee->organization->country ?? null,
                    ];
                } elseif ($rh->external_organization_id) {
                    $is_external = true;
                    $external_organization = $rh->external_organization;
                    $external_referral[] = [
                        'external_referee_id' => null,
                        'name' => null,
                        'email' => null,
                        'phone' => null,
                        'position' => null,
                        'external_organization_id' => $external_organization->id,
                        'organization' => $external_organization->name,
                        'address' => $external_organization->address,
                        'postcode' => $external_organization->postcode,
                        'state' => $external_organization->state,
                        'country' => $external_organization->country,
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

            if ($is_external) {
                //get pdf base64
                $pdf = $this->exportPdf($data);
                if ($pdf) {
                    $data['pdf_base64'] = $pdf;
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
                $referral = Referral::with(['referral_hierarchies', 'referral_hierarchies.referral_details'])->find($referral_id);

                //update status
                $referral->status = $validated['referral']['status'];

                if ($validated['referral']['status'] == 5 && isset($validated['referral']['status_note']) && $validated['referral']['status_note'] != '') {
                    $referral->status_note = $validated['referral']['status_note'];
                }

                $referral_hierarchy_id = null;

                // Find the current business unit's hierarchy
                foreach ($referral->referral_hierarchies as $rh) {
                    if ($rh->business_unit_id == $business_unit_id) {
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
                    'message' => 'PDF not found for this referral. PDF is only available for external referrals.'
                ], 404);
            }

            // Return the stored PDF base64
            return response()->json(['pdfBase64' => $referral->encoded_base], 200);
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

            // Convert PDF to base64 for JSON response (commented out)
            $pdfContent = $pdf->output();
            $base64Pdf = base64_encode($pdfContent);
            return $base64Pdf;
        } catch (\Exception $e) {
            Log::error('PDF generation failed: ' . $e->getMessage());
            return null;
        }
    }

    public function notification(Request $request)
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

            // Fetch unread notifications for the staff (only if business unit is the last sequence)
            $notifications = ReferralHistory::with(['referral'])
                ->where('business_unit_id', $businessUnitId)
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
        } catch (\Exception $e) {
            return response()->json(['message' => 'Internal server error.'], 500);
        }
    }
}