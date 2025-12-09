<?php

namespace App\Notifications;

use App\Models\AdditionalTaskClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ClaimValidatedNotification extends Notification
{
    use Queueable;

    public function __construct(public AdditionalTaskClaim $claim) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Klaim Tugas Anda Divalidasi')
            ->greeting('Halo ' . $notifiable->name)
            ->line('Klaim tugas tambahan Anda telah divalidasi dan menunggu keputusan akhir: ' . $this->claim->task?->title)
            ->action('Lihat Status', url('/pegawai-medis/additional-contributions'));
    }
}
