<?php

namespace App\Http\Controllers;

use App\Models\ExternalReferee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Http\Requests\ExternalRefereeRequest;

class ExternalRefereeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/external-referees",
     *     summary="Get list of external referees",
     *     tags={"External Referees"},
     *     @OA\Response(
     *         response=200,
     *         description="List of external referees",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Dr. John Doe"),
     *                     @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                     @OA\Property(property="organization", type="string", example="General Hospital"),
     *                     @OA\Property(property="is_active", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $referees = ExternalReferee::orderBy('name')->get();
        if (!$referees) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }
        return response()->json(['data' => $referees]);
    }

    /**
     * @OA\Post(
     *     path="/api/external-referees",
     *     summary="Create a new external referee",
     *     tags={"External Referees"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "organization"},
     *             @OA\Property(property="name", type="string", example="Dr. John Doe"),
     *             @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890", nullable=true),
     *             @OA\Property(property="organization", type="string", example="General Hospital"),
     *             @OA\Property(property="position", type="string", example="Senior Physician", nullable=true),
     *             @OA\Property(property="specialty", type="string", example="Cardiology", nullable=true),
     *             @OA\Property(property="address", type="string", example="123 Medical Center Ave", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="External referee created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="External referee created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(ExternalRefereeRequest $request)
    {
        try {
            DB::beginTransaction();
            $referee = ExternalReferee::create($request->validated());
            DB::commit();

            return response()->json([
                'message' => 'External referee created successfully',
                'data' => $referee
            ], 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create external referee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/external-referees/{externalReferee}",
     *     summary="Get a specific external referee",
     *     tags={"External Referees"},
     *     @OA\Parameter(
     *         name="externalReferee",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Dr. John Doe"),
     *                 @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *                 @OA\Property(property="organization", type="string", example="General Hospital"),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     )
     * )
     */
    public function show(ExternalReferee $externalReferee)
    {
        if (!$externalReferee) {
            return response()->json([
                'message' => 'No results.',
                'data' => [],
            ], 204);
        }
        return response()->json(['data' => $externalReferee]);
    }

    /**
     * @OA\Put(
     *     path="/api/external-referees/{externalReferee}",
     *     summary="Update an external referee",
     *     tags={"External Referees"},
     *     @OA\Parameter(
     *         name="externalReferee",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Dr. John Doe"),
     *             @OA\Property(property="email", type="string", example="john.doe@example.com"),
     *             @OA\Property(property="phone", type="string", example="+1234567890", nullable=true),
     *             @OA\Property(property="organization", type="string", example="General Hospital"),
     *             @OA\Property(property="position", type="string", example="Senior Physician", nullable=true),
     *             @OA\Property(property="specialty", type="string", example="Cardiology", nullable=true),
     *             @OA\Property(property="address", type="string", example="123 Medical Center Ave", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="External referee updated successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation failed"
     *     )
     * )
     */
    public function update(ExternalRefereeRequest $request, ExternalReferee $externalReferee)
    {
        try {
            DB::beginTransaction();
            $externalReferee->update($request->validated());
            DB::commit();

            return response()->json([
                'message' => 'External referee updated successfully',
                'data' => $externalReferee
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update external referee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/external-referees/{externalReferee}",
     *     summary="Delete an external referee",
     *     tags={"External Referees"},
     *     @OA\Parameter(
     *         name="externalReferee",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="External referee deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not found"
     *     )
     * )
     */
    public function destroy(ExternalReferee $externalReferee)
    {
        try {
            DB::beginTransaction();
            $externalReferee->delete();
            DB::commit();

            return response()->json([
                'message' => 'External referee deleted successfully'
            ]);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete external referee',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
