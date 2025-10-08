<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExternalRefereeRequest;
use App\Models\ExternalOrganization;
use App\Models\ExternalReferee;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="External Referee API",
 *     description="API endpoints for managing external referees and their organizations"
 * )
 */

/**
 * @OA\OpenApi(
 *     @OA\Components(
 *         @OA\Schema(
 *             schema="ExternalOrganizationName",
 *             required={"name"},
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="address", type="string", nullable=true),
 *             @OA\Property(property="postcode", type="string", nullable=true),
 *             @OA\Property(property="state", type="string", nullable=true),
 *             @OA\Property(property="country", type="string", nullable=true),
 *             @OA\Property(property="created_at", type="string", format="date-time"),
 *             @OA\Property(property="updated_at", type="string", format="date-time"),
 *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 *         ),
 *         @OA\Schema(
 *             schema="ExternalReferee",
 *             required={"name"},
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="external_organization_id", type="integer", nullable=true),
 *             @OA\Property(property="name", type="string"),
 *             @OA\Property(property="email", type="string", nullable=true),
 *             @OA\Property(property="phone", type="string", nullable=true),
 *             @OA\Property(property="position", type="string", nullable=true),
 *             @OA\Property(property="specialty", type="string", nullable=true),
 *             @OA\Property(property="is_active", type="boolean", default=true),
 *             @OA\Property(property="created_at", type="string", format="date-time"),
 *             @OA\Property(property="updated_at", type="string", format="date-time"),
 *             @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true),
 *             @OA\Property(
 *                 property="organization",
 *                 ref="#/components/schemas/ExternalOrganization",
 *                 nullable=true
 *             )
 *         )
 *     )
 * )
 */
class ExternalRefereeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/external-referees",
     *     summary="Get list of external referees",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of external referees",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/ExternalReferee")
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $externalReferees = ExternalReferee::with('organization')->get();
        return response()->json($externalReferees);
    }

    /**
     * @OA\Post(
     *     path="/api/external-referees",
     *     summary="Create a new external referee",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="position", type="string"),
     *             @OA\Property(property="specialty", type="string"),
     *             @OA\Property(property="external_organization_id", type="integer", nullable=true),
     *             @OA\Property(property="organization", type="object", nullable=true,
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="postcode", type="string"),
     *                 @OA\Property(property="state", type="string"),
     *                 @OA\Property(property="country", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="External referee created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ExternalReferee")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(ExternalRefereeRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $validated = $request->validated();

            if (!isset($validated['external_organization_id']) && isset($validated['organization'])) {
                $organization = ExternalOrganization::create($validated['organization']);
                $validated['external_organization_id'] = $organization->id;
            }

            unset($validated['organization']);

            $externalReferee = ExternalReferee::create($validated);
            $externalReferee->load('organization');

            DB::commit();
            return response()->json($externalReferee, 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create external referee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/external-referees/{referee}",
     *     summary="Get external referee details",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="referee",
     *         in="path",
     *         required=true,
     *         description="External referee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="External referee details",
     *         @OA\JsonContent(ref="#/components/schemas/ExternalReferee")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="External referee not found"
     *     )
     * )
     */
    public function show(ExternalReferee $externalReferee): JsonResponse
    {
        // $externalReferee->load('organization');
        return response()->json($externalReferee);
    }

    /**
     * @OA\Put(
     *     path="/api/external-referees/{referee}",
     *     summary="Update an external referee",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="referee",
     *         in="path",
     *         required=true,
     *         description="External referee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="position", type="string"),
     *             @OA\Property(property="specialty", type="string"),
     *             @OA\Property(property="external_organization_id", type="integer", nullable=true),
     *             @OA\Property(property="organization", type="object", nullable=true,
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="address", type="string"),
     *                 @OA\Property(property="postcode", type="string"),
     *                 @OA\Property(property="state", type="string"),
     *                 @OA\Property(property="country", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="External referee updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ExternalReferee")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="External referee not found"
     *     )
     * )
     */
    public function update(ExternalRefereeRequest $request, ExternalReferee $externalReferee): JsonResponse
    {
        try {
            DB::beginTransaction();
            $validated = $request->validated();

            if (!isset($validated['external_organization_id']) && isset($validated['organization'])) {
                $organization = ExternalOrganization::create($validated['organization']);
                $validated['external_organization_id'] = $organization->id;
            }

            unset($validated['organization']);

            $externalReferee->update($validated);
            $externalReferee->load('organization');

            DB::commit();
            return response()->json($externalReferee);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'External referee not found.',
            ], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update external referee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/external-referees/{referee}",
     *     summary="Delete an external referee",
     *     tags={"External Referees"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="referee",
     *         in="path",
     *         required=true,
     *         description="External referee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="External referee deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="External referee deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete - referee has existing referrals",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cannot delete external referee. This referee has 5 existing referral(s)."),
     *             @OA\Property(property="referral_count", type="integer", example=5)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="External referee not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function destroy(ExternalReferee $externalReferee): JsonResponse
    {
        try {
            // Check if external referee has any referrals
            $referralCount = $externalReferee->referral_histories()->count();

            if ($referralCount > 0) {
                return response()->json([
                    'message' => "Cannot delete external referee. This referee has {$referralCount} existing referral(s).",
                    'referral_count' => $referralCount,
                ], 400);
            }

            $externalReferee->delete();
            return response()->json([
                'message' => 'External referee deleted successfully',
            ], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'External referee not found.',
            ], 404);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Failed to delete external referee.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}