<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ODBController extends Controller
{
    public static function generateJWT($payload)
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        $secret = config('app.key');

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    public static function verifyJWT($token)
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        [$header, $payload, $signature] = $parts;
        $secret = config('app.key');

        $expectedSignature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
        $expectedBase64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));

        if (!hash_equals($expectedBase64Signature, $signature)) {
            return false;
        }

        $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);

        if (!$decodedPayload || !isset($decodedPayload['exp']) || $decodedPayload['exp'] < time()) {
            return false;
        }

        return $decodedPayload;
    }

    /**
     * @OA\Post(
     *     path="/api/auth/verify",
     *     summary="Verify JWT token validity",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token"},
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Token is valid",
     *         @OA\JsonContent(
     *             @OA\Property(property="valid", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token is valid"),
     *             @OA\Property(property="payload", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token is invalid or expired",
     *         @OA\JsonContent(
     *             @OA\Property(property="valid", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Token is invalid or expired")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The token field is required.")
     *         )
     *     )
     * )
     */
    public function verifyToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string'
        ]);

        $token = $request->input('token');
        $payload = self::verifyJWT($token);

        if ($payload) {
            return response()->json([
                'valid' => true,
                'message' => 'Token is valid',
                'payload' => $payload
            ], 200);
        }

        return response()->json([
            'valid' => false,
            'message' => 'Token is invalid or expired'
        ], 401);
    }

    private function mapToBusinessUnitId($staffDepartmentId, $statusSemasa)
    {
        if ($staffDepartmentId == 1) {
            $isAudiologist = in_array(strtolower($statusSemasa), [
                'junior audiologist',
                'audiologist',
                'senior audiologist',
                'audiologist in charge'
            ]);

            if ($isAudiologist) {
                $businessUnit = BusinessUnit::where('name', 'Alpro Audiology')->first();
            } else {
                $businessUnit = BusinessUnit::where('name', 'Alpro Pharmacy')->first();
            }
        } else {
            $businessUnit = BusinessUnit::where('staff_department_id', $staffDepartmentId)->first();
        }

        return $businessUnit ? $businessUnit->id : null;
    }

    /**
     * @OA\Post(
     *     path="/api/auth",
     *     summary="Authenticate user and generate JWT token",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"staff_id", "staff_department_id", "status_semasa", "outlet"},
     *             @OA\Property(property="staff_id", type="integer", example=2222),
     *             @OA\Property(property="staff_department_id", type="integer", example=20, description="Department ID. Use 16 for superadmin (no business unit required)"),
     *             @OA\Property(property="status_semasa", type="string", example="Tester"),
     *             @OA\Property(
     *                 property="outlet",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={99, 100, 101, 102, 161, 216, 217, 232, 260, 317, 318, 337, 338, 354, 356, 362, 378}
     *             ),
     *             @OA\Property(
     *                 property="referral",
     *                 type="integer",
     *                 example=2,
     *                 description="Referral level: 0=normal user, 1=superadmin, 2=admin of dept",
     *                 enum={0, 1, 2},
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Authentication successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."),
     *             @OA\Property(property="expires_in", type="integer", example=86400)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or invalid department/status combination",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid department or status combination.")
     *         )
     *     )
     * )
     */
    public function auth(Request $request)
    {
        $validated = $request->validate([
            'staff_id' => 'required|integer',
            'staff_department_id' => 'required|integer',
            'status_semasa' => 'required|string',
            'outlet' => 'nullable|array',
            'outlet.*' => 'integer',
            'referral' => 'nullable|integer|in:0,1,2',
        ], [
            'staff_id.required' => 'Staff ID is required.',
            'staff_department_id.required' => 'Staff Department ID is required.',
            'status_semasa.required' => 'Status Semasa is required.',
            'outlet.required' => 'Outlet is required.',
            'outlet.array' => 'Outlet must be an array.',
            'outlet.*.integer' => 'Each outlet must be an integer.',
            'referral.integer' => 'Referral must be an integer.',
            'referral.in' => 'Referral must be 0, 1, or 2.',
        ]);

        if ($validated) {
            // Skip business unit mapping for superadmin department (16)
            // Superadmin (referral = 1) doesn't need business unit assignment
            if ($validated['staff_department_id'] == 16) {
                $businessUnitId = null;
            } else {
                $businessUnitId = $this->mapToBusinessUnitId($validated['staff_department_id'], $validated['status_semasa']);

                // Fallback: match by outlet_id for HQ-type business units where staff_department_id has no mapping
                if (!$businessUnitId && !empty($validated['outlet'])) {
                    $businessUnit = BusinessUnit::whereIn('outlet_id', $validated['outlet'])
                        ->where('is_active', 1)
                        ->first();
                    $businessUnitId = $businessUnit ? $businessUnit->id : null;
                }

                if (!$businessUnitId) {
                    return response()->json(['message' => 'Invalid department or status combination.'], 422);
                }
            }

            $payload = [
                'iss' => config('app.url'),
                'iat' => time(),
                'exp' => time() + (60 * 60 * 24),
                'staff_id' => $validated['staff_id'],
                'business_unit_id' => $businessUnitId,
                'status_semasa' => $validated['status_semasa'],
                'outlet' => $validated['outlet'],
                'referral' => $validated['staff_department_id'] == 16 ? 1 : ($validated['referral'] ?? 2)
            ];

            $token = self::generateJWT($payload);

            return response()->json([
                'token' => $token,
                'expires_in' => 86400
            ], 200);
        }
    }
}
