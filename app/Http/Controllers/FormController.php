<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormsRequest;
use App\Models\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FormController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/form",
     *     tags={"Forms"},
     *     summary="Create a new form of business unit",
     *     description="Creates a new form with the given business unit ID, label name, and visibility.",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Form data to be created",
     *         @OA\JsonContent(
     *             required={"business_unit_id", "label_name"},
     *             @OA\Property(property="business_unit_id", type="integer", description="The ID of the business unit"),
     *             @OA\Property(property="label_name", type="string", description="The label name of the form"),
     *             @OA\Property(property="is_hidden", type="boolean", description="Whether the form is hidden or not")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Form created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Form created successfully!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Form creation failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Form creation failed"),
     *             @OA\Property(property="error", type="string", example="Error message from exception")
     *         )
     *     ),
     * 
     *      security={{"apiKeyAuth": {}}}
     * )
     */
    public function create(FormsRequest $request)
    {
        try {
            $validated = $request->validated();

            Form::create([
                'business_unit_id' => $validated['business_unit_id'],
                'label_name' => $validated['label_name'],
                'is_hidden' => $validated['is_hidden'] ?? false
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
     *     summary="Get list of forms by business unit",
     *     description="Retrieve a list of forms associated with a specific business unit ID.",
     *     @OA\Parameter(
     *         name="business_unit_id",
     *         in="path",
     *         required=true,
     *         description="The business unit ID to filter forms by.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Forms retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="business_unit_id", type="integer"),
     *                     @OA\Property(property="label_name", type="string"),
     *                     @OA\Property(property="is_hidden", type="boolean")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve forms",
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
            $forms = Form::where('business_unit_id', $business_unit_id)->get();
            $data = $forms->map(function ($form) {
                return [
                    'business_unit_id' => $form->business_unit_id,
                    'label_name' => $form->label_name,
                    'is_hidden' => $form->is_hidden != 1 ? false : true,
                ];
            });

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

            $form->update([
                'business_unit_id' => $validated['business_unit_id'],
                'label_name' => $validated['label_name'],
                'is_hidden' => $validated['is_hidden'] ?? false
            ]);

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
