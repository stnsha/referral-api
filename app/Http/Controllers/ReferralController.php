<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralRequest;
use App\Models\BusinessUnit;
use App\Models\FormDetails;
use App\Models\Referral;
use App\Models\ReferralDetails;
use App\Models\ReferralHistory;
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
        $id = '#REF';

        foreach ($referrals as $ref) {
            switch ($ref->status) {
                case '1':
                    $status = 'Assigned';
                    break;

                case '2':
                    $status = 'In Progress';
                    break;

                case '3':
                    $status = 'Completed';

                case '4':
                    $status = 'Referred';

                case '5':
                    $status = 'Closed';

                default:
                    $status = 'Submitted';
                    break;
            }
            $refs[] = [
                'id' => $ref->id,
                'ref_id' => $id . str_pad($ref->id, 4, '0', STR_PAD_LEFT),
                'reason' => $ref->reason,
                'business_unit' => $ref->latest_referral_history->business_unit->name,
                'status' => $status
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
     *                     @OA\Property(property="staff_id", type="integer", example=3581),
     *                     @OA\Property(property="staff_department_id", type="integer", example=2)
     *                 ),
     *                 @OA\Property(
     *                     property="recipient",
     *                     type="object",
     *                     @OA\Property(property="staff_id", type="integer", example=3580),
     *                     @OA\Property(property="staff_department_id", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="referral",
     *                 type="object",
     *                 @OA\Property(property="customer_id", type="integer", nullable=true, example=2),
     *                 @OA\Property(property="referral_reason", type="string", example="Hearing issue"),
     *                 @OA\Property(property="referral_condition", type="string", example="Not be able to hear clearly since last week (30/4)"),
     *                 @OA\Property(property="medical_history", type="string", example=""),
     *                 @OA\Property(property="priority", type="integer", example=2)
     *             ),
     *             @OA\Property(
     *                 property="form_data",
     *                 type="object",
     *                 additionalProperties={
     *                     "type"="object",
     *                     "additionalProperties"={"type"="string"}
     *                 },
     *                 example={
     *                     "2": {
     *                         "chronic_illness_history": "No",
     *                         "current_medications": "No",
     *                         "recent_surgeries": "No"
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
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */

    public function store(StoreReferralRequest $request)
    {
        try {
            $validated = $request->validated();

            $referral = Referral::create([
                'customer_id' => $validated['referral']['customer_id'],
                'reason' => $validated['referral']['referral_reason'],
                'condition' => $validated['referral']['referral_condition'],
                'medical_history' => $validated['referral']['medical_history'],
                'priority' => $validated['referral']['priority'],
                'status' => 1, //Assigned
            ]);

            $businessUnits = $request->input('business_units');

            foreach (['assignee', 'recipient'] as $role) {
                $sd_id = $businessUnits[$role]['staff_department_id'];

                $bu_id = BusinessUnit::where('staff_department_id', $sd_id)->value('id');
                ReferralHistory::create([
                    'referral_id' => $referral->id,
                    'staff_id' => $businessUnits[$role]['staff_id'],
                    'business_unit_id' => $bu_id,
                ]);
            }

            $departmentId = $businessUnits['assignee']['staff_department_id'];
            $formFields = $request->input("form_data.$departmentId", []);

            foreach ($formFields as $field => $value) {
                $form_detail_id = FormDetails::where('field_name', $field)
                    ->whereHas('form.business_unit', function ($query) use ($departmentId) {
                        $query->where('staff_department_id', $departmentId);
                    })
                    ->value('id');

                ReferralDetails::create([
                    'referral_id' => $referral->id,
                    'form_detail_id' => $form_detail_id,
                    'value' => $value,
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

    public function show(Referral $referral)
    {
        /*
        {
            "business_units": {
                "assignee": {
                    "staff_id": 3581,
                    "staff_department_id": 2
                    },
                "recipient": {
                    "staff_id": 3580,
                    "staff_department_id": 1
                    }
            },
            "referral": {
                "customer_id": 2,
                "referral_reason": "Hearing issue",
                "referral_condition": "Not be able to hear clearly since last week (30/4)",
                "medical_history": "",
                "priority": 2
            },
            "form_data": {
                "2": {
                "chronic_illness_history": "No",
                "current_medications": "No",
                "recent_surgeries": "No"
                }
            }
        }
        */

        $arr = [];

        $businessUnits = $referral->referral_histories->take(2)->values()->map(function ($bu, $index) {
            return [
                'staff_id' => $bu->staff_id,
                'business_unit_id' => $bu->business_unit_id,
                'role' => $index === 0 ? 'assignee' : 'recipient',
            ];
        });

        $referringIndication = [
            'referral_id' => $referral->id,
            'referral_reason' => $referral->reason,
            'referral_condition' => $referral->condition,
            'medical_history' => $referral->medical_history,
            'priority' => $referral->priority,
            'status' => $referral->status,
        ];

        $initialTreatments = [];

        foreach ($referral->referral_details as $rd) {
            $forms = [
                'label_name' => $rd->form_detail->form->label_name,
                'is_hidden' => $rd->form_detail->form->is_hidden,
            ];

            $formDetails[] = [
                'field_name' => $rd->form_detail->field_name,
                'field_type' => $rd->form_detail->field_type,
                'is_required' => $rd->form_detail->is_required,
                'field_value' => $rd->form_detail->field_value,
                // 'referral_answer' => $rd->value,
            ];

            $initialTreatments = [
                'forms' => $forms,
                'form_details' => $formDetails,
                // 'referral_answer' => $r
            ];
        }

        return $initialTreatments;
    }
}
