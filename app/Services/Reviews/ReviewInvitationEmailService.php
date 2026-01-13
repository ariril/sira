<?php

namespace App\Services\Reviews;

use App\Mail\ReviewInvitationMail;
use App\Mail\ReviewInvitationTestMail;
use App\Models\ReviewInvitation;
use App\Support\Mail\LastMailSendStore;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ReviewInvitationEmailService
{
    public function __construct(private readonly LastMailSendStore $store)
    {
    }

    /**
     * @return array{host:?string,port:?int,encryption:?string}
     */
    private function smtpMeta(): array
    {
        return [
            'host' => env('MAIL_HOST'),
            'port' => env('MAIL_PORT') !== null ? (int) env('MAIL_PORT') : null,
            'encryption' => env('MAIL_ENCRYPTION'),
        ];
    }

    /**
     * Preflight check: can we open a TCP socket to SMTP host:port?
     * This verifies "reachable" and (if ssl) basic TLS handshake.
     */
    private function assertSmtpReachable(): void
    {
        $host = (string) (env('MAIL_HOST') ?? '');
        $port = (int) (env('MAIL_PORT') ?? 0);
        $enc = (string) (env('MAIL_ENCRYPTION') ?? '');

        if ($host === '' || $port <= 0) {
            throw new \RuntimeException('Konfigurasi SMTP belum lengkap (MAIL_HOST/MAIL_PORT).');
        }

        $timeout = 5;
        $scheme = strtolower($enc) === 'ssl' ? 'ssl' : 'tcp';
        $endpoint = $scheme . '://' . $host . ':' . $port;

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($endpoint, $errno, $errstr, $timeout);
        if (!is_resource($fp)) {
            $msg = $errstr !== '' ? $errstr : ('errno=' . $errno);
            throw new \RuntimeException('Gagal konek ke SMTP ' . $host . ':' . $port . ' (' . $msg . ').');
        }

        fclose($fp);
    }

    public function sendSingle(ReviewInvitation $invitation): void
    {
        $email = trim((string) ($invitation->email ?? ''));
        if ($email === '') {
            throw new \InvalidArgumentException('Email undangan belum diisi.');
        }

        if ($invitation->used_at !== null || (string) $invitation->status === 'used') {
            throw new \InvalidArgumentException('Undangan sudah digunakan.');
        }

        $token = (string) ($invitation->token_plain ?? '');
        if ($token === '') {
            throw new \RuntimeException('Token undangan tidak tersedia.');
        }

        $shortUrl = url('/r/' . $token);

        try {
            $this->assertSmtpReachable();
            $this->store->clear();
            Mail::to($email)->send(new ReviewInvitationMail($invitation->loadMissing('unit'), $shortUrl));
        } catch (TransportExceptionInterface $e) {
            Log::error('review_invitation_email_send_failed', [
                'invitation_id' => $invitation->id,
                'registration_ref' => $invitation->registration_ref,
                'email' => $email,
                'error' => $e->getMessage(),
                'smtp' => $this->smtpMeta(),
            ]);
            throw new \RuntimeException('SMTP error: ' . $e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            Log::error('review_invitation_email_send_failed', [
                'invitation_id' => $invitation->id,
                'registration_ref' => $invitation->registration_ref,
                'email' => $email,
                'error' => $e->getMessage(),
                'smtp' => $this->smtpMeta(),
            ]);

            throw $e;
        }

        $messageId = $this->store->lastMessageId();
        Log::channel('mail')->info('review_invitation_email_accepted', [
            'timestamp' => now()->toDateTimeString(),
            'invitation_id' => $invitation->id,
            'to_email' => $email,
            'subject' => 'Survei Kepuasan Pelayanan RSUD MGR. Gabriel Manek SVD Atambua',
            'message_id' => $messageId,
            'smtp' => $this->smtpMeta(),
        ]);

        $now = Carbon::now();

        $nextStatus = (string) $invitation->status;
        if (in_array($nextStatus, ['created', 'sent', ''], true)) {
            $nextStatus = 'sent';
        }

        $invitation->forceFill([
            'sent_at' => $now,
            'status' => $nextStatus,
        ])->save();
    }

    /**
     * @return array{success:bool,message_id:?string,sent_at:?string,smtp:array{host:?string,port:?int,encryption:?string},error:?string}
     */
    public function sendTestEmail(string $toEmail): array
    {
        $toEmail = trim($toEmail);

        try {
            $this->assertSmtpReachable();
            $this->store->clear();
            Mail::to($toEmail)->send(new ReviewInvitationTestMail($toEmail));
        } catch (TransportExceptionInterface $e) {
            Log::channel('mail')->error('mail_test_failed', [
                'timestamp' => now()->toDateTimeString(),
                'to_email' => $toEmail,
                'error' => $e->getMessage(),
                'smtp' => $this->smtpMeta(),
            ]);

            return [
                'success' => false,
                'to' => $toEmail,
                'message_id' => null,
                'sent_at' => null,
                'smtp' => $this->smtpMeta(),
                'error' => 'SMTP error: ' . $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            Log::channel('mail')->error('mail_test_failed', [
                'timestamp' => now()->toDateTimeString(),
                'to_email' => $toEmail,
                'error' => $e->getMessage(),
                'smtp' => $this->smtpMeta(),
            ]);

            return [
                'success' => false,
                'to' => $toEmail,
                'message_id' => null,
                'sent_at' => null,
                'smtp' => $this->smtpMeta(),
                'error' => $e->getMessage(),
            ];
        }

        $messageId = $this->store->lastMessageId();
        $sentAt = now()->toDateTimeString();

        Log::channel('mail')->info('mail_test_accepted', [
            'timestamp' => $sentAt,
            'to_email' => $toEmail,
            'message_id' => $messageId,
            'smtp' => $this->smtpMeta(),
        ]);

        return [
            'success' => true,
            'to' => $toEmail,
            'message_id' => $messageId,
            'sent_at' => $sentAt,
            'smtp' => $this->smtpMeta(),
            'error' => null,
        ];
    }

    /**
     * @return array{attempted:int,sent:int,failed:int}
     */
    public function sendBulkByPeriod(int $periodId): array
    {
        $attempted = 0;
        $sent = 0;
        $failed = 0;

        ReviewInvitation::query()
            ->where('assessment_period_id', $periodId)
            ->whereNull('used_at')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereNull('sent_at')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$attempted, &$sent, &$failed) {
                foreach ($rows as $invitation) {
                    $attempted++;
                    try {
                        $this->sendSingle($invitation);
                        $sent++;
                    } catch (\Throwable $e) {
                        $failed++;
                    }
                }
            });

        return [
            'attempted' => $attempted,
            'sent' => $sent,
            'failed' => $failed,
        ];
    }
}
