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
