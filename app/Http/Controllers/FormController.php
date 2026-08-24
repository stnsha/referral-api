<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormDetailsRequest;
use App\Http\Requests\FormsRequest;
use App\Models\BusinessUnit;
use App\Models\Form;
use App\Models\FormDetails;
use App\Traits\AccessControl;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FormController extends Controller
{
    use AccessControl;

    /**
     * @OA\Post(
     *     path="/api/form/list",
     *     summary="Get forms for a business unit",
     *     tags={"Forms"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"recipientBuId"},
     *             @OA\Property(property="recipientBuId", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of forms",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/FormListItem")
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
     *         response=404,
     *         description="No forms found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No forms found for the specified business unit.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Database error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Database error occurred while fetching forms.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json(['message' => 'Business unit ID not found in session.'], 401);
            }

            $recipientBuId = $request->input('recipientBuId');

            $forms = Form::with('form_details')
                ->whereHas('business_units', function ($q) use ($recipientBuId) {
                    $q->where('business_units.id', $recipientBuId);
                })
                ->where('is_hidden', false)
                ->get();

            if ($forms->isEmpty()) {
                return response()->json(['message' => 'No forms found for the specified business unit.'], 404);
            }
            $data = [];

            foreach ($forms as $form) {
                $data[] = [
                    'form_id' => $form->id,
                    'label_name' => $form->label_name,
                    'is_required' => $form->form_details->isNotEmpty() ? $form->form_details->first()->is_required == 1 : false
                ];
            }

            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred while fetching forms.'
            ], 500);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/form",
     *     summary="Create a new form and its details",
     *     tags={"Forms"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"business_unit_id", "label_name", "is_hidden", "is_required", "field_type"},
     *             @OA\Property(property="business_unit_id", type="integer", example=1),
     *             @OA\Property(property="label_name", type="string", example="Physio Interventions"),
     *             @OA\Property(property="is_hidden", type="boolean", example=false),
     *             @OA\Property(property="is_required", type="boolean", example=true),
     *             @OA\Property(property="field_name", type="string", example="physio_interventions"),
     *             @OA\Property(property="field_type", type="string", example="checkbox"),
     *             @OA\Property(
     *                 property="value_fields",
     *                 type="array",
     *                 @OA\Items(type="string"),
     *                 example={"Chest Physiotherapy", "Pain Management"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Form created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Form created successfully!"),
     *             @OA\Property(property="form_id", type="integer", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Authorization header is required.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Form creation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Form creation failed"),
     *             @OA\Property(property="error", type="string", example="Something went wrong")
     *         )
     *     )
     * )
     */


    public function store(FormsRequest $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            // Admin Panel (form management) is SuperAdmin-only; HQ Admin is excluded
            if (!$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Only SuperAdmin can access the Admin Panel.',
                ], 403);
            }

            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            DB::beginTransaction();
            $validated = $request->validated();
            $requestedBuIds = $validated['business_unit_ids'];

            // Non-superadmin can only assign their own business unit
            if (!$this->isSuperadmin($jwtPayload)) {
                $userBuId = $jwtPayload['business_unit_id'] ?? null;
                if (!in_array($userBuId, $requestedBuIds)) {
                    return response()->json([
                        'message' => 'Unauthorized: Cannot create form for a different business unit.',
                    ], 403);
                }
                $requestedBuIds = [$userBuId];
            }

            $validBuIds = BusinessUnit::whereIn('id', $requestedBuIds)->pluck('id')->toArray();
            if (empty($validBuIds)) {
                return response()->json(['message' => 'No valid business units provided.'], 422);
            }

            $form = Form::create([
                'label_name' => $validated['label_name'],
                'is_hidden' => $validated['is_hidden'],
                'display_on' => $validated['display_on'] ?? 'creation',
            ]);

            if (!empty($validated['value_fields'])) {
                foreach ($validated['value_fields'] as $value) {
                    FormDetails::create([
                        'form_id' => $form->id,
                        'field_name' => $validated['field_name'],
                        'field_type' => $validated['field_type'],
                        'is_required' => $validated['is_required'],
                        'field_value' => $value
                    ]);
                }
            } else {
                FormDetails::create([
                    'form_id' => $form->id,
                    'field_name' => $validated['field_name'],
                    'field_type' => $validated['field_type'],
                    'is_required' => $validated['is_required'],
                    'field_value' => null
                ]);
            }

            $form->business_units()->attach($validBuIds);

            DB::commit();
            return response()->json(['message' => 'Form created successfully!', 'form_id' => $form->id], 201);
        } catch (QueryException $e) {
            DB::rollBack();
            Log::error('Form creation database error: ' . $e->getMessage());
            return response()->json(['message' => 'Form creation failed', 'error' => 'Database error occurred'], 500);
        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('Form creation failed: ' . $e->getMessage());
            return response()->json(['message' => 'Form creation failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/form/all",
     *     summary="Get all forms (including hidden) by business unit id from JWT token",
     *     tags={"Forms"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="business_unit_id", type="integer", example=3),
     *             @OA\Property(
     *                 property="forms",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="form_id", type="integer", example=12),
     *                     @OA\Property(property="label_name", type="string", example="Assessment Form"),
     *                     @OA\Property(property="is_hidden", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="form_details",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="form_detail_id", type="integer", example=101),
     *                             @OA\Property(property="field_name", type="string", example="Blood Pressure"),
     *                             @OA\Property(property="field_type", type="string", example="text"),
     *                             @OA\Property(property="is_required", type="boolean", example=true),
     *                             @OA\Property(
     *                                 property="field_value",
     *                                 oneOf={
     *                                     @OA\Schema(type="string", example="120/80"),
     *                                     @OA\Schema(
     *                                         type="array",
     *                                         @OA\Items(
     *                                             @OA\Property(property="form_detail_id", type="integer", example=201),
     *                                             @OA\Property(property="field_value", type="string", example="Option A")
     *                                         )
     *                                     )
     *                                 }
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
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
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(type="string", example="Error message")
     *     )
     * )
     */
    public function allForms(Request $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            // Admin Panel (form management) is SuperAdmin-only; HQ Admin is excluded
            if (!$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Only SuperAdmin can access the Admin Panel.',
                ], 403);
            }

            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json(['message' => 'Business unit ID not found in session.'], 401);
            }

            $query = Form::with(['form_details', 'conditions.triggerFormDetail', 'business_units']);

            // Apply business unit filter only for non-superadmin
            if (!$this->isSuperadmin($jwtPayload)) {
                $query->whereHas('business_units', function ($q) use ($businessUnitId) {
                    $q->where('business_units.id', $businessUnitId);
                });
            }

            $forms = $query->get();
            $data = [];
            $arr = [];

            foreach ($forms as $form) {
                $form_details = [];
                $form_detail_count = count($form->form_details);

                if ($form_detail_count > 1) {
                    foreach ($form->form_details as $fd) {
                        $key = $fd->field_name;

                        if (!isset($form_details[$key])) {
                            $form_details[$key] = [
                                'field_name' => $fd->field_name,
                                'field_type' => $fd->field_type,
                                'is_required' => $fd->is_required != 0,
                                'field_value' => [],
                            ];
                        }

                        $form_details[$key]['field_value'][] = [
                            'form_detail_id' => $fd->id,
                            'field_value' => $fd->field_value,
                        ];
                    }
                } else {
                    foreach ($form->form_details as $fd) {
                        $form_details[] = [
                            'form_detail_id' => $fd->id,
                            'field_name' => $fd->field_name,
                            'field_type' => $fd->field_type,
                            'is_required' => $fd->is_required != 0,
                            'field_value' => $fd->field_value,
                        ];
                    }
                }

                $arr[] = [
                    'form_id' => $form->id,
                    'label_name' => $form->label_name,
                    'is_hidden' => $form->is_hidden != 0 ? True : False,
                    'display_on' => $form->display_on,
                    'business_unit_ids' => $form->business_units->pluck('id')->toArray(),
                    'conditions' => $form->conditions->map(function ($c) {
                        return [
                            'condition_id' => $c->id,
                            'trigger_form_detail_id' => $c->trigger_form_detail_id,
                            'trigger_form_id' => $c->triggerFormDetail ? $c->triggerFormDetail->form_id : null,
                        ];
                    })->values()->toArray(),
                    'form_details' => $form_details,
                ];
            }

            $data = [
                'business_unit_id' => $businessUnitId,
                'forms' => $arr
            ];

            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred while fetching forms.'
            ], 500);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/form/show/{business_unit_id}",

     *     summary="Get forms by business unit id from JWT token",
     *     tags={"Forms"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="business_unit_id",
     *         in="path",
     *         required=true,
     *         description="Business unit ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="business_unit", type="integer", example=3),
     *             @OA\Property(
     *                 property="forms",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="form_id", type="integer", example=12),
     *                     @OA\Property(property="label_name", type="string", example="Assessment Form"),
     *                     @OA\Property(property="is_hidden", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="form_details",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="form_detail_id", type="integer", example=101),
     *                             @OA\Property(property="field_name", type="string", example="Blood Pressure"),
     *                             @OA\Property(property="field_type", type="string", example="text"),
     *                             @OA\Property(property="is_required", type="boolean", example=true),
     *                             @OA\Property(
     *                                 property="field_value",
     *                                 oneOf={
     *                                     @OA\Schema(type="string", example="120/80"),
     *                                     @OA\Schema(
     *                                         type="array",
     *                                         @OA\Items(
     *                                             @OA\Property(property="form_detail_id", type="integer", example=201),
     *                                             @OA\Property(property="field_value", type="string", example="Option A")
     *                                         )
     *                                     )
     *                                 }
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Business unit ID not found in token")
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
     *       response=500,
     *       description="Internal Server Error",
     *       @OA\JsonContent(type="string", example="SQLSTATE[42S22]: Column not found: 1054 Unknown column...")
     *       )
     *  )
     */
    public function show(Request $request, $business_unit_id = null)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $userBuId = $jwtPayload['business_unit_id'] ?? null;

            if (!$userBuId && !$this->isElevated($jwtPayload)) {
                return response()->json(['message' => 'Business unit ID not found in session.'], 401);
            }

            // Use the route parameter as the recipient BU filter; fall back to the user's own BU
            $recipientBuId = $business_unit_id ?? $userBuId;

            $query = Form::with(['form_details', 'conditions.triggerFormDetail', 'business_units']);

            // Filter by the recipient business unit
            $query->whereHas('business_units', function ($q) use ($recipientBuId) {
                $q->where('business_units.id', $recipientBuId);
            });

            $forms = $query->get();
            $data = [];
            $arr = [];

            foreach ($forms as $form) {
                $form_details = [];
                $form_detail_count = count($form->form_details);

                if ($form_detail_count > 1) {
                    foreach ($form->form_details as $fd) {
                        $key = $fd->field_name;

                        if (!isset($form_details[$key])) {
                            $form_details[$key] = [
                                'field_name' => $fd->field_name,
                                'field_type' => $fd->field_type,
                                'is_required' => $fd->is_required != 0,
                                'field_value' => [],
                            ];
                        }

                        $form_details[$key]['field_value'][] = [
                            'form_detail_id' => $fd->id,
                            'field_value' => $fd->field_value,
                        ];
                    }
                } else {
                    foreach ($form->form_details as $fd) {
                        $form_details[] = [
                            'form_detail_id' => $fd->id,
                            'field_name' => $fd->field_name,
                            'field_type' => $fd->field_type,
                            'is_required' => $fd->is_required != 0,
                            'field_value' => $fd->field_value,
                        ];
                    }
                }

                $arr[] = [
                    'form_id' => $form->id,
                    'label_name' => $form->label_name,
                    'is_hidden' => $form->is_hidden != 0 ? True : False,
                    'display_on' => $form->display_on,
                    'business_unit_ids' => $form->business_units->pluck('id')->toArray(),
                    'conditions' => $form->conditions->map(function ($c) {
                        return [
                            'condition_id' => $c->id,
                            'trigger_form_detail_id' => $c->trigger_form_detail_id,
                            'trigger_form_id' => $c->triggerFormDetail ? $c->triggerFormDetail->form_id : null,
                        ];
                    })->values()->toArray(),
                    'form_details' => $form_details,
                ];
            }

            $data = [
                'business_unit_id' => $recipientBuId,
                'forms' => $arr
            ];

            return response()->json($data, 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred while fetching forms.',
            ], 500);
        } catch (Throwable $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/form/{id}",
     *     tags={"Forms"},
     *     summary="Update an existing form of a business unit",
     *     description="Updates the form with the given ID and provided data (label name and visibility). The form must belong to the authenticated user's business unit.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The ID of the form to be updated",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Form data to be updated",
     *         @OA\JsonContent(
     *             required={"business_unit_id", "label_name"},
     *             @OA\Property(property="business_unit_id", type="integer", description="The ID of the business unit", example=1),
     *             @OA\Property(property="label_name", type="string", description="The label name of the form", example="Updated Form Name"),
     *             @OA\Property(property="is_hidden", type="boolean", description="Whether the form is hidden or not", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Form updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Form updated successfully!")
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
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthorized: Cannot update form from different business unit.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Update failed"),
     *             @OA\Property(property="error", type="string", example="Error message from exception")
     *         )
     *     )
     * )
     */
    public function update(FormsRequest $request, Form $form)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            // Admin Panel (form management) is SuperAdmin-only; HQ Admin is excluded
            if (!$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Only SuperAdmin can access the Admin Panel.',
                ], 403);
            }

            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            $form->load('business_units');

            // Check if user can access this form (skip for superadmin)
            if (!$this->isSuperadmin($jwtPayload)) {
                $userBuId = $jwtPayload['business_unit_id'] ?? null;
                if (!$form->business_units->contains('id', $userBuId)) {
                    return response()->json([
                        'message' => 'Unauthorized: Cannot update form from different business unit.',
                    ], 403);
                }
            }

            DB::beginTransaction();
            $validated = $request->validated();

            $updates = [];

            foreach (['label_name', 'is_hidden', 'display_on'] as $key) {
                $newValue = $validated[$key] ?? ($key === 'is_hidden' ? false : null);
                if ($form->$key !== $newValue) {
                    $updates[$key] = $newValue;
                }
            }

            if ($updates) {
                $form->update($updates);
            }

            DB::commit();
            return response()->json(['message' => 'Form updated successfully!'], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Update failed', 'error' => 'Database error occurred'], 500);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Update failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/form/hide/{form}",
     *     tags={"Forms"},
     *     summary="Hide a form of a business unit",
     *     description="Hide a specific form by its ID by setting is_hidden to true.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="form",
     *         in="path",
     *         required=true,
     *         description="The ID of the form to be hidden.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Form hidden successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Form hidden successfully!")
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
     *         description="Failed to hide form",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Failed to hide form."),
     *             @OA\Property(property="error", type="string", example="Error message from exception")
     *         )
     *     )
     * )
     */

    public function hide(Request $request, Form $form)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            // Admin Panel (form management) is SuperAdmin-only; HQ Admin is excluded
            if (!$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Only SuperAdmin can access the Admin Panel.',
                ], 403);
            }

            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            $form->load('business_units');

            // Check if user can access this form
            if (!$this->isSuperadmin($jwtPayload)) {
                $userBuId = $jwtPayload['business_unit_id'] ?? null;
                if (!$form->business_units->contains('id', $userBuId)) {
                    return response()->json([
                        'message' => 'Unauthorized: Cannot hide form from different business unit.',
                    ], 403);
                }
            }

            DB::beginTransaction();

            // Update form to be hidden
            $form->update(['is_hidden' => true]);

            // Update all related form details to set is_required to false
            $form->form_details()->update(['is_required' => false]);

            DB::commit();
            return response()->json([
                'message' => 'Form hidden successfully!'
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to hide form.',
                'error' => 'Database error occurred'
            ], 500);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to hide form.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/form/unhide/{form}",
     *     tags={"Forms"},
     *     summary="Unhide a form of a business unit",
     *     description="Unhide a specific form by its ID by setting is_hidden to false.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="form",
     *         in="path",
     *         required=true,
     *         description="The ID of the form to be unhidden.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Form unhidden successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Form unhidden successfully!")
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
     *         description="Failed to unhide form",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Failed to unhide form."),
     *             @OA\Property(property="error", type="string", example="Error message from exception")
     *         )
     *     )
     * )
     */

    public function unhide(Request $request, Form $form)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            // Admin Panel (form management) is SuperAdmin-only; HQ Admin is excluded
            if (!$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Only SuperAdmin can access the Admin Panel.',
                ], 403);
            }

            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            $form->load('business_units');

            // Check if user can access this form
            if (!$this->isSuperadmin($jwtPayload)) {
                $userBuId = $jwtPayload['business_unit_id'] ?? null;
                if (!$form->business_units->contains('id', $userBuId)) {
                    return response()->json([
                        'message' => 'Unauthorized: Cannot unhide form from different business unit.',
                    ], 403);
                }
            }

            DB::beginTransaction();
            $form->update(['is_hidden' => false]);
            DB::commit();
            return response()->json([
                'message' => 'Form unhidden successfully!'
            ], 200);
        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to unhide form.',
                'error' => 'Database error occurred'
            ], 500);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to unhide form.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function attachBusinessUnit(Request $request, Form $form): JsonResponse
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            if (!$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Only superadmin can modify form business units.',
                ], 403);
            }

            $businessUnitId = $request->input('business_unit_id');
            if (!$businessUnitId) {
                return response()->json(['message' => 'business_unit_id is required.'], 422);
            }

            $bu = BusinessUnit::find($businessUnitId);
            if (!$bu) {
                return response()->json(['message' => 'Business unit not found.'], 404);
            }

            if ($form->business_units()->where('business_units.id', $businessUnitId)->exists()) {
                return response()->json(['message' => 'Business unit is already attached to this form.'], 422);
            }

            $form->business_units()->attach($businessUnitId);

            return response()->json(['message' => 'Business unit added to form successfully.'], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to attach business unit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function detachBusinessUnit(Request $request, Form $form, BusinessUnit $businessUnit): JsonResponse
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            if (!$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Only superadmin can modify form business units.',
                ], 403);
            }

            if (!$form->business_units()->where('business_units.id', $businessUnit->id)->exists()) {
                return response()->json(['message' => 'Business unit is not attached to this form.'], 422);
            }

            $form->business_units()->detach($businessUnit->id);

            return response()->json(['message' => 'Business unit removed from form successfully.'], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to detach business unit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}