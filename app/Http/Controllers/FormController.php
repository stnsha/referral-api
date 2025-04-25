<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormDetailsRequest;
use App\Http\Requests\FormsRequest;
use App\Models\Form;
use App\Models\FormDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FormController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/form",
     *     summary="Create a new form and its details",
     *     tags={"Forms"},
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
     *             @OA\Property(property="message", type="string", example="Form created successfully!")
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
            $validated = $request->validated();

            $form = Form::create([
                'business_unit_id' => $validated['business_unit_id'],
                'label_name' => $validated['label_name'],
                'is_hidden' => $validated['is_hidden']
            ]);

            $form_details = FormDetails::create([
                'form_id' => $form->id,
                'field_name' => $validated['field_name'],
                'field_type' => $validated['field_type'],
                'is_required' => $validated['is_required'],
                'field_value' => $validated['field_value']
            ]);

            return response()->json(['message' => 'Form created successfully!'], 201);
        } catch (\Exception $e) {
            Log::error('Form creation failed: ' . $e->getMessage());

            return response()->json(['message' => 'Form creation failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/form/show/{business_unit_id}",
     *     tags={"Forms"},
     *     summary="Get forms and their details by business unit",
     *     description="Retrieve all forms for a specific business unit, including their associated form details.",
     *     @OA\Parameter(
     *         name="business_unit_id",
     *         in="path",
     *         required=true,
     *         description="ID of the business unit",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of forms with their details",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="form_id", type="integer", example=1),
     *                     @OA\Property(property="label_name", type="string", example="Physio Interventions"),
     *                     @OA\Property(property="is_hidden", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="form_details",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="form_detail_id", type="integer", example=10),
     *                             @OA\Property(property="field_name", type="string", example="physio_interventions"),
     *                             @OA\Property(property="field_type", type="string", example="checkbox"),
     *                             @OA\Property(property="is_required", type="boolean", example=true),
     *                             @OA\Property(property="field_value", type="string", example="Educational Class")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve form",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Failed to retrieve form."),
     *             @OA\Property(property="error", type="string", example="Error message from exception")
     *         )
     *     )
     * )
     */
    public function show($business_unit_id)
    {
        try {
            $forms = Form::with(['form_details'])->where('business_unit_id', $business_unit_id)->get();
            $data = [];

            foreach ($forms as $form) {
                $form_details = [];
                foreach ($form->form_details as $fd) {
                    $form_details[] = [
                        'form_detail_id' => $fd->id,
                        'field_name' => $fd->field_name,
                        'field_type' => $fd->field_type,
                        'is_required' => $fd->is_required != 0 ? True : False,
                        'field_value' => $fd->field_value,
                    ];
                }
                $data[] = [
                    'form_id' => $form->id,
                    'label_name' => $form->label_name,
                    'is_hidden' => $form->is_hidden != 0 ? True : False,
                    'form_details' => $form_details
                ];
            }

            return response()->json([
                'data' => $data
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve form.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/form/{id}",
     *     tags={"Forms"},
     *     summary="Update an existing form of a business unit",
     *     description="Updates the form with the given ID and provided data (business unit ID, label name, and visibility).",
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
     *             @OA\Property(property="business_unit_id", type="integer", description="The ID of the business unit"),
     *             @OA\Property(property="label_name", type="string", description="The label name of the form"),
     *             @OA\Property(property="is_hidden", type="boolean", description="Whether the form is hidden or not")
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
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Update failed")
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
     *     ),
     * )
     */
    public function update(FormsRequest $request, Form $form)
    {
        try {
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

            return response()->json(['message' => 'Form updated successfully!'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Update failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/form/{form}",
     *     tags={"Forms"},
     *     summary="Delete a form of a business unit",
     *     description="Delete a specific form by its ID.",
     *     @OA\Parameter(
     *         name="form",
     *         in="path",
     *         required=true,
     *         description="The ID of the form to be deleted.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Form deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Form deleted successfully!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to delete form",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Failed to delete form."),
     *             @OA\Property(property="error", type="string", example="Error message from exception")
     *         )
     *     )
     * )
     */

    public function destroy(Form $form)
    {
        try {
            $form->delete();

            return response()->json([
                'message' => 'Form deleted successfully!'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete form.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
