<?php

namespace App\Http\Controllers;

use App\Http\Requests\BusinessUnitRequest;
use App\Http\Resources\BusinessUnitResource;
use App\Models\BusinessUnit;
use App\Traits\AccessControl;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class BusinessUnitController extends Controller
{
    use AccessControl;

    /**
     * @OA\Get(
     *     path="/api/business-units",
     *     tags={"Business Units"},
     *     summary="Get list of business units",
     *     description="Returns a list of business units with their names and staff department IDs. If no data is available, returns a 404 error.",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="staff_department_id", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No data available",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="error", type="string")
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index()
    {
        try {
            $bus = BusinessUnit::select('id', 'name', 'staff_department_id')->get();

            if ($bus->isEmpty()) {
                return response()->json([
                    'message' => 'No data available'
                ], 404);
            }

            return response()->json($bus, 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Database error occurred while fetching business units.'
            ], 500);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/business-units",
     *     summary="Create a new business unit",
     *     tags={"Business Units"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "staff_department_id"},
     *             @OA\Property(property="name", type="string", example="Alpro Clinic"),
     *             @OA\Property(property="staff_department_id", type="integer", example=2),
     *             @OA\Property(property="is_active", type="boolean", example=true, description="Optional, defaults to true")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Business unit created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Business unit created successfully."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Alpro Clinic"),
     *                 @OA\Property(property="staff_department_id", type="integer", example=2),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - read-only access",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthorized: Read-only access. Only admin and superadmin can create business units.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to create business unit."),
     *             @OA\Property(property="error", type="string", example="Error details")
     *         )
     *     )
     * )
     */
    public function store(BusinessUnitRequest $request): JsonResponse
    {
        try {
            $jwtPayload = $request->get('jwt_payload');

            // Check if user is superadmin (referral 1 only)
            if (!$this->isSuperadmin($jwtPayload)) {
                return response()->json([
                    'message' => 'Unauthorized: Only superadmin can create business units.',
                ], 403);
            }

            DB::beginTransaction();

            $validated = $request->validated();

            // Set is_active to true by default if not provided
            if (!isset($validated['is_active'])) {
                $validated['is_active'] = true;
            }

            $businessUnit = BusinessUnit::create($validated);

            DB::commit();

            return response()->json([
                'message' => 'Business unit created successfully.',
                'data' => $businessUnit
            ], 201);

        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create business unit.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}