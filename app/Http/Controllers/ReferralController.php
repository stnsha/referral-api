<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralRequest;
use App\Models\FormDetails;
use App\Models\Referral;
use App\Models\ReferralDetails;
use App\Models\ReferralHistory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferralController extends Controller
{
    /**
     * @OA\Post(
     *     path="api/referral",
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
            ]);

            $businessUnits = $request->input('business_units');

            foreach (['assignee', 'recipient'] as $role) {
                ReferralHistory::create([
                    'referral_id' => $referral->id,
                    'staff_id' => $businessUnits[$role]['staff_id'],
                    'business_unit_id' => $businessUnits[$role]['staff_department_id'],
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
}
