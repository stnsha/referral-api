<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormDetailsRequest;
use App\Models\Form;
use App\Models\FormDetails;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FormDetailsController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/formDetails/create",
     *     tags={"Form Details"},
     *     summary="Create form details of a form in a business unit",
     *     security={{"bearerAuth": {}}},
     *     description="Create details for a specific form, including fields like field name, field type, and required status.",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Form details to be created",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"form_id", "form_details"},
     *             @OA\Property(property="form_id", type="integer", description="The ID of the form"),
     *             @OA\Property(
     *                 property="form_details",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"field_name", "field_type", "is_required"},
     *                     @OA\Property(property="field_name", type="string", description="Name of the field"),
     *                     @OA\Property(property="field_type", type="string", description="Type of the field (e.g., text, number, etc.)"),
     *                     @OA\Property(property="is_required", type="boolean", description="Indicates if the field is required")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Form details created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Form details created successfully!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to create form details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Failed to create form details."),
     *             @OA\Property(property="error", type="string", example="Error message from exception")
     *         )
     *     )
     * )
     */

    public function create(FormDetailsRequest $request)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            DB::beginTransaction();
            $validated = $request->validated();
            $form_id = $validated['form_id'];

            // Check if form belongs to user's business unit
            $form = Form::find($form_id);
            if (!$form || $form->business_unit_id != $businessUnitId) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot create form details for different business unit.',
                ], 403);
            }
            $formDetails = $validated['form_details'];

            foreach ($formDetails as $detail) {
                FormDetails::create([
                    'form_id' => $form_id,
                    'field_name' => $detail['field_name'],
                    'field_type' => $detail['field_type'],
                    'is_required' => $detail['is_required'],
                ]);
            }
            DB::commit();
            return response()->json([
                'message' => 'Form details created successfully!'
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create form details.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Get(
     *     path="/api/formDetails/{formDetail}",
     *     summary="Get specific form detail",
     *     tags={"Form Details"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="formDetail",
     *         in="path",
     *         required=true,
     *         description="Form detail ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Form detail retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="form_id", type="integer", example=1),
     *                 @OA\Property(property="field_name", type="string", example="patient_notes"),
     *                 @OA\Property(property="field_type", type="string", example="textarea"),
     *                 @OA\Property(property="is_required", type="boolean", example=true),
     *                 @OA\Property(property="field_value", type="string", example="Some default text")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve form",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to retrieve form."),
     *             @OA\Property(property="error", type="string", example="Error message here")
     *         )
     *     )
     * )
     */

    public function show(Request $request, FormDetails $formDetail)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            // Check if form detail belongs to user's business unit
            $form = $formDetail->form;
            if (!$form || $form->business_unit_id != $businessUnitId) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot access form details from different business unit.',
                ], 403);
            }

            $data = [
                'form_id' => $formDetail->form_id,
                'field_name' => $formDetail->field_name,
                'field_type' => $formDetail->field_type,
                'is_required' => $formDetail->is_required,
                'field_value' => $formDetail->field_value
            ];
            return response()->json([
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve form.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/formDetails/{formDetail}",
     *     summary="Delete a specific form detail",
     *     tags={"Form Details"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="form",
     *         in="path",
     *         required=true,
     *         description="Form detail ID to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Form deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Form deleted successfully!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to delete form",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to delete form."),
     *             @OA\Property(property="error", type="string", example="Exception message")
     *         )
     *     )
     * )
     */

    public function destroy(Request $request, FormDetails $formDetail)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            // Check if form detail belongs to user's business unit
            $form = $formDetail->form;
            if (!$form || $form->business_unit_id != $businessUnitId) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot delete form details from different business unit.',
                ], 403);
            }

            DB::beginTransaction();
            $formDetail->delete();
            DB::commit();
            return response()->json([
                'message' => 'Form deleted successfully!'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete form.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/formDetails/{id}",
     *     operationId="updateFormDetails",
     *     tags={"Form Details"},
     *     summary="Update form details",
     *     security={{"bearerAuth": {}}},
     *     description="Updates a specific form detail entry with new values.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the form detail",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\RequestBody(
     *         required=true,
     *         description="Form data to be updated",
     *         @OA\JsonContent(
     *             required={"form_id", "field_name", "field_type", "is_required", "field_value"},
     *             @OA\Property(property="form_id", type="integer", description="The ID of the business unit"),
     *             @OA\Property(property="field_name", type="string", description="The label name of the form"),
     *             @OA\Property(property="field_type", type="boolean", description="Whether the form is hidden or not"),
     *             @OA\Property(property="is_required", type="integer", description="The ID of the business unit"),
     *             @OA\Property(property="field_value", type="string", description="The label name of the form"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Form updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Form updated successfully!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Update failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Update failed"),
     *             @OA\Property(property="error", type="string", example="Exception message")
     *         )
     *     )
     * )
     */

    public function update(FormDetailsRequest $request, FormDetails $formDetail)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            // Check if form detail belongs to user's business unit
            $form = $formDetail->form;
            if (!$form || $form->business_unit_id != $businessUnitId) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot update form details from different business unit.',
                ], 403);
            }

            DB::beginTransaction();
            $validated = $request->validated();

            $formDetail->fill($validated);

            if ($formDetail->isDirty()) {
                $formDetail->save();
            }
            DB::commit();
            return response()->json(['message' => 'Form updated successfully!'], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Update failed', 'error' => $e->getMessage()], 500);
        }
    }
}
