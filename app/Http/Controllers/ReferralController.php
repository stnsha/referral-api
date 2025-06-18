<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralRequest;
use App\Http\Requests\UpdateReferralRequest;
use App\Models\BusinessUnit;
use App\Models\FormDetails;
use App\Models\Referral;
use App\Models\ReferralDetails;
use App\Models\ReferralHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReferralController extends Controller
{
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

    public function store(StoreReferralRequest $request)
    {
        try {
            $validated = $request->validated();

            $businessUnits = $request->input('business_units');

            $business_unit_id = $businessUnits['assignee']['business_unit_id'];

            $referral = Referral::create([
                'customer_id' => $validated['referral']['customer_id'],
                'priority' => $validated['referral']['priority'],
                'status' => 1, //Open
            ]);

            foreach (array_values($businessUnits) as $key => $value) {
                $is_filled = $business_unit_id != $value['business_unit_id'] ? false : true;

                $data = [
                    'referral_id' => $referral->id,
                    'staff_id' => ($value['staff_id'] ?? 0) != 0 ? $value['staff_id'] : null,
                    'business_unit_id' => $value['business_unit_id'],
                    'location' => $value['location'],
                    'sequence' => $key + 1,
                    'is_filled' => $is_filled,
                ];

                if (isset($value['referral_reason']) || isset($value['referral_condition']) || isset($value['medical_history'])) {
                    $data['referral_reason'] = $value['referral_reason'] ?? '';
                    $data['referral_condition'] = $value['referral_condition'] ?? '';
                    $data['medical_history'] = $value['medical_history'] ?? '';
                }

                $referralHistory = ReferralHistory::create($data);
                $referral_history_id = $referralHistory->id;

                if ($business_unit_id == $value['business_unit_id']) {
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
                }
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
        try {
            if (!$referral) {
                return response()->json(['message' => 'Referral not found.'], 404);
            }

            $referralHistories = $referral->referral_histories
                ->sortBy('sequence')
                ->values()
                ->keyBy(function ($rh) {
                    return $rh->business_unit_id;
                })
                ->map(function ($rh) {
                    $forms = [];

                    foreach ($rh->referral_details as $rd) {
                        $formDetails = [];
                        $form_details = $rd->form->form_details;
                        $value = json_decode($rd->value, true);

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

                        $formDetails = array_values($formDetails);

                        $form = [
                            'form_id' => $rd->form->id,
                            'label_name' => $rd->form->label_name,
                            'is_hidden' => $rd->form->is_hidden != 0,
                            'form_details' => $formDetails,
                        ];

                        $forms[] = $form;
                    }

                    return [
                        'sequence' => $rh->sequence,
                        'staff_id' => $rh->staff_id,
                        'location' => $rh->location,
                        'business_unit_id' => $rh->business_unit_id,
                        'is_filled' => $rh->is_filled,
                        'created_at' => Carbon::parse($rh->created_at)->format('d F Y'),
                        'referral_details' => $forms
                    ];
                });


            $referringIndication = [
                'id' => $referral->id,
                'referral_id' => $this->createRefId($referral->id),
                'customer_id' => $referral->customer_id,
                'business_unit_id' => $referral->business_unit_id,
                'referral_reason' => $referral->reason,
                'referral_condition' => $referral->condition,
                'medical_history' => $referral->medical_history,
                'priority' => $referral->priority,
                'status' => $referral->status,
            ];

            $data = [
                'referralDetails' => $referralHistories,
                'referringIndication' => $referringIndication
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
