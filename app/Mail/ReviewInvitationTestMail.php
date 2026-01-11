<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class ReviewInvitationTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $toEmail)
    {
        $to = $this->toEmail;
        $this->withSymfonyMessage(function (Email $message) use ($to) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('X-Mail-Type', 'review_invitation_test');
            $headers->addTextHeader('X-Test-To', $to);
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test Email - Review Invitation',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.review-invitation-test',
            with: [
                'toEmail' => $this->toEmail,
                'sentAt' => now(),
            ],
        );
    }
}
