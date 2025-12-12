<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExternalRefereeRequest;
use App\Models\ExternalOrganization;
use App\Models\ExternalReferee;
use App\Traits\AccessControl;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="External Referee API",
 *     description="API endpoints for managing external referees and their organizations"
 * )
 */

/**
 * @OA\OpenApi(
 *     @OA\Components(
 *         @OA\Schema(
 *             schema="ExternalOrganizationName",
 *             required={"name"},
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="address", type="string", nullable=true),
 *             @OA\Property(property="postcode", type="string", nullable=true),
 *             @OA\Property(property="state", type="string", nullable=true),
 *             @OA\Property(property="country", type="string", nullable=true),
 *             @OA\Property(property="created_at", type="string", format="date-time"),
 *             @OA\Property(property="updated_at", type="string", format="date-time"),
 *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 *         ),
 *         @OA\Schema(
 *             schema="ExternalReferee",
 *             required={"name"},
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="external_organization_id", type="integer", nullable=true),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="email", type="string", nullable=true),
 *             @OA\Property(property="phone", type="string", nullable=true),
 *             @OA\Property(property="position", type="string", nullable=true),
 *             @OA\Property(property="specialty", type="string", nullable=true),
 *             @OA\Property(property="is_active", type="boolean", default=true),
 *             @OA\Property(property="created_at", type="string", format="date-time"),
 *             @OA\Property(property="updated_at", type="string", format="date-time"),
 *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(
 *                 property="organization",
 *                 ref="#/components/schemas/ExternalOrganization",
 *                 nullable=true
 *             )
 *         )
 *     )
 * )
 */
class ExternalRefereeController extends Controller
{
    use AccessControl;

    /**
     * @OA\Get(
     *     path="/api/external-referees",
     *     summary="Get list of external referees",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of external referees",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/ExternalReferee")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $externalReferees = ExternalReferee::with('organization')->get();
        return response()->json($externalReferees);
    }

    /**
     * @OA\Post(
     *     path="/api/external-referees",
     *     summary="Create a new external referee",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="position", type="string"),
     *             @OA\Property(property="specialty", type="string"),
     *             @OA\Property(property="external_organization_id", type="integer", nullable=true),
     *             @OA\Property(property="organization", type="object", nullable=true,
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="postcode", type="string"),
     *                 @OA\Property(property="state", type="string"),
     *                 @OA\Property(property="country", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="External referee created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ExternalReferee")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(ExternalRefereeRequest $request): JsonResponse
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            // Check if user has write permission (referral 1 or 2 only)
            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can create external referees.',
                ], 403);
            }

            DB::beginTransaction();
            $validated = $request->validated();

            if (!isset($validated['external_organization_id']) && isset($validated['organization'])) {
                $organization = ExternalOrganization::create($validated['organization']);
                $validated['external_organization_id'] = $organization->id;
            }

            unset($validated['organization']);

            $externalReferee = ExternalReferee::create($validated);
            $externalReferee->load('organization');

            DB::commit();
            return response()->json($externalReferee, 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create external referee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/external-referees/{referee}",
     *     summary="Get external referee details",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="referee",
     *         in="path",
     *         required=true,
     *         description="External referee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="External referee details",
     *         @OA\JsonContent(ref="#/components/schemas/ExternalReferee")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="External referee not found"
     *     )
     * )
     */
    public function show(ExternalReferee $externalReferee): JsonResponse
    {
        // $externalReferee->load('organization');
        return response()->json($externalReferee);
    }

