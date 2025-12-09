<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormDetailsRequest;
use App\Http\Requests\FormsRequest;
use App\Models\BusinessUnit;
use App\Models\Form;
use App\Models\FormDetails;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormController extends Controller
{
    use \App\Traits\AccessControl;

    public function index(Request $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json(['message' => 'Business unit ID not found in session.'], 401);
            }

            $recipientBuId = $request->input('recipientBuId');

            $forms = Form::with('form_details')->where('business_unit_id', $recipientBuId)->where('is_hidden', false)->get();

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
        } catch (Exception $e) {
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
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            // Check if user has write permission (referral 1 or 2 only)
            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can create forms.',
                ], 403);
            }

            DB::beginTransaction();
            $validated = $request->validated();
            $bu = BusinessUnit::where('id', $validated['business_unit_id'])->first();

            // Validate that the requested business unit matches JWT business unit (skip for superadmin)
            if (!$bu || !$this->canAccessBusinessUnit($jwtPayload, $bu->id)) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot create form for different business unit.',
                ], 403);
            }

            $form = Form::create([
                'business_unit_id' => $bu->id,
                'label_name' => $validated['label_name'],
                'is_hidden' => $validated['is_hidden']
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

            DB::commit();
            return response()->json(['message' => 'Form created successfully!', 'form_id' => $form->id], 201);
        } catch (Exception $e) {
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
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json(['message' => 'Business unit ID not found in session.'], 401);
            }

            $query = Form::with(['form_details']);

            // Apply business unit filter only for non-superadmin
            if (!$this->isSuperadmin($jwtPayload)) {
                $query->where('business_unit_id', $businessUnitId);
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
                    'form_details' => $form_details
                ];
            }

            $data = [
                'business_unit_id' => $businessUnitId,
                'forms' => $arr
            ];

            return response()->json($data, 200);
        } catch (Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/form/show",
     *     summary="Get forms by business unit id from JWT token",
     *     tags={"Forms"},
     *     security={{"bearerAuth":{}}},
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
    public function show(Request $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId && !$this->isSuperadmin($jwtPayload)) {
                return response()->json(['message' => 'Business unit ID not found in session.'], 401);
            }

            $query = Form::with(['form_details']);

            // Apply business unit filter only for non-superadmin
            if (!$this->isSuperadmin($jwtPayload)) {
                $query->where('business_unit_id', $businessUnitId);
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
                    'form_details' => $form_details
                ];
            }

            $data = [
                'business_unit_id' => $businessUnitId,
                'forms' => $arr
            ];

            return response()->json($data, 200);
        } catch (Exception $e) {
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
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            // Check if user has write permission (referral 1 or 2 only)
            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can update forms.',
                ], 403);
            }

            // Check if user can access this form (skip for superadmin)
            if (!$this->canAccessBusinessUnit($jwtPayload, $form->business_unit_id)) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot update form from different business unit.',
                ], 403);
            }

            DB::beginTransaction();
            $validated = $request->validated();

            $updates = [];

            foreach (['business_unit_id', 'label_name', 'is_hidden'] as $key) {
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
        } catch (Exception $e) {
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
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            // Check if user has write permission (referral 1 or 2 only)
            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can hide forms.',
                ], 403);
            }

            // Check if user can access this form
            if ($form->business_unit_id != $businessUnitId) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot hide form from different business unit.',
                ], 403);
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
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to hide form.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/form/{form}/unhide",
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
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            // Check if user has write permission (referral 1 or 2 only)
            if (!$this->canWrite($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Read-only access. Only admin and superadmin can unhide forms.',
                ], 403);
            }

            // Check if user can access this form
            if ($form->business_unit_id != $businessUnitId) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot unhide form from different business unit.',
                ], 403);
            }

            DB::beginTransaction();
            $form->update(['is_hidden' => false]);
            DB::commit();
            return response()->json([
                'message' => 'Form unhidden successfully!'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to unhide form.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}