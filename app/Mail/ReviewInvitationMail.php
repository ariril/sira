<?php

namespace App\Mail;

use App\Models\ReviewInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class ReviewInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ReviewInvitation $invitation, public readonly string $shortUrl)
    {
        $invId = (string) $invitation->id;
        $this->withSymfonyMessage(function (Email $message) use ($invId) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('X-Mail-Type', 'review_invitation');
            $headers->addTextHeader('X-Invitation-ID', $invId);
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Undangan Survei Kepuasan',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.review-invitation',
            with: [
                'invitation' => $this->invitation,
                'shortUrl' => $this->shortUrl,
            ],
        );
    }
}
