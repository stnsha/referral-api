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
    public $refereeName;
    public $referralReason;
    public $pdfBase64;

    /**
     * Create a new message instance.
     */
    public function __construct($referralId, $refereeName, $referralReason, $pdfBase64 = null)
    {
        $this->referralId = $referralId;
        $this->refereeName = $refereeName;
        $this->referralReason = $referralReason;
        $this->pdfBase64 = $pdfBase64;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New External Referral - ' . $this->referralId,
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

        return $attachments;
    }
}