    /**
     * @OA\Put(
     *     path="/api/external-referees/{referee}",
     *     summary="Update an external referee",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="referee",
     *         in="path",
     *         required=true,
     *         description="External referee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="position", type="string"),
     *             @OA\Property(property="specialty", type="string"),
     *             @OA\Property(property="external_organization_id", type="integer", nullable=true),
     *             @OA\Property(property="organization", type="object", nullable=true,
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="postcode", type="string"),
     *                 @OA\Property(property="state", type="string"),
     *                 @OA\Property(property="country", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="External referee updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ExternalReferee")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="External referee not found"
     *     )
     * )
     */
    public function update(ExternalRefereeRequest $request, ExternalReferee $externalReferee): JsonResponse
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            // Check if user has write permission (referral 1 or 2 only)
            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can update external referees.',
                ], 403);
            }

            DB::beginTransaction();
            $validated = $request->validated();

            if (!isset($validated['external_organization_id']) && isset($validated['organization'])) {
                $organization = ExternalOrganization::create($validated['organization']);
                $validated['external_organization_id'] = $organization->id;
            }

            unset($validated['organization']);

            $externalReferee->update($validated);
            $externalReferee->load('organization');

            DB::commit();
            return response()->json($externalReferee);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'External referee not found.',
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update external referee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/external-referees/{referee}",
     *     summary="Delete an external referee",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="referee",
     *         in="path",
     *         required=true,
     *         description="External referee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="External referee deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="External referee deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete - referee has existing referrals",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cannot delete external referee. This referee has 5 existing referral(s)."),
     *             @OA\Property(property="referral_count", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="External referee not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function destroy(ExternalReferee $externalReferee): JsonResponse
    {
        try {
            $jwtPayload = request()->get('jwt_payload');

            // Check if user has write permission (referral 1 or 2 only)
            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can delete external referees.',
                ], 403);
            }

            // Check if external referee has any referrals
            $referralCount = $externalReferee->referral_histories()->count();

            if ($referralCount > 0) {
                return response()->json([
                    'message' => "Cannot delete external referee. This referee has {$referralCount} existing referral(s).",
                    'referral_count' => $referralCount,
                ], 400);
            }

            $externalReferee->delete();
            return response()->json([
                'message' => 'External referee deleted successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'External referee not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to delete external referee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function external(StoreReferralRequest $request)
    // {
    //     try {
    //         $jwtPayload = $request->get('jwt_payload');
    //         $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

    //         if (!$businessUnitId) {
    //             return response()->json([
    //                 'message' => 'Business unit ID not found in session.',
    //                 'data' => [],
    //             ], 401);
    //         }

    //         $validated = $request->validated();

    //         if ($validated) {
    //             DB::beginTransaction();
    //             //data from business units
    //             $businessUnits = $validated['business_units'];

    //             //get business unit id
    //             $business_unit_id = $businessUnits['assignee']['business_unit_id'];

    //             // Validate that the assignee business unit matches JWT business unit
    //             if ($business_unit_id != $businessUnitId) {
    //                 return response()->json([
    //                     'message' => 'Unauthorized: Cannot create referral for different business unit.',
    //                 ], 403);
    //             }

    //             // Determine customer_id based on external referral
    //             $customerId = $validated['referral']['customer_id'];

    //             //create referral
    //             $referral = Referral::create([
    //                 'customer_id' => $customerId,
    //                 'priority' => $validated['referral']['priority'],
    //                 'status' => 1, //Open
    //             ]);

    //             // Handle new organization and recipient creation for external referrals
    //             $newOrganizationId = null;
    //             $newRefereeId = null;

    //             if (isset($businessUnits['recipient']['new_organization'])) {
    //                 $newOrgData = $businessUnits['recipient']['new_organization'];
    //                 $newOrganization = ExternalOrganization::create([
    //                     'name' => $newOrgData['name'],
    //                     'address' => $newOrgData['address'] ?? null,
    //                     'postcode' => $newOrgData['postcode'] ?? null,
    //                     'state' => $newOrgData['state'] ?? null,
    //                     'country' => $newOrgData['country'] ?? null,
    //                 ]);
    //                 $newOrganizationId = $newOrganization->id;
    //             }

    //             if (isset($businessUnits['recipient']['new_recipient'])) {
    //                 $newRecipientData = $businessUnits['recipient']['new_recipient'];

    //                 // Priority: newly created org > existing org ID > null
    //                 $orgId = $newOrganizationId ?? $businessUnits['recipient']['organization'] ?? null;

    //                 $newReferee = ExternalReferee::create([
    //                     'name' => $newRecipientData['name'],
    //                     'email' => $newRecipientData['email'] ?? null,
    //                     'phone' => $newRecipientData['phone'] ?? null,
    //                     'position' => $newRecipientData['position'] ?? null,
    //                     'external_organization_id' => $orgId,
    //                 ]);
    //                 $newRefereeId = $newReferee->id;
    //             }

    //             // If new organization created but no new recipient, still need to update recipient to use new org
    //             if ($newOrganizationId && !$newRefereeId && isset($businessUnits['recipient']['organization'])) {
    //                 // This case shouldn't happen based on the examples, but keeping for safety
    //             }

    //             //run through businessunits
    //             foreach (array_values($businessUnits) as $key => $value) {

    //                 //for second level of business unit
    //                 $is_filled = false;
    //                 $is_read = false;

    //                 if (isset($value['business_unit_id']) && $business_unit_id == $value['business_unit_id']) {
    //                     $is_filled = true;
    //                     $is_read = true;
    //                 }

    //                 // Determine if this is the recipient (has external referee/organization data)
    //                 $isRecipient = isset($value['referee']) || isset($value['new_recipient']) || isset($value['new_organization']) || isset($value['organization']);

    //                 // Determine external_organization_id for external referrals
    //                 $externalOrganizationId = null;
    //                 if ($isRecipient) {
    //                     // If only existing organization is selected (no referee)
    //                     if (isset($value['organization']) && !isset($value['referee']) && !isset($value['new_recipient'])) {
    //                         $externalOrganizationId = $value['organization'];
    //                     }
    //                     // If new organization was created (with or without new recipient)
    //                     elseif ($newOrganizationId) {
    //                         $externalOrganizationId = $newOrganizationId;
    //                     }
    //                     // If existing organization is specified for new recipient
    //                     elseif (isset($value['new_recipient']) && isset($value['organization'])) {
    //                         $externalOrganizationId = $value['organization'];
    //                     }
    //                 }

    //                 $sequence = $key + 1;

    //                 //compile data for referral hierarchy (no form fields)
    //                 $data = [
    //                     'referral_id' => $referral->id,
    //                     'staff_id' => ($value['staff_id'] ?? 0) != 0 ? $value['staff_id'] : null,
    //                     'business_unit_id' => isset($value['business_unit_id']) ? $value['business_unit_id'] : null,
    //                     'location' => $value['location'] ?? null,
    //                     'sequence' => $sequence,
    //                     'additional_remarks' => $value['additional_remarks'] ?? null,
    //                     'is_filled' => $is_filled,
    //                     'is_read' => $is_read,
    //                     'external_referee_id' =>  $isRecipient ? ($newRefereeId ?? (isset($value['referee']) ? $value['referee'] : null)) : null,
    //                     'external_organization_id' => $externalOrganizationId
    //                 ];

    //                 $new_status = $isRecipient ? 4 : 1;
    //                 $referral->status = $new_status;

    //                 //create referral hierarchy
    //                 $referralHierarchy = ReferralHierarchy::create($data);
    //                 $referral_hierarchy_id = $referralHierarchy->id;
    //                 $business_unit = $is_filled ? $referralHierarchy->business_unit->name : null;

    //                 // Create form based on sequence (odd = create form, even = reply form)
    //                 if ($sequence % 2 === 1) {
    //                     // Odd sequence - Create Form
    //                     if (
    //                         isset($value['referral_reason']) ||
    //                         isset($value['referral_condition']) ||
    //                         isset($value['medical_history'])
    //                     ) {
    //                         ReferralCreateForm::create([
    //                             'referral_hierarchy_id' => $referral_hierarchy_id,
    //                             'referral_reason' => $value['referral_reason'] ?? null,
    //                             'referral_condition' => $value['referral_condition'] ?? null,
    //                             'medical_history' => $value['medical_history'] ?? null,
    //                         ]);
    //                     }
    //                 }

    //                 //run through first level of business unit
    //                 if ($is_filled) {
    //                     $formFields = $request->input("form_data.$business_unit_id", []);

    //                     // Only process form fields if form_data is not empty
    //                     if (!empty($formFields) && is_array($formFields)) {
    //                         foreach ($formFields as $field => $value) {
    //                             //get data from form details
    //                             $form_detail = FormDetails::where('field_name', $field)
    //                                 ->whereHas('form', function ($query) use ($business_unit_id) {
    //                                     $query->where('business_unit_id', $business_unit_id);
    //                                 })
    //                                 ->first();

    //                             //create referral details
    //                             if ($form_detail) {
    //                                 ReferralDetails::create([
    //                                     'referral_hierarchy_id' => $referral_hierarchy_id,
    //                                     'form_id' => $form_detail->form_id,
    //                                     'value' => is_array($value) ? json_encode($value) : $value,
    //                                 ]);
    //                             }
    //                         }
    //                     }
    //                 }

    //                 //run through attachments if exist
    //                 if (filled($request['attachments']) && $is_filled) {
    //                     $mimeMap = [
    //                         'image/jpeg' => 'jpg',
    //                         'image/png' => 'png',
    //                         'application/pdf' => 'pdf',
    //                         'application/msword' => 'doc',
    //                         'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    //                         'application/vnd.ms-excel' => 'xls',
    //                         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
    //                     ];

    //                     foreach ($validated['attachments'] as $key => $atc) {
    //                         $referralAttachment = ReferralAttachment::create([
    //                             'referral_hierarchy_id' => $referral_hierarchy_id,
    //                             'file_name' => $atc['name'],
    //                             'file_type' => $atc['type'],
    //                             'file_size' => $atc['size'],
    //                             'encoded_base' => $atc['base64']
    //                         ]);

    //                         $extension = $mimeMap[$atc['type']] ?? pathinfo($atc['name'], PATHINFO_EXTENSION);
    //                         $newFileName = $business_unit != null
    //                             ? str_replace(' ', '_', $business_unit)
    //                             : pathinfo($atc['name'], PATHINFO_FILENAME);

    //                         $suffix = $referral->id . $referral_hierarchy_id . $referralAttachment->id;
    //                         $referralAttachment->file_name = $newFileName . '_' . $suffix . '.' . $extension;
    //                         $referralAttachment->save();
    //                     }
    //                 }
    //             }

    //             $referral->save();

    //             // Check if any referral history has external referee (is_external)
    //             $hasExternalReferee = false;
    //             foreach ($validated['business_units'] as $value) {
    //                 if (isset($value['referee']) || $newRefereeId) {
    //                     $hasExternalReferee = true;
    //                     break;
    //                 }
    //             }

    //             $response = ['id' => $referral->id];

    //             // Generate PDF base64 for ALL referrals (both internal and external)
    //             $referralData = $referral->load([
    //                 'referral_hierarchies.business_unit',
    //                 'referral_hierarchies.external_referee.organization',
    //                 'referral_hierarchies.referral_create_form'
    //             ]);

    //             // dd($validated);

    //             // Prepare data for PDF
    //             $firstHierarchy = $referralData->referral_hierarchies->where('sequence', 1)->first();
    //             $lastHierarchy = $referralData->referral_hierarchies->sortByDesc('sequence')->first();
    //             $externalRefereeHierarchy = $referralData->referral_hierarchies->firstWhere('external_referee_id', '!=', null);

    //             // Collect PDF data
    //             $referralId = createRefId($referral->id);
    //             $dateCreated = $referral->created_at->format('d F Y');

    //             // Get referral data from create form
    //             $referralReason = 'N/A';
    //             $referralCondition = '';
    //             $medicalHistory = '';
    //             if ($firstHierarchy && $firstHierarchy->referral_create_form) {
    //                 $referralReason = $firstHierarchy->referral_create_form->referral_reason ?? 'N/A';
    //                 $referralCondition = $firstHierarchy->referral_create_form->referral_condition ?? '';
    //                 $medicalHistory = $firstHierarchy->referral_create_form->medical_history ?? '';
    //             }
    //             $additionalRemarks = $firstHierarchy ? $firstHierarchy->additional_remarks : '';

    //             // Get referral details (form data)
    //             $referralDetailsList = [];
    //             if ($firstHierarchy) {
    //                 $details = ReferralDetails::where('referral_hierarchy_id', $firstHierarchy->id)
    //                     ->with(['form'])
    //                     ->get();

    //                 foreach ($details as $detail) {
    //                     $formName = $detail->form ? $detail->form->label_name : 'Detail';
    //                     $formValue = $detail->value;

    //                     // If value is a form_detail_id (FK), get the field_value
    //                     if (is_numeric($formValue)) {
    //                         $formDetail = FormDetails::find($formValue);
    //                         if ($formDetail && $formDetail->field_value) {
    //                             $formValue = $formDetail->field_value;
    //                         }
    //                     }

    //                     $referralDetailsList[] = [
    //                         'form_name' => $formName,
    //                         'form_value' => $formValue
    //                     ];
    //                 }
    //             }

    //             // Get recipient data (external or internal)
    //             $recipientName = '';
    //             $recipientPosition = '';
    //             $recipientPhone = '';
    //             $recipientEmail = '';
    //             $organizationName = '';
    //             $organizationAddress = '';

    //             if ($hasExternalReferee) {
    //                 // External referral recipient data
    //                 if ($externalRefereeHierarchy && $externalRefereeHierarchy->external_referee) {
    //                     $externalReferee = $externalRefereeHierarchy->external_referee;
    //                     $recipientName = $externalReferee->name ?? '';
    //                     $recipientPosition = $externalReferee->position ?? '';
    //                     $recipientPhone = $externalReferee->phone ?? '';
    //                     $recipientEmail = $externalReferee->email ?? '';

    //                     if ($externalReferee->organization) {
    //                         $organizationName = $externalReferee->organization->name ?? '';
    //                         $addressParts = array_filter([
    //                             $externalReferee->organization->address ?? '',
    //                             $externalReferee->organization->postcode ?? '',
    //                             $externalReferee->organization->state ?? '',
    //                             $externalReferee->organization->country ?? ''
    //                         ]);
    //                         $organizationAddress = implode(', ', $addressParts);
    //                     }
    //                 }

    //                 // If recipient data is empty (e.g., new organization without recipient), use organization data
    //                 if (empty($recipientEmail) && $newOrganizationId && !$newRefereeId) {
    //                     $newOrg = ExternalOrganization::find($newOrganizationId);
    //                     if ($newOrg) {
    //                         $organizationName = $newOrg->name;
    //                         $addressParts = array_filter([
    //                             $newOrg->address,
    //                             $newOrg->postcode,
    //                             $newOrg->state,
    //                             $newOrg->country
    //                         ]);
    //                         $organizationAddress = implode(', ', $addressParts);
    //                     }
    //                 }
    //             } else {
    //                 // Internal referral recipient data - use business unit info
    //                 if ($lastHierarchy && $lastHierarchy->business_unit) {
    //                     $recipientBusinessUnit = $lastHierarchy->business_unit;
    //                     $organizationName = $recipientBusinessUnit->name ?? 'N/A';
    //                     $recipientName = $validated['business_units']['recipient']['nama_staff'] ?? 'N/A';
    //                     $recipientPosition = $validated['business_units']['recipient']['designation'] ?? 'N/A';
    //                     $recipientPhone = $validated['business_units']['recipient']['contact'] ?? 'N/A';
    //                     $recipientEmail = $validated['business_units']['recipient']['email'] ?? '';
    //                 }
    //             }

    //             // Get customer_id
    //             $customerId = $validated['referral']['customer_id'];
    //             $patient = $this->customerDetails($customerId);

    //             if (blank($patient)) {
    //                 $patientName = $patientIcNo = $patientPhone = $patientAddress = $patientEmail = 'N/A';

    //                 Log::info('Patient not found in ODB', [
    //                     'referral_id' => $referral->id,
    //                     'customer_id' => $customerId,
    //                 ]);
    //             } else {
    //                 $data = $patient[0] ?? $patient;
    //                 $patientName = data_get($data, 'customer_name', 'N/A');
    //                 $patientIcNo = data_get($data, 'ic', 'N/A');
    //                 $patientPhone = data_get($data, 'phone', 'N/A');
    //                 $patientAddress = str_replace(["\r", "\n"], [",", " "], data_get($data, 'c_addr', 'N/A'));
    //                 $patientEmail = data_get($data, 'email', 'N/A');
    //             }

    //             // Get referee (staff) data from assignee
    //             $staffId = $validated['business_units']['assignee']['staff_id'];
    //             $assignee = $this->staffDetails($staffId);

    //             if (blank($assignee)) {
    //                 $referrerName = $referrerDesignation = $referrerBusinessUnit = $referrerPhone = $referrerEmail = 'N/A';

    //                 Log::info('Staff not found in ODB', [
    //                     'referral_id' => $referral->id,
    //                     'staff_id' => $staffId,
    //                 ]);
    //             } else {
    //                 $staff = $assignee[0] ?? $assignee;
    //                 $referrerName = data_get($staff, 'nama_staff', 'N/A');
    //                 $referrerDesignation = data_get($staff, 'status_semasa', 'N/A');
    //                 $referrerBusinessUnit = optional(BusinessUnit::find($businessUnitId))->name ?? 'N/A';
    //                 $referrerPhone = data_get($staff, 'hp', 'N/A');
    //                 $referrerEmail = data_get($staff, 'email', 'N/A');
    //             }

    //             // Get staff id from recipient
    //             $staffId = $validated['business_units']['recipient']['staff_id'];
    //             $recipient = $this->staffDetails($staffId);

    //             if (blank($recipient)) {
    //                 $referrerName = $referrerDesignation = $referrerBusinessUnit = $referrerPhone = $referrerEmail = 'N/A';

    //                 Log::info('Staff not found in ODB', [
    //                     'referral_id' => $referral->id,
    //                     'staff_id' => $staffId,
    //                 ]);
    //             } else {
    //                 $staff = $recipient[0] ?? $recipient;
    //                 $recipientName = data_get($staff, 'nama_staff', 'N/A');
    //                 $recipientPosition = data_get($staff, 'status_semasa', 'N/A');
    //                 $referrerBusinessUnit = optional(BusinessUnit::find($businessUnitId))->name ?? 'N/A';
    //                 $recipientPhone = data_get($staff, 'hp', 'N/A');
    //                 $referrerEmail = data_get($staff, 'email', 'N/A');
    //             }

    //             $data = [
    //                 'referralId' => $referralId,
    //                 'dateCreated' => $dateCreated,
    //                 'referralReason' => $referralReason,
    //                 'referralCondition' => $referralCondition,
    //                 'medicalHistory' => $medicalHistory,
    //                 'additionalRemarks' => $additionalRemarks,
    //                 'referralDetails' => $referralDetailsList,
    //                 'recipientName' => $recipientName,
    //                 'recipientPosition' => $recipientPosition,
    //                 'recipientPhone' => $recipientPhone,
    //                 'organizationName' => $organizationName,
    //                 'organizationAddress' => $organizationAddress,
    //                 'patientName' => $patientName,
    //                 'patientIcNo' => $patientIcNo,
    //                 'patientPhone' => $patientPhone,
    //                 'patientAddress' => $patientAddress,
    //                 'patientEmail' => $patientEmail,
    //                 'referrerName' => $referrerName,
    //                 'referrerDesignation' => $referrerDesignation,
    //                 'referrerBusinessUnit' => $referrerBusinessUnit,
    //                 'referrerPhone' => $referrerPhone,
    //                 'referrerEmail' => $referrerEmail,
    //             ];

    //             dd($data);

    //             // Generate PDF and convert to base64 for ALL referrals
    //             $pdfBase64 = $this->exportPdf($data);
    //             if ($pdfBase64) {
    //                 $response['pdf_base64'] = $pdfBase64;

    //                 // Store the PDF base64 in referral record
    //                 $referral->encoded_base = $pdfBase64;
    //                 $referral->save();
    //             }

    //             // Send email to external referee if email exists and is valid (only for external referrals)
    //             if ($hasExternalReferee && $externalRefereeHierarchy && $externalRefereeHierarchy->external_referee && $pdfBase64) {
    //                 $externalReferee = $externalRefereeHierarchy->external_referee;

    //                 if ($externalReferee->email && filter_var($externalReferee->email, FILTER_VALIDATE_EMAIL)) {
    //                     try {
    //                         // Get attachments from the referral hierarchy with is_filled = true
    //                         $referralAttachments = [];
    //                         $filledHierarchy = $referralData->referral_hierarchies->where('is_filled', true)->first();
    //                         if ($filledHierarchy) {
    //                             $attachments = ReferralAttachment::where('referral_hierarchy_id', $filledHierarchy->id)->get();
    //                             $referralAttachments = $attachments->map(function ($atc) {
    //                                 return [
    //                                     'file_name' => $atc->file_name,
    //                                     'file_type' => $atc->file_type,
    //                                     'encoded_base' => $atc->encoded_base
    //                                 ];
    //                             })->toArray();
    //                         }

    //                         // Reuse data already collected for PDF
    //                         Mail::to($recipientEmail)->send(
    //                             new ExternalReferralNotification(
    //                                 $referralId,
    //                                 $dateCreated,
    //                                 $referralReason,
    //                                 $referralCondition,
    //                                 $medicalHistory,
    //                                 $additionalRemarks,
    //                                 $referralDetailsList,
    //                                 $recipientName,
    //                                 $recipientPosition,
    //                                 $organizationName,
    //                                 $organizationAddress,
    //                                 $patientName,
    //                                 $patientIcNo,
    //                                 $patientPhone,
    //                                 $patientAddress,
    //                                 $patientEmail,
    //                                 $referrerName,
    //                                 $referrerDesignation,
    //                                 $referrerBusinessUnit,
    //                                 $referrerPhone,
    //                                 $referrerEmail,
    //                                 $pdfBase64,
    //                                 $referralAttachments
    //                             )
    //                         );
    //                     } catch (Exception $e) {
    //                         Log::error('Failed to send external referral email', [
    //                             'referral_id' => $referral->id,
    //                             'email' => $externalReferee->email,
    //                             'error' => $e->getMessage()
    //                         ]);
    //                         // Don't fail the request if email fails
    //                     }

    //                     Log::info('External referral notification email sent', [
    //                         'referral_id' => $referral->id,
    //                         'recipient_email' => $externalReferee->email
    //                     ]);
    //                 } else {
    //                     Log::info('Skipping external referral email - no valid recipient email', [
    //                         'referral_id' => $referral->id,
    //                         'external_referee_email' => $externalReferee->email ?? null
    //                     ]);
    //                 }
    //             }

    //             //return referral id if successfulD
    //             DB::commit();
    //             return response()->json($response, 201);
    //         }
    //     } catch (ValidationException $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => 'Validation failed.',
    //             'errors' => $e->errors(),
    //         ], 422);
    //     } catch (Throwable $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => 'Failed to create referral.',
    //             'error'   => $e->getMessage(),
    //             'line'    => $e->getLine(),
    //             'file'    => $e->getFile(),
    //         ], 500);
    //     }
    // }
}