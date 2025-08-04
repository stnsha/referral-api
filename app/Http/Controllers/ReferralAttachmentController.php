<?php

namespace App\Http\Controllers;

use App\Models\ReferralAttachment;
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
    public function download(ReferralAttachment $attachment)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Error retrieving attachment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
