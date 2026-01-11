<?php

namespace App\Listeners;

use App\Support\Mail\LastMailSendStore;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Log;

class LogMailMessageSent
{
    public function __construct(private readonly LastMailSendStore $store)
    {
    }

    public function handle(MessageSent $event): void
    {
        $sent = null;
        if (property_exists($event, 'sent')) {
            /** @phpstan-ignore-next-line */
            $sent = $event->sent;
        } elseif (property_exists($event, 'sentMessage')) {
            /** @phpstan-ignore-next-line */
            $sent = $event->sentMessage;
        }

        $msg = $sent && method_exists($sent, 'getOriginalMessage') ? $sent->getOriginalMessage() : null;

        $messageId = null;
        $to = null;
        $subject = null;
        $invitationId = null;
        $mailType = null;

        try {
            if ($msg && method_exists($msg, 'getHeaders')) {
                $headers = $msg->getHeaders();

                $hMessageId = $headers->get('Message-ID');
                $messageId = $hMessageId ? trim((string) $hMessageId->getBodyAsString()) : null;

                $hInv = $headers->get('X-Invitation-ID');
                $invitationId = $hInv ? trim((string) $hInv->getBodyAsString()) : null;

                $hType = $headers->get('X-Mail-Type');
                $mailType = $hType ? trim((string) $hType->getBodyAsString()) : null;
            }

            if ($msg && method_exists($msg, 'getTo')) {
                $to = collect($msg->getTo() ?? [])
                    ->map(fn ($a) => method_exists($a, 'toString') ? $a->toString() : (string) $a)
                    ->implode(', ');
            }

            if ($msg && method_exists($msg, 'getSubject')) {
                $subject = (string) ($msg->getSubject() ?? '');
            }
        } catch (\Throwable $e) {
            // Best-effort only; do not break request.
        }

        if ($messageId) {
            $this->store->setLast($messageId, [
                'to' => $to,
                'subject' => $subject,
                'invitation_id' => $invitationId,
                'mail_type' => $mailType,
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        try {
            $mailer = null;
            if (property_exists($event, 'mailer')) {
                /** @phpstan-ignore-next-line */
                $mailer = $event->mailer;
            }

            Log::channel('mail')->info('mail_sent', [
                'timestamp' => now()->toDateTimeString(),
                'message_id' => $messageId,
                'to' => $to,
                'subject' => $subject,
                'invitation_id' => $invitationId,
                'mail_type' => $mailType,
                'mailer' => $mailer,
            ]);
        } catch (\Throwable $e) {
            // logging should never break the request
        }
    }
}
