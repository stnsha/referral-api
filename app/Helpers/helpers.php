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
