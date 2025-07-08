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
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferralController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/referral",
     *     summary="Get list of referrals",
     *     tags={"Referrals"},
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
     *                     @OA\Property(property="status", type="string", example="Open")
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
     *     )
     * )
     */
    public function index()
    {
        $referrals = Referral::with(['referral_histories'])->orderByDesc('created_at')->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }

        $refs = [];

        foreach ($referrals as $ref) {
            foreach ($ref->referral_histories as $rh) {
                if ($rh->sequence == 1) {
                    $refs[] = [
                        'id' => $ref->id,
                        'ref_id' => $this->createRefId($ref->id),
                        'reason' => $rh->referral_reason,
                        'business_unit' => $rh->business_unit->name,
                        'ori_status' => $ref->status,
                        'status' => $this->getStatus($ref->status)
                    ];
                }
            }
        }

        return response()->json(['data' => $refs], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/referral",
     *     summary="Create a new referral",
     *     tags={"Referrals"},
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
            $validated = $request->validated();

            if ($validated) {
                DB::beginTransaction();
                //data from business units
                $businessUnits = $validated['business_units'];

                //get business unit id
                $business_unit_id = $businessUnits['assignee']['business_unit_id'];

                //create referral
                $referral = Referral::create([
                    'customer_id' => $validated['referral']['customer_id'],
                    'priority' => $validated['referral']['priority'],
                    'status' => 1, //Open
                ]);

                //run through businessunits 
                foreach (array_values($businessUnits) as $key => $value) {

                    //for second level of business unit 
                    $is_filled = false;

                    if (isset($value['business_unit_id']) && $business_unit_id == $value['business_unit_id']) {
                        $is_filled = true;
                    }

                    //compile data
                    $data = [
                        'referral_id' => $referral->id,
                        'staff_id' => ($value['staff_id'] ?? 0) != 0 ? $value['staff_id'] : null,
                        'business_unit_id' => isset($value['business_unit_id']) ? $value['business_unit_id'] : null,
                        'location' =>  isset($value['location']) ? $value['location'] : null,
                        'sequence' => $key + 1,
                        'is_filled' => $is_filled,
                        'external_referee_id' =>  isset($value['referee']) ? $value['referee'] : null
                    ];

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
                    $referralHistory = ReferralHistory::create($data);
                    $referral_history_id = $referralHistory->id;
                    $business_unit = $is_filled ? $referralHistory->business_unit->name : null;

                    //run through first level of business unit
                    if ($is_filled) {
                        $formFields = $request->input("form_data.$business_unit_id", []);

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
                                    'referral_history_id' => $referral_history_id,
                                    'form_id' => $form_detail->form_id,
                                    'value' => is_array($value) ? json_encode($value) : $value,
                                ]);
                            }
                        }
                    }

                    //run through attachments if exist
                    if (filled($request['attachments']) && $is_filled) {
                        foreach ($validated['attachments'] as $key => $atc) {
                            $referralAttachment = ReferralAttachment::create([
                                'referral_history_id' => $referral_history_id,
                                'file_name' => $atc['name'],
                                'file_type' => $atc['type'],
                                'file_size' => $atc['size'],
                                'encoded_base' => $atc['base64']
                            ]);

                            // Get file extension from MIME type
                            $extension = str_replace('image/', '', $atc['type']);
                            $newFileName = $business_unit != null ? str_replace(' ', '_', $business_unit) : $atc['name'];
                            $suffix =  $referral->id . $referral_history_id . $referralAttachment->id;
                            $referralAttachment->file_name =  $newFileName . '_' . $suffix . '.' . $extension;
                            $referralAttachment->save();
                        }
                    }
                }

                //return referral id if successfulD
                DB::commit();
                return response()->json(['id' => $referral->id], 201);
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
     *             @OA\Property(property="referralDetails", type="object",
     *                 @OA\Property(property="6", type="object",
     *                     @OA\Property(property="sequence", type="integer", example=1),
     *                     @OA\Property(property="staff_id", type="integer", example=2222),
     *                     @OA\Property(property="location", type="integer", example=101),
     *                     @OA\Property(property="business_unit_id", type="integer", example=6),
     *                     @OA\Property(property="is_filled", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", example="19 June 2025"),
     *                     @OA\Property(property="referral_reason", type="string", example="Vestibular-Related Balance Issue"),
     *                     @OA\Property(property="referral_condition", type="string", example="Patient reports persistent dizziness..."),
     *                     @OA\Property(property="medical_history", type="string", example="Mild scoliosis diagnosed during teenage years."),
     *                     @OA\Property(property="additional_remarks", type="string", nullable=true, example=null),
     *                     @OA\Property(property="referral_details", type="array",
     *                         @OA\Items(type="object")
     *                     ),
     *                     @OA\Property(property="attachments", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="name", type="string", example="BloodReport.pdf"),
     *                             @OA\Property(property="size", type="string", example="1.2 MB"),
     *                             @OA\Property(property="type", type="string", example="application/pdf"),
     *                             @OA\Property(property="encoded", type="string", format="byte", example="JVBERi0xLjQKJaqrrK0KMSAwIG9iago8PC9U... (truncated)")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="referringIndication", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="referral_id", type="string", example="#REF0001"),
     *                 @OA\Property(property="customer_id", type="integer", example=10),
     *                 @OA\Property(property="business_unit_id", type="integer", example=6),
     *                 @OA\Property(property="referral_reason", type="string", example="Vestibular-Related Balance Issue"),
     *                 @OA\Property(property="referral_condition", type="string", example="Patient reports persistent dizziness and unsteadiness..."),
     *                 @OA\Property(property="medical_history", type="string", example="Mild scoliosis diagnosed during teenage years."),
     *                 @OA\Property(property="priority", type="integer", example=2),
     *                 @OA\Property(property="status", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Referral not found.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Referral not found.")
     *         )
     *     )
     * )
     */
    public function show(Referral $referral)
    {
        try {
            //check if exist
            if (!$referral) {
                return response()->json(['message' => 'Referral not found.'], 404);
            }

            //initialize for default value
            $referral_reason = '';
            $business_unit_id = '';
            $referral_condition = '';
            $medical_history  = '';

            //get referral histories
            $referralHistories = $referral->referral_histories
                ->sortBy('sequence')
                ->values()
                ->map(function ($rh) use (
                    &$referral_reason,
                    &$business_unit_id,
                    &$referral_condition,
                    &$medical_history,
                ) {
                    $forms = [];

                    //get details
                    foreach ($rh->referral_details as $rd) {
                        $formDetails = [];
                        $form_details = $rd->form->form_details;
                        $value = json_decode($rd->value, true);

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

                            if ($fd->field_type == 'checkbox' && is_array($value)) {
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
                                        'is_answer' => true
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

                    //if first level business unit, assigned actual values
                    if ($rh->sequence == 1) {
                        $referral_reason = $rh->referral_reason;
                        $business_unit_id = $rh->business_unit_id;
                        $referral_condition = $rh->referral_condition;
                        $medical_history = $rh->medical_history;
                    }

                    //get attachments for this history
                    $attachments = $rh->referral_attachments->map(function ($atc) {
                        return [
                            'attachment_id' => $atc->id,
                            'name' => $atc->file_name,
                            'size' => $atc->file_type,
                            'type' => $atc->file_size,
                            'encoded' => $atc->encoded_base
                        ];
                    });

                    //get external referee
                    $external_referral = [];

                    if ($rh->external_referee_id) {
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
                    //return histories data with attachments
                    return [
                        'sequence' => $rh->sequence,
                        'staff_id' => $rh->staff_id,
                        'location' => $rh->location,
                        'business_unit_id' => $rh->business_unit_id,
                        'is_filled' => $rh->is_filled,
                        'created_at' => Carbon::parse($rh->created_at)->format('d F Y'),
                        'referral_reason' => $rh->referral_reason,
                        'referral_condition' => $rh->referral_condition,
                        'medical_history' => $rh->medical_history,
                        'additional_remarks' => $rh->additional_remarks,
                        'referral_details' => $forms,
                        'attachments' => $attachments,
                        'external_referral' => $external_referral,
                    ];
                });

            //create referring indication data
            $referringIndication = [
                'id' => $referral->id,
                'referral_id' => $this->createRefId($referral->id),
                'customer_id' => $referral->customer_id,
                'business_unit_id' => $business_unit_id,
                'referral_reason' => $referral_reason,
                'referral_condition' => $referral_condition,
                'medical_history' => $medical_history,
                'priority' => $referral->priority,
                'status' => $referral->status,
            ];

            //grouped all data
            $data = [
                'referralDetails' => $referralHistories,
                'referringIndication' => $referringIndication,
            ];

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
            $validated = $request->validated();

            if ($validated) {
                DB::beginTransaction();
                $referral_id = $validated['referral']['referral_id'];
                $business_unit_id = $validated['referral']['business_unit_id_reply'];
                $referral = Referral::with(['referral_histories', 'referral_histories.referral_details'])->find($referral_id);

                //update status
                $referral->status = $validated['referral']['status'];
                $referral_history_id = '';

                foreach ($referral->referral_histories as $rh) {
                    $is_external = is_null($rh->external_referee_id) ? true : false;

                    if ($rh->business_unit_id == $business_unit_id && $is_external) {
                        $is_filled = true;
                        $referral_history_id = $rh->id;

                        if (is_null($rh->staff_id) && !empty($validated['referral']['updated_recipient_to'])) {
                            $rh->staff_id = $validated['referral']['updated_recipient_to'];
                            $rh->additional_remarks = $validated['referral']['additional_remarks'] ?? $rh->additional_remarks;
                        }

                        $rh->is_filled = $is_filled;
                        $rh->save();

                        //insert referral details
                        $formFields = $request->input("form_data.$business_unit_id", []);

                        foreach ($formFields as $field => $value) {
                            $form_detail = FormDetails::where('field_name', $field)
                                ->whereHas('form', function ($query) use ($business_unit_id) {
                                    $query->where('business_unit_id', $business_unit_id);
                                })
                                ->first();

                            ReferralDetails::create([
                                'referral_history_id' => $referral_history_id,
                                'form_id' => $form_detail->form_id,
                                'value' => is_array($value) ? json_encode($value) : $value,
                            ]);
                        }

                        //run through attachments if exist
                        if (filled($request['attachments']) && $is_filled) {
                            foreach ($validated['attachments'] as $atc) {
                                $referralAttachment = ReferralAttachment::create([
                                    'referral_history_id' => $referral_history_id,
                                    'file_name' => $atc['name'],
                                    'file_type' => $atc['type'],
                                    'file_size' => $atc['size'],
                                    'encoded_base' => $atc['base64']
                                ]);

                                $business_unit = $rh->business_unit->name;
                                $extension = str_replace('image/', '', $atc['type']);
                                $newFileName = $business_unit != null ? str_replace(' ', '_', $business_unit) : $atc['name'];
                                $suffix =  $referral->id . $referral_history_id . $referralAttachment->id;
                                $referralAttachment->file_name =  $newFileName . '_' . $suffix . '.' . $extension;
                                $referralAttachment->save();
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
                    $referral_reason = isset($validated['refer_another']['referral_reason']) ? $validated['refer_another']['referral_reason'] : null;
                    $referral_condition = isset($validated['refer_another']['referral_condition']) ? $validated['refer_another']['referral_condition'] : null;
                    $medical_history = isset($validated['refer_another']['medical_history']) ? $validated['refer_another']['medical_history'] : null;

                    $total_rh = count($referral->referral_histories);

                    $referral_history = ReferralHistory::create([
                        'referral_id' => $referral->id,
                        'staff_id' => $refer_to,
                        'business_unit_id' => $refer_business_unit,
                        'location' => $refer_location,
                        'sequence' => $total_rh + 1,
                        'referral_reason' => $referral_reason,
                        'referral_condition' => $referral_condition,
                        'medical_history' => $medical_history,
                        'is_filled' => false
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

    private function getStatus($ref_status)
    {

        /**
         * Status
         * 1 = Open
         * 2 = In Progress
         * 3 = Referred
         * 4 = Closed
         */
        switch ($ref_status) {
            case '1':
                $status = 'Open';
                break;

            case '2':
                $status = 'In Progress';
                break;

            case '3':
                $status = 'Referred';
                break;

            case '4':
                $status = 'Closed';
                break;

            default:
                $status = 'Submitted';
                break;
        }
        return $status;
    }

    private function createRefId($id)
    {
        $param = '#REF';

        $updatedId = $param . str_pad($id, 4, '0', STR_PAD_LEFT);

        return $updatedId;
    }
}
