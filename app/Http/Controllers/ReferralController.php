<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralRequest;
use App\Models\BusinessUnit;
use App\Models\FormDetails;
use App\Models\Referral;
use App\Models\ReferralDetails;
use App\Models\ReferralHistory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferralController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/referral",
     *     summary="Get list of all referrals",
     *     tags={"Referrals"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response or no results",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="ref_id", type="string", example="#REF0001"),
     *                 @OA\Property(property="reason", type="string", example="Follow-up needed"),
     *                 @OA\Property(property="business_unit", type="string", example="Clinic"),
     *                 @OA\Property(property="status", type="string", example="In Progress")
     *             ))
     *         )
     *     )
     * )
     */

    public function index()
    {
        $referrals = Referral::with(['latest_referral_history'])->get();

        if ($referrals->isEmpty()) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 200);
        }

        $refs = [];


        foreach ($referrals as $ref) {

            $refs[] = [
                'id' => $ref->id,
                'ref_id' => $this->createRefId($ref->id),
                'reason' => $ref->reason,
                'business_unit' => $ref->latest_referral_history->business_unit->name,
                'status' => $this->getStatus($ref->status)
            ];
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
     *             type="object",
     *             @OA\Property(
     *                 property="business_units",
     *                 type="object",
     *                 @OA\Property(
     *                     property="assignee",
     *                     type="object",
     *                     @OA\Property(property="staff_id", type="integer", example=2),
     *                     @OA\Property(property="staff_department_id", type="string", example="21"),
     *                     @OA\Property(property="location", type="string", example="1")
     *                 ),
     *                 @OA\Property(
     *                     property="recipient",
     *                     type="object",
     *                     @OA\Property(property="staff_id", type="integer", example=3580),
     *                     @OA\Property(property="staff_department_id", type="string", example="1"),
     *                     @OA\Property(property="location", type="string", example="3")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="referral",
     *                 type="object",
     *                 @OA\Property(property="customer_id", type="integer", nullable=true, example=10),
     *                 @OA\Property(property="referral_reason", type="string", example="Bloating"),
     *                 @OA\Property(property="referral_condition", type="string", example="-8 month girl\r\n-bloating\r\n-irritability"),
     *                 @OA\Property(property="medical_history", type="string", example="No prior conditions"),
     *                 @OA\Property(property="priority", type="integer", example=2)
     *             ),
     *             @OA\Property(
     *                 property="form_data",
     *                 type="object",
     *                 additionalProperties={
     *                     "type"="object",
     *                     "additionalProperties"={
     *                         "oneOf"={
     *                             @OA\Schema(type="string"),
     *                             @OA\Schema(type="array", @OA\Items(type="integer"))
     *                         }
     *                     }
     *                 },
     *                 example={
     *                     "21": {
     *                         "baby_dob": "2025-04-30",
     *                         "breastfeeding_status": "6",
     *                         "recent_vaccinations": {"8", "9"}
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Referral created successfully.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Referral created successfully.")
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
     *             @OA\Property(property="error", type="string", example="SQLSTATE[23000]: Integrity constraint violation...")
     *         )
     *     )
     * )
     */

    public function store(StoreReferralRequest $request)
    {
        try {
            $validated = $request->validated();

            $businessUnits = $request->input('business_units');

            $departmentId = $businessUnits['assignee']['staff_department_id'];
            $business_unit_id = BusinessUnit::where('staff_department_id', $departmentId)->value('id');

            $referral = Referral::create([
                'customer_id' => $validated['referral']['customer_id'],
                'business_unit_id' => $business_unit_id,
                'reason' => $validated['referral']['referral_reason'],
                'condition' => $validated['referral']['referral_condition'],
                'medical_history' => $validated['referral']['medical_history'],
                'priority' => $validated['referral']['priority'],
                'status' => 1, //Open
            ]);

            foreach (array_values($businessUnits) as $key => $value) {
                $bu_id = BusinessUnit::where('staff_department_id', $value['staff_department_id'])->value('id');

                $is_filled = $departmentId != $value['staff_department_id'] ? false : true;

                ReferralHistory::create([
                    'referral_id' => $referral->id,
                    'staff_id' => $value['staff_id'],
                    'business_unit_id' => $bu_id,
                    'location' => $value['location'],
                    'sequence' => $key + 1,
                    'is_filled' => $is_filled
                ]);
            }

            $formFields = $request->input("form_data.$departmentId", []);

            foreach ($formFields as $field => $value) {

                $form_detail = FormDetails::where('field_name', $field)
                    ->whereHas('form.business_unit', function ($query) use ($departmentId) {
                        $query->where('staff_department_id', $departmentId);
                    })
                    ->first();

                ReferralDetails::create([
                    'referral_id' => $referral->id,
                    'form_id' => $form_detail->form_id,
                    'value' => is_array($value) ? json_encode($value) : $value,
                ]);
            }

            return response()->json(['message' => 'Referral created successfully.'], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to create referral.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/referral/{referral}",
     *     summary="Get detailed referral information including history and form data",
     *     tags={"Referrals"},
     *     @OA\Parameter(
     *         name="referral",
     *         in="path",
     *         required=true,
     *         description="The ID of the referral",
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Referral details retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="referralDetails", type="array", @OA\Items(
     *                 @OA\Property(property="sequence", type="integer", example=1),
     *                 @OA\Property(property="staff_id", type="integer", example=3581),
     *                 @OA\Property(property="location", type="string", example="Melaka"),
     *                 @OA\Property(property="staff_department_id", type="string", example="21"),
     *                 @OA\Property(property="is_filled", type="boolean", example=true),
     *             )),
     *             @OA\Property(property="referringIndication", type="object",
     *                 @OA\Property(property="id", type="integer", example=10),
     *                 @OA\Property(property="referral_id", type="string", example="REF0010"),
     *                 @OA\Property(property="customer_id", type="integer", example=12121),
     *                 @OA\Property(property="business_unit_id", type="string", example="21"),
     *                 @OA\Property(property="referral_reason", type="string", example="Need specialist review"),
     *                 @OA\Property(property="referral_condition", type="string", example="Diabetic"),
     *                 @OA\Property(property="medical_history", type="string", example="Hypertension"),
     *                 @OA\Property(property="priority", type="string", example="Urgent"),
     *                 @OA\Property(property="status", type="integer", example=2),
     *             ),
     *             @OA\Property(property="initialTreatment", type="array", @OA\Items(
     *                 @OA\Property(property="form_id", type="integer", example=1),
     *                 @OA\Property(property="label_name", type="string", example="Baby Checkup"),
     *                 @OA\Property(property="is_hidden", type="boolean", example=false),
     *                 @OA\Property(property="form_details", type="array", @OA\Items(
     *                     @OA\Property(property="form_detail_id", type="integer", example=3),
     *                     @OA\Property(property="field_name", type="string", example="breastfeeding_status"),
     *                     @OA\Property(property="field_type", type="string", example="checkbox"),
     *                     @OA\Property(property="is_required", type="boolean", example=true),
     *                     @OA\Property(property="field_value", type="array", @OA\Items(
     *                         @OA\Property(property="form_detail_id", type="integer", example=5),
     *                         @OA\Property(property="field_value", type="string", example="Exclusive"),
     *                         @OA\Property(property="is_answer", type="boolean", example=true)
     *                     ))
     *                 )),
     *                 @OA\Property(property="form_answer", type="string", example="Yes")
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Referral not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Referral not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error.")
     *         )
     *     )
     * )
     */

    public function show(Referral $referral)
    {
        try {
            if (!$referral) {
                return response()->json(['message' => 'Referral not found.'], 404);
            }

            $data = [];

            $referralDetails = $referral->referral_histories->take(2)->sortBy('sequence')->values()->map(function ($rh) {
                return [
                    'sequence' => $rh->sequence,
                    'staff_id' => $rh->staff_id,
                    'location' => $rh->location,
                    'staff_department_id' => $rh->business_unit->staff_department_id
                ];
            });

            $referringIndication = [
                'id' => $referral->id,
                'referral_id' => $this->createRefId($referral->id),
                'customer_id' => $referral->customer_id,
                'business_unit_id' => $referral->business_unit->staff_department_id,
                'referral_reason' => $referral->reason,
                'referral_condition' => $referral->condition,
                'medical_history' => $referral->medical_history,
                'priority' => $referral->priority,
                'status' => $referral->status,
            ];

            $referralHistories = [];

            $refHistories = $referral->referral_histories->map(function ($history) {
                return [
                    'staff_id' => $history->staff_id,
                    'location' => $history->location,
                    'business_unit_id' => $history->business_unit->staff_department_id,
                    'sequence' => $history->sequence,
                    'is_filled' => $history->is_filled,
                ];
            })->toArray();

            $referralHistories = $refHistories;

            $initialTreatments = [];
            $replyHistories = [];

            $initBuId = $referral->referral_histories->firstWhere('sequence', 1)?->business_unit_id;

            foreach ($referral->referral_details as $rd) {
                $formDetails = [];
                $count_fd = count($rd->form->form_details);

                if ($count_fd > 1) {
                    $form_details = $rd->form->form_details;
                    $value = json_decode($rd->value, true);

                    foreach ($form_details as $fd) {
                        $key = $fd->field_name;

                        if (!isset($formDetails[$key])) {
                            $formDetails[$key] = [
                                'field_name' => $fd->field_name,
                                'field_type' => $fd->field_type,
                                'is_required' => $fd->is_required != 0,
                                'field_value' => [],
                            ];
                        }

                        if ($fd->field_type == 'checkbox' && is_array($value)) {
                            $is_answer = in_array($fd->id, $value);
                        } else {
                            $is_answer = $value == $fd->id;
                        }

                        $formDetails[$key]['field_value'][] = [
                            'form_detail_id' => $fd->id,
                            'field_value' => $fd->field_value,
                            'is_answer' => $is_answer
                        ];
                    }

                    $formDetails = array_values($formDetails);
                } else {
                    $fd = $rd->form->form_details->first();
                    $formDetails[] = [
                        'form_id' => $fd->form_id,
                        'form_detail_id' => $fd->id,
                        'field_name' => $fd->field_name,
                        'field_type' => $fd->field_type,
                        'is_required' => $fd->is_required != 0,
                    ];
                }

                $form_answer = $rd->value;

                $form = [
                    'form_id' => $rd->form->id,
                    'label_name' => $rd->form->label_name,
                    'is_hidden' => $rd->form->is_hidden != 0,
                    'form_details' => $formDetails,
                ];

                if ($count_fd == 1) {
                    $form['form_answer'] = $form_answer;
                }

                if ($rd->form->business_unit_id == $initBuId) {
                    $initialTreatments[] = $form;
                } else {
                    $replyHistories[] = $form;
                }
            }


            $data = [
                'referralDetails' => $referralDetails,
                'referringIndication' => $referringIndication,
                'initialTreatment' => $initialTreatments,
                'replyHistories' => $replyHistories,
                'referralHistories' => $referralHistories
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
     *     summary="Update an existing referral with form data and optionally forward it",
     *     tags={"Referrals"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="referral_id", type="integer", example=10),
     *             @OA\Property(property="bu_id_reply", type="string", example="21"),
     *             @OA\Property(property="status", type="integer", example=2),
     *             @OA\Property(
     *                 property="form_data",
     *                 type="object",
     *                 additionalProperties={
     *                     "oneOf"={
     *                         @OA\Schema(type="string"),
     *                         @OA\Schema(type="array", @OA\Items(type="integer"))
     *                     }
     *                 },
     *                 example={
     *                     "baby_dob": "2025-04-30",
     *                     "breastfeeding_status": "6",
     *                     "recent_vaccinations": {"8", "9"}
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="new_referral",
     *                 type="object",
     *                 required={"staff_id", "staff_department_id", "location"},
     *                 @OA\Property(property="staff_id", type="integer", example=3581),
     *                 @OA\Property(property="staff_department_id", type="string", example="5"),
     *                 @OA\Property(property="location", type="string", example="2")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
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
     *         description="Failed to create referral.",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create referral."),
     *             @OA\Property(property="error", type="string", example="SQLSTATE[23000]: Integrity constraint violation...")
     *         )
     *     )
     * )
     */

    public function update(UpdateReferralRequest $request)
    {
        try {
            $validated = $request->validated();
            if ($validated) {
                $referralId = $validated['referral_id'];
                $departmentId = $validated['bu_id_reply'];
                $business_unit_id = BusinessUnit::where('staff_department_id', $departmentId)->value('id');

                $referral = Referral::find($referralId);
                $referral->status = $validated['status'];
                $referral->save();

                $formFields = $request->input("form_data", []);

                foreach ($formFields as $field => $value) {

                    $form_detail = FormDetails::where('field_name', $field)
                        ->whereHas('form.business_unit', function ($query) use ($departmentId) {
                            $query->where('staff_department_id', $departmentId);
                        })
                        ->first();

                    ReferralDetails::create([
                        'referral_id' => $referral->id,
                        'form_id' => $form_detail->form_id,
                        'value' => is_array($value) ? json_encode($value) : $value,
                    ]);
                }

                $histories = $referral->referral_histories->where('business_unit_id', $business_unit_id)->first();
                $histories->is_filled = true;
                $histories->save();

                if (isset($validated['new_referral'])) {
                    $latestSequence = $referral->referral_histories()->max('sequence');

                    $staffId = isset($validated['new_referral']['staff_id']) ? $validated['new_referral']['staff_id'] : null;
                    $staffDeptId = isset($validated['new_referral']['staff_department_id']) ? $validated['new_referral']['staff_department_id'] : null;
                    $location = isset($validated['new_referral']['location']) ? $validated['new_referral']['location'] : null;

                    $buId = BusinessUnit::where('staff_department_id', $staffDeptId)->value('id');

                    ReferralHistory::create([
                        'referral_id' => $referral->id,
                        'staff_id' => $staffId,
                        'business_unit_id' => $buId,
                        'location' => $location,
                        'sequence' => 1 + $latestSequence,
                        'is_filled' => false
                    ]);
                }

                return response()->json(['message' => 'Referral updated successfully.'], 201);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to create referral.',
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

            case '4':
                $status = 'Closed';

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
