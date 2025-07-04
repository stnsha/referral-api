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
     * Download an attachment file
     *
     * @OA\Get(
     *     path="/api/attachment/{attachment}/download",
     *     summary="Download an attachment file",
     *     tags={"Attachments"},
     *     @OA\Parameter(
     *         name="attachment",
     *         in="path",
     *         required=true,
     *         description="ID of the attachment to download",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File downloaded successfully",
     *         @OA\MediaType(
     *             mediaType="application/octet-stream",
     *             @OA\Schema(type="string", format="binary")
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
            $publicPath = public_path('attachments');
            $filePath = $publicPath . '/' . $attachment->file_name;

            // Create attachments directory if it doesn't exist
            if (!file_exists($publicPath)) {
                mkdir($publicPath, 0755, true);
            }

            // Check if file exists in public directory
            if (!file_exists($filePath)) {
                // Decode and save base64 content
                $fileContent = base64_decode($attachment->encoded_base);
                file_put_contents($filePath, $fileContent);
            }

            // Get file info
            $mimeType = $attachment->file_type;
            $fileSize = filesize($filePath);

            // Prepare headers
            $headers = [
                'Content-Type' => $mimeType,
                'Content-Length' => $fileSize,
                'Content-Disposition' => 'attachment; filename="' . $attachment->file_name . '"',
                'Cache-Control' => 'private, no-transform, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ];

            // Return file download
            return response()->download($filePath, $attachment->file_name, $headers);
        } catch (\Exception $e) {
            Log::error('Error downloading attachment: ' . $e->getMessage());
            return response()->json([
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
