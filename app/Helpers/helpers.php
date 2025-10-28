<?php
if (! function_exists('createRefId')) {
    function createRefId($id)
    {
        $param = '#REF';
        $updatedId = $param . str_pad($id, 4, '0', STR_PAD_LEFT);
        return $updatedId;
    }
}

if (! function_exists('getStatus')) {
    /**
     * Get status name from status number
     * Copied from ReferralController
     */
    function getStatus($ref_status)
    {
        /**
         * Status
         * 1 = Open
         * 2 = In Progress
         * 3 = Referred
         * 4 = Closed
         * 5 = Not Present
         */
        switch ($ref_status) {
            case '1':
            case 1:
                $status = 'Open';
                break;

            case '2':
            case 2:
                $status = 'In Progress';
                break;

            case '3':
            case 3:
                $status = 'Referred';
                break;

            case '4':
            case 4:
                $status = 'Closed';
                break;

            case '5':
            case 5:
                $status = 'Not Present';
                break;

            default:
                $status = 'Submitted';
                break;
        }
        return $status;
    }
}

if (!function_exists('generateReferralPdfWithQr')) {
    /**
     * Generate PDF with QR code for referral
     *
     * @param int $referralId The referral ID
     * @param array $data The data to pass to the PDF view
     * @return string|null Base64 encoded PDF or null on failure
     */
    function generateReferralPdfWithQr($referralId, $data)
    {
        try {
            // Generate QR code using external API (no extensions needed)
            $qrCodeUrl = 'http://octopusdb.info:8080/odb/referral/view.php?id=' . $referralId;
            $qrCodeImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($qrCodeUrl);

            // Fetch the image and convert to base64
            try {
                $imageContent = file_get_contents($qrCodeImageUrl);
                $qrCodeBase64 = 'data:image/png;base64,' . base64_encode($imageContent);
                $data['qrCodeBase64'] = $qrCodeBase64;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('QR Code generation failed: ' . $e->getMessage());
                $data['qrCodeBase64'] = ''; // Empty if fails
            }

            // Generate PDF
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.report', $data);
            $pdf->setPaper('A4', 'portrait');

            // Convert PDF to base64
            $pdfContent = $pdf->output();
            $base64Pdf = base64_encode($pdfContent);

            return $base64Pdf;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('PDF generation failed: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('formatMalaysianPhone')) {
    /**
     * Format Malaysian phone number to international format (60XXXXXXXXXX)
     *
     * Rules:
     * - Strips all non-digit characters (spaces, dashes, etc.)
     * - Must start with "01" (Malaysian mobile format)
     * - Must have 3-digit prefix (01X format: 011, 012, 013, etc.)
     * - Total digits must be 10 or 11 (without 60 prefix)
     * - If valid, prepends "60" and returns formatted number
     * - If invalid, returns null
     *
     * Examples:
     * - "0183776517" => "60183776517"
     * - "019-985 3923" => "60199853923"
     * - "019 - 985 3923" => "60199853923"
     * - "06-7813923" => null (doesn't start with 01)
     *
     * @param string|null $phone The phone number to format
     * @return string|null Formatted phone number or null if invalid
     */
    function formatMalaysianPhone($phone)
    {
        // Return null if phone is empty or null
        if (empty($phone)) {
            return null;
        }

        // Strip all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Check if it's empty after cleaning
        if (empty($cleaned)) {
            return null;
        }

        // If already starts with "60", validate and return as is
        if (substr($cleaned, 0, 2) === '60') {
            $length = strlen($cleaned);
            // Should be 12 or 13 digits total (60 + 10 or 11 digits)
            if ($length >= 12 && $length <= 13) {
                // In international format, the "0" is dropped, so "011" becomes "11"
                // Verify the part after "60" starts with "1" (which represents "01X" in local format)
                if (substr($cleaned, 2, 1) === '1') {
                    return $cleaned;
                }
            }
            return null;
        }

        // Check length (must be 10 or 11 digits)
        $length = strlen($cleaned);
        if ($length < 10 || $length > 11) {
            return null;
        }

        // Check if starts with "01" (first two digits)
        if (substr($cleaned, 0, 2) !== '01') {
            return null;
        }

        // Check if has 3-digit prefix (01X format)
        // The third character should be a digit (making it 01X)
        if ($length < 3 || !ctype_digit(substr($cleaned, 2, 1))) {
            return null;
        }

        // Valid Malaysian mobile number, prepend "60"
        return '60' . $cleaned;
    }
}
