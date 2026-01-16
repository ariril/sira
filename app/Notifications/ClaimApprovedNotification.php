<?php

namespace App\Notifications;

use App\Models\AdditionalTaskClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ClaimApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public AdditionalTaskClaim $claim) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        $points = $this->claim->awarded_points ?? $this->claim->task?->points;

        return (new MailMessage)
            ->subject('Klaim Tugas Disetujui')
            ->greeting('Halo ' . $notifiable->name)
            ->line('Klaim tugas tambahan Anda disetujui: ' . $this->claim->task?->title)
            ->line('Poin yang diberikan: ' . ($points !== null ? rtrim(rtrim(number_format((float) $points, 2, ',', '.'), '0'), ',') : '-'))
            ->action('Lihat Tugas Tambahan', url('/pegawai-medis/additional-tasks'));
    }
}
