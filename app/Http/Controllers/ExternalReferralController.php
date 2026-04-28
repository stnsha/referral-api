<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralRequest;
use App\Models\ExternalOrganization;
use App\Models\ExternalReferee;
use App\Models\FormDetails;
use App\Models\Referral;
use App\Models\ReferralAttachment;
use App\Models\ReferralCreateForm;
use App\Models\ReferralDetails;
use App\Models\ReferralHierarchy;
use App\Traits\AccessControl;
use App\Traits\Octopus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ExternalReferralController extends Controller
{
    use Octopus;
    use AccessControl;

    /**
     * @OA\Post(
     *     path="/api/external-referral",
     *     summary="Create a new external referral",
     *     tags={"External Referrals"},
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
     *                     @OA\Property(property="referral_reason", type="string", example="Specialized cardiac assessment required"),
     *                     @OA\Property(property="referral_condition", type="string", example="Patient presents with irregular heartbeat and chest discomfort. Initial ECG shows abnormalities requiring specialist review."),
     *                     @OA\Property(property="medical_history", type="string", example="Hypertension for 5 years, controlled with medication."),
     *                     @OA\Property(property="additional_remarks", type="string", example="Patient prefers morning appointments", nullable=true)
     *                 ),
     *                 @OA\Property(property="recipient", type="object",
     *                     description="Four possible conditions: A) Existing org + existing referee, B) New org + new referee, C) New org only, D) Existing org + new referee",
     *                     @OA\Property(property="organization", type="integer", example=1, description="Existing organization ID (Conditions A & D)"),
     *                     @OA\Property(property="referee", type="integer", example=1, description="Existing referee ID (Condition A only)"),
     *                     @OA\Property(property="new_organization", type="object", description="New organization details (Conditions B & C)",
     *                         @OA\Property(property="name", type="string", example="Hospital Tuanku Jaafar"),
     *                         @OA\Property(property="address", type="string", example="Jalan Rasah, Seremban"),
     *                         @OA\Property(property="postcode", type="string", example="70300"),
     *                         @OA\Property(property="state", type="string", example="Negeri Sembilan"),
     *                         @OA\Property(property="country", type="string", example="Malaysia")
     *                     ),
     *                     @OA\Property(property="new_recipient", type="object", description="New referee details (Conditions B & D)",
     *                         @OA\Property(property="name", type="string", example="Dr. Farisah"),
     *                         @OA\Property(property="email", type="string", example="dummy1@dummy.com"),
     *                         @OA\Property(property="phone", type="string", example="0123456789"),
     *                         @OA\Property(property="position", type="string", example="Medical Officer")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="referral", type="object",
     *                 @OA\Property(property="customer_id", type="integer", example=10),
     *                 @OA\Property(property="priority", type="integer", example=2)
     *             ),
     *             @OA\Property(property="form_data", type="object",
     *                 @OA\Property(property="6", type="object",
     *                     @OA\Property(property="targeted_area", type="string", example="Cardiovascular"),
     *                     @OA\Property(property="pain_level", type="string", example="6"),
     *                     @OA\Property(property="previous_treatment", type="string", example="30")
     *                 )
     *             ),
     *             @OA\Property(property="attachments", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string", example="ECG_Report.pdf"),
     *                     @OA\Property(property="type", type="string", example="application/pdf"),
     *                     @OA\Property(property="size", type="integer", example=25600),
     *                     @OA\Property(property="base64", type="string", format="byte", example="JVBERi0xLjQKJaqrrK0KMSAwIG9iago...")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="External referral created successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1234),
     *             @OA\Property(property="pdf_base64", type="string", format="byte", example="JVBERi0xLjQKJaqrrK0KMSAwIG9iago...")
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
     *         description="Failed to create external referral.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create referral."),
     *             @OA\Property(property="error", type="string", example="Connection Failure"),
     *             @OA\Property(property="line", type="integer", example=123),
     *             @OA\Property(property="file", type="string", example="/app/Http/Controllers/ExternalReferralController.php")
     *         )
     *     )
     * )
     */
    public function store(StoreReferralRequest $request) {

        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                    'data' => [],
                ], 401);
            }

            $validated = $request->validated();

            if($validated)
            {
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

                // Determine external referral
                $isExternalReferral = isset($businessUnits['recipient']['new_organization'])
                    || isset($businessUnits['recipient']['new_recipient'])
                    || isset($businessUnits['recipient']['organization']);

                // Determine customer_id based on external referral
                $customerId = $validated['referral']['customer_id'];

                // Create referral
                $referral = Referral::create([
                    'customer_id' => $customerId,
                    'priority' => $validated['referral']['priority'],
                    'status' => 3, //Referred (because external org/ref might respond)
                ]);

                // Handle new organization and recipient creation for external referrals
                $newOrganizationId = null;
                $newRefereeId = null;

                if (isset($businessUnits['recipient']['new_organization'])) {
                    $newOrgData = $businessUnits['recipient']['new_organization'];
                    $newOrganization = ExternalOrganization::firstOrCreate(
                        [
                            'name' => $newOrgData['name'],
                        ],
                        [
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

                    $newReferee = ExternalReferee::firstOrCreate(
                        [
                            'email' => $newRecipientData['email'] ?? null,
                        ],
                        [
                            'name' => $newRecipientData['name'],
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

                    $isRecipient = isset($value['new_recipient']) || isset($value['new_organization']) || isset($value['organization']) || isset($value['referee']);

                    // Determine external_organization_id for external referrals
                    $externalOrganizationId = null;
                    if ($isRecipient) {
                        // Priority 1: If new organization was created, use it
                        if ($newOrganizationId) {
                            $externalOrganizationId = $newOrganizationId;
                        }
                        // Priority 2: If existing organization is specified, use it
                        elseif (isset($value['organization'])) {
                            $externalOrganizationId = $value['organization'];
                        }
                        // else: remains null (for cases where only referee is specified without organization)
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
                                        $query->whereHas('business_units', function ($q) use ($business_unit_id) {
                                            $q->where('business_units.id', $business_unit_id);
                                        });
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

                $response = ['id' => $referral->id];

                $this->exportReferral($referral);

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

    public function exportReferral(Referral $referral, ?int $sequence = null, bool $returnPdf = false)
    {
        // Generate PDF base64 for ALL referrals (both internal and external)
        $referralData = $referral->load([
            'referral_hierarchies.business_unit',
            'referral_hierarchies.external_referee.organization',
            'referral_hierarchies.external_organization',
            'referral_hierarchies.referral_create_form'
        ]);

        // Determine FROM and TO hierarchies based on sequence parameter
        if ($sequence !== null && $sequence > 0) {
            // Determine if odd (create form) or even (reply form)
            if ($sequence % 2 === 1) {
                // Odd sequence (create form): FROM = N, TO = N+1
                $fromSequence = $sequence;
                $toSequence = $sequence + 1;
            } else {
                // Even sequence (reply form): FROM = N-1, TO = N
                $fromSequence = $sequence - 1;
                $toSequence = $sequence;
            }

            $firstHierarchy = $referralData->referral_hierarchies
                ->where('sequence', $fromSequence)->first();
            $lastHierarchy = $referralData->referral_hierarchies
                ->where('sequence', $toSequence)->first();

            // Validate that FROM hierarchy exists
            if (!$firstHierarchy) {
                Log::warning('FROM hierarchy not found for sequence', [
                    'referral_id' => $referral->id,
                    'requested_sequence' => $sequence,
                    'from_sequence' => $fromSequence
                ]);
                return;
            }

            // Handle incomplete pair (TO hierarchy doesn't exist yet)
            if (!$lastHierarchy) {
                // When $sequence is an odd number > 1 and TO doesn't exist, this is a refer_another
                // destination — the passed $sequence IS the TO, and FROM = $sequence - 1
                if ($sequence % 2 === 1 && $sequence > 1) {
                    $altFrom = $referralData->referral_hierarchies->where('sequence', $sequence - 1)->first();
                    $altTo = $referralData->referral_hierarchies->where('sequence', $sequence)->first();
                    if ($altFrom && $altTo) {
                        $firstHierarchy = $altFrom;
                        $lastHierarchy = $altTo;
                        Log::info('Refer-another external PDF: adjusted FROM/TO', [
                            'referral_id' => $referral->id,
                            'from_sequence' => $sequence - 1,
                            'to_sequence' => $sequence,
                        ]);
                    } else {
                        $lastHierarchy = $firstHierarchy;
                    }
                } else {
                    Log::info('TO hierarchy not found, using FROM only (incomplete pair)', [
                        'referral_id' => $referral->id,
                        'requested_sequence' => $sequence,
                        'to_sequence' => $toSequence
                    ]);
                    $lastHierarchy = $firstHierarchy;
                }
            }

            // Store PDF in TO hierarchy
            $storageHierarchy = $lastHierarchy;

            Log::info('Generating external referral PDF for specific sequence', [
                'referral_id' => $referral->id,
                'requested_sequence' => $sequence,
                'from_sequence' => $fromSequence,
                'to_sequence' => $toSequence
            ]);

        } else {
            // Default behavior: FROM = sequence 1, TO = last sequence
            $firstHierarchy = $referralData->referral_hierarchies->where('sequence', 1)->first();
            $lastHierarchy = $referralData->referral_hierarchies->sortByDesc('sequence')->first();
            $storageHierarchy = $lastHierarchy;

            Log::info('Generating external referral PDF with default behavior (seq 1 to last)', [
                'referral_id' => $referral->id
            ]);
        }

        // Validate that hierarchies exist
        if (!$firstHierarchy || !$lastHierarchy) {
            Log::error('Required hierarchies not found', [
                'referral_id' => $referral->id,
                'sequence' => $sequence,
                'has_first' => !is_null($firstHierarchy),
                'has_last' => !is_null($lastHierarchy)
            ]);
            return;
        }

        // Prepare data for PDF

        // Collect PDF data
        $referralId = createRefId($referral->id);
        $dateCreated = $referral->created_at->format('d M Y');
        $priority = setPriorityReferral($referral->priority);

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

        /************************************************** Assignee *****************************************/
        $referralReason = null;
        $referralCondition = null;
        $medicalHistory = null;
        $additionalRemarks = null;

        // Try to get create_form from current sequence first
        if ($firstHierarchy && $firstHierarchy->referral_create_form) {
            $referralReason = normalizeValue($firstHierarchy->referral_create_form->referral_reason ?? null);
            $referralCondition = normalizeValue($firstHierarchy->referral_create_form->referral_condition ?? null);
            $medicalHistory = normalizeValue($firstHierarchy->referral_create_form->medical_history ?? null);
            $additionalRemarks = normalizeValue($firstHierarchy->additional_remarks ?? null);
        } else {
            // Fallback to sequence 1's create_form if current sequence doesn't have one
            $originalHierarchy = $referralData->referral_hierarchies->where('sequence', 1)->first();
            if ($originalHierarchy && $originalHierarchy->referral_create_form) {
                $referralReason = normalizeValue($originalHierarchy->referral_create_form->referral_reason ?? null);
                $referralCondition = normalizeValue($originalHierarchy->referral_create_form->referral_condition ?? null);
                $medicalHistory = normalizeValue($originalHierarchy->referral_create_form->medical_history ?? null);
                $additionalRemarks = normalizeValue($originalHierarchy->additional_remarks ?? null);
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

        $locationId = $firstHierarchy->location ?? null;
        $assigneeBusinessUnit = $firstHierarchy->business_unit->name;
        $assigneeOutletInfo = $assigneeOutletEmail = $assigneeOutletPhone = $assigneeOutletAddr = null;

        if ($locationId) {
            $outlet = $this->outletDetails($locationId);
            if (blank($outlet)) {
                Log::info('Outlet not found in ODB', [
                    'referral_id' => $referral->id,
                    'outlet_id' => $locationId,
                ]);
            } else {
                $outletData = $outlet[0] ?? $outlet;
                $assigneeOutletInfo = $outletData['code'] . ', ' . $assigneeBusinessUnit;
                $assigneeOutletEmail = data_get($outletData, 'email', null);
                $assigneeOutletPhone = implode('/', array_filter([
                    $outletData['office1'] ?? null,
                    $outletData['office2'] ?? null,
                ]));
                $assigneeOutletAddr = data_get($outletData, 'addr', null);
            }
        }
        /************************************************** End of Assignee *****************************************/
        /************************************************** Referee/Organization *****************************************/

        $refereeName = $refereeEmail = $refereePhone = $refereePosition = $organizationName = $organizationAddr = null;

        if (!is_null($lastHierarchy->external_referee_id)) {
            $refereeName = normalizeValue($lastHierarchy->external_referee->name);
            $refereeEmail = $lastHierarchy->external_referee->email;
            $refereePhone = normalizeValue($lastHierarchy->external_referee->phone);
            $refereePosition = normalizeValue($lastHierarchy->external_referee->position);
        }

        if (!is_null($lastHierarchy->external_organization_id)) {
            $addr = $lastHierarchy->external_organization->address . ', ' . $lastHierarchy->external_organization->postcode . ', ' . $lastHierarchy->external_organization->state . ', ' . $lastHierarchy->external_organization->country;

            $organizationName = $lastHierarchy->external_organization->name;
            $organizationAddr = $addr;
        }


        /************************************************** End of Referee/Organization *****************************************/
        $assigneeBusinessUnitModel = $firstHierarchy->business_unit;

        // Prepare data for PDF generation
        $data = [
            'is_external' => true,
            'referralId' => $referralId,
            'dateCreated' => $dateCreated,
            'priority' => $priority,

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

            'refereeName' => $refereeName,
            'refereeEmail' => $refereeEmail,
            'refereePhone' => $refereePhone,
            'refereePosition' => $refereePosition,

            'organizationName' => $organizationName,
            'organizationAddr' => $organizationAddr,

            // Patient data from API
            'patientName' => $patientName,
            'patientIcNo' => $patientIcNo,
            'patientPhone' => $patientPhone,
            'patientAddress' => $patientAddress,
            'patientEmail' => $patientEmail,

            'referralDetails' => [], // Form details if needed

            'letterheadPath' => $assigneeBusinessUnitModel->letterhead ?? null,
            'footerPath' => $assigneeBusinessUnitModel->footer ?? null,
        ];

        // Generate PDF with QR code using helper function
        $pdfBase64 = generateReferralPdfWithQr($referral->id, $data);
        if ($pdfBase64) {
            Log::info('External Referral PDF generated', [
                'referral_id' => $referral->id,
                'hierarchy_id' => $storageHierarchy->id,
                'sequence' => $storageHierarchy->sequence,
                'from_sequence' => $firstHierarchy->sequence,
                'to_sequence' => $lastHierarchy->sequence
            ]);

            // If $returnPdf is true, return the base64 string directly
            if ($returnPdf) {
                return $pdfBase64;
            }

            // Otherwise, return as HTTP response (original behavior)
            $response['pdf_base64'] = $pdfBase64;

            $pdfContent = base64_decode($pdfBase64);

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="referral.pdf"');
        }
    }
}