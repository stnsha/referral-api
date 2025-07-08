<?php

namespace App\Http\Controllers;

use App\Models\ExternalOrganization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Http\Requests\ExternalOrganizationRequest;


/**
 * @OA\Schema(
 *     schema="ExternalRefereeDetails",
 *     required={"external_organization_id", "name", "email"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="external_organization_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Dr. John Smith"),
 *     @OA\Property(property="email", type="string", example="john.smith@example.com"),
 *     @OA\Property(property="phone", type="string", example="+1234567890", nullable=true),
 *     @OA\Property(property="position", type="string", example="Senior Consultant", nullable=true),
 *     @OA\Property(property="specialty", type="string", example="Cardiology", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ExternalOrganization",
 *     required={"name", "address", "postcode", "state", "country"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Sample Organization"),
 *     @OA\Property(property="address", type="string", example="123 Main Street"),
 *     @OA\Property(property="postcode", type="string", example="12345"),
 *     @OA\Property(property="state", type="string", example="California"),
 *     @OA\Property(property="country", type="string", example="United States"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 */
class ExternalOrganizationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/external-organizations",
     *     summary="Get list of external organizations",
     *     tags={"External Organizations"},
     *     @OA\Response(
     *         response=200,
     *         description="List of external organizations with their referees",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 allOf={
     *                     @OA\Schema(ref="#/components/schemas/ExternalOrganization"),
     *                     @OA\Schema(
     *                         @OA\Property(
     *                             property="referees",
     *                             type="array",
     *                             @OA\Items(ref="#/components/schemas/ExternalReferee")
     *                         )
     *                     )
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        try {
            $externalOrganizations = ExternalOrganization::with(['referees'])->get();
            return response()->json($externalOrganizations, 200);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Failed to retrieve organizations.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/external-organizations",
     *     summary="Create a new external organization",
     *     tags={"External Organizations"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Sample Organization"),
     *             @OA\Property(property="address", type="string", example="123 Main Street", nullable=true),
     *             @OA\Property(property="postcode", type="string", example="12345", nullable=true),
     *             @OA\Property(property="state", type="string", example="California", nullable=true),
     *             @OA\Property(property="country", type="string", example="United States", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ExternalOrganization")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(ExternalOrganizationRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $externalOrganization = ExternalOrganization::create($request->validated());
            DB::commit();

            return response()->json($externalOrganization, 201);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create organization.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/external-organizations/{externalOrganization}",
     *     summary="Get external organization details",
     *     tags={"External Organizations"},
     *     @OA\Parameter(
     *         name="externalOrganization",
     *         in="path",
     *         required=true,
     *         description="External organization ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ExternalOrganization"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="referees",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/ExternalReferee")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization not found.")
     *         )
     *     )
     * )
     */
    public function show(ExternalOrganization $externalOrganization): JsonResponse
    {
        try {
            $externalOrganization->load('referees');
            return response()->json($externalOrganization, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Organization not found.'], 404);
        } catch (Throwable $e) {
            return response()->json(['message' => 'Failed to retrieve organization.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/external-organizations/{externalOrganization}",
     *     summary="Update an external organization",
     *     tags={"External Organizations"},
     *     @OA\Parameter(
     *         name="externalOrganization",
     *         in="path",
     *         required=true,
     *         description="External organization ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Sample Organization"),
     *             @OA\Property(property="address", type="string", example="123 Main Street", nullable=true),
     *             @OA\Property(property="postcode", type="string", example="12345", nullable=true),
     *             @OA\Property(property="state", type="string", example="California", nullable=true),
     *             @OA\Property(property="country", type="string", example="United States", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ExternalOrganization")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization not found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function update(ExternalOrganizationRequest $request, ExternalOrganization $externalOrganization): JsonResponse
    {
        try {
            DB::beginTransaction();
            $externalOrganization->update($request->validated());
            DB::commit();

            return response()->json($externalOrganization, 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Organization not found.'], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update organization.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/external-organizations/{externalOrganization}",
     *     summary="Delete an external organization",
     *     tags={"External Organizations"},
     *     @OA\Parameter(
     *         name="externalOrganization",
     *         in="path",
     *         required=true,
     *         description="External organization ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization deleted successfully.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Organization not found.")
     *         )
     *     )
     * )
     */
    public function destroy(ExternalOrganization $externalOrganization): JsonResponse
    {
        try {
            DB::beginTransaction();
            $externalOrganization->delete();
            DB::commit();

            return response()->json(['message' => 'Organization deleted successfully.'], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Organization not found.'], 404);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete organization.', 'error' => $e->getMessage()], 500);
        }
    }
}
