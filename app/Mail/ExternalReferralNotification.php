<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExternalReferralNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $referralId;
    public $dateCreated;
    public $referralReason;
    public $referralCondition;
    public $medicalHistory;
    public $additionalRemarks;
    public $referralDetails;
    public $recipientName;
    public $recipientPosition;
    public $organizationName;
    public $organizationAddress;
    public $patientName;
    public $patientIcNo;
    public $patientPhone;
    public $patientAddress;
    public $patientEmail;
    public $referrerName;
    public $referrerDesignation;
    public $referrerBusinessUnit;
    public $referrerPhone;
    public $referrerEmail;
    public $pdfBase64;
    public $referralAttachments;

    /**
     * Create a new message instance.
     */
    public function __construct(
        $referralId,
        $dateCreated,
        $referralReason,
        $referralCondition,
        $medicalHistory,
        $additionalRemarks,
        $referralDetails,
        $recipientName,
        $recipientPosition,
        $organizationName,
        $organizationAddress,
        $patientName,
        $patientIcNo,
        $patientPhone,
        $patientAddress,
        $patientEmail,
        $referrerName,
        $referrerDesignation,
        $referrerBusinessUnit,
        $referrerPhone,
        $referrerEmail,
        $pdfBase64 = null,
        $referralAttachments = []
    ) {
        $this->referralId = $referralId;
        $this->dateCreated = $dateCreated;
        $this->referralReason = $referralReason;
        $this->referralCondition = $referralCondition;
        $this->medicalHistory = $medicalHistory;
        $this->additionalRemarks = $additionalRemarks;
        $this->referralDetails = $referralDetails;
        $this->recipientName = $recipientName;
        $this->recipientPosition = $recipientPosition;
        $this->organizationName = $organizationName;
        $this->organizationAddress = $organizationAddress;
        $this->patientName = $patientName;
        $this->patientIcNo = $patientIcNo;
        $this->patientPhone = $patientPhone;
        $this->patientAddress = $patientAddress;
        $this->patientEmail = $patientEmail;
        $this->referrerName = $referrerName;
        $this->referrerDesignation = $referrerDesignation;
        $this->referrerBusinessUnit = $referrerBusinessUnit;
        $this->referrerPhone = $referrerPhone;
        $this->referrerEmail = $referrerEmail;
        $this->pdfBase64 = $pdfBase64;
        $this->referralAttachments = $referralAttachments;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[THIS IS PRODUCTION TESTING. PLEASE IGNORE IF RECEIVED] Referral from ' . $this->referrerBusinessUnit . ' - ' . $this->referralId,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.external-referral',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        if ($this->pdfBase64) {
            $attachments[] = Attachment::fromData(fn () => base64_decode($this->pdfBase64), 'referral_' . $this->referralId . '.pdf')
                ->withMime('application/pdf');
        }

        // Add referral attachments
        if (!empty($this->referralAttachments)) {
            foreach ($this->referralAttachments as $attachment) {
                $attachments[] = Attachment::fromData(
                    fn () => base64_decode($attachment['encoded_base']),
                    $attachment['file_name']
                )->withMime($attachment['file_type']);
            }
        }

        return $attachments;
    }
}
