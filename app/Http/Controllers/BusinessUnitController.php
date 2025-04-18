<?php

namespace App\Http\Controllers;

use App\Http\Resources\BusinessUnitResource;
use App\Models\BusinessUnit;
use Illuminate\Http\Request;

class BusinessUnitController extends Controller
{
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
     *     security={{"apiKeyAuth": {}}}
     * )
     */
    public function index()
    {
        try {
            $bus = BusinessUnit::select('name', 'staff_department_id')->get();

            if ($bus->isEmpty()) {
                return response()->json([
                    'message' => 'No data available'
                ], 404);
            }

            return response()->json([
                'data' => $bus
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }
}
