<?php

namespace App\Http\Controllers;

use App\Models\ReferralAttachment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReferralAttachmentController extends Controller
{
    /**
     * Get attachment file as base64
     *
     * @OA\Get(
     *     path="/api/attachment/{attachment}",
     *     summary="Get attachment file as base64 encoded data",
     *     tags={"Attachments"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="attachment",
     *         in="path",
     *         required=true,
     *         description="ID of the attachment to retrieve",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="document.pdf"),
     *             @OA\Property(property="type", type="string", example="application/pdf"),
     *             @OA\Property(property="size", type="integer", example=1024000),
     *             @OA\Property(property="base64", type="string", format="byte", example="JVBERi0xLjQKJaqrrK0KMSAwIG9iag...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Attachment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Attachment not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Internal server error")
     *         )
     *     )
     * )
     */
    public function download(Request $request, ReferralAttachment $attachment)
    {
        try {
            $jwtPayload = $request->get('jwt_payload');
            $businessUnitId = $jwtPayload['business_unit_id'] ?? null;

            if (!$businessUnitId) {
                return response()->json([
                    'message' => 'Business unit ID not found in session.',
                ], 401);
            }

            // Check if user's business unit is involved in this referral
            $referralHistory = $attachment->referralHistory;
            $referral = $referralHistory ? $referralHistory->referral : null;

            if (!$referral) {
                return response()->json([
                    'message' => 'Referral not found.',
                ], 404);
            }

            // Check if current business unit is involved in this referral
            $exists = $referral->referral_histories->contains('business_unit_id', $businessUnitId);

            if (!$exists) {
                return response()->json([
                    'message' => 'Unauthorized: Cannot access attachment from different business unit.',
                ], 403);
            }

            // Calculate file size from base64 data
            $base64Data = $attachment->encoded_base;
            $fileSize = (int) (strlen(rtrim($base64Data, '=')) * 3 / 4);

            // Return file data as JSON with base64 content
            return response()->json([
                'name' => $attachment->file_name,
                'type' => $attachment->file_type,
                'size' => $fileSize,
                'base64' => $base64Data
            ], 200);
        } catch (Exception $e) {
            Log::error('Error retrieving attachment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
