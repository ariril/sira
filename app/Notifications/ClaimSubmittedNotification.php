<?php

namespace App\Notifications;

use App\Models\AdditionalTaskClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ClaimSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(public AdditionalTaskClaim $claim) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hasil Tugas Tambahan Dikirim')
            ->greeting('Halo ' . $notifiable->name)
            ->line('Pegawai mengirim hasil tugas tambahan: ' . $this->claim->task?->title)
            ->action('Review Sekarang', url('/kepala-unit/additional-task-claims/review'));
    }
}
