<?php

namespace App\Notifications;

use App\Models\AdditionalContribution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ContributionRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public AdditionalContribution $contribution) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kontribusi Tambahan Ditolak')
            ->greeting('Halo ' . $notifiable->name)
            ->line('Kontribusi tambahan Anda ditolak: ' . $this->contribution->title)
            ->line('Alasan/komentar: ' . ($this->contribution->supervisor_comment ?: 'Tidak diberikan'))
            ->action('Lihat Kontribusi', url('/pegawai-medis/additional-contributions'));
    }
}
