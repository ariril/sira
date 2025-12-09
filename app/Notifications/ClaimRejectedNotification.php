<?php

namespace App\Notifications;

use App\Models\AdditionalTaskClaim;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ClaimRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public AdditionalTaskClaim $claim, public ?string $comment = null) {}

    public function via($notifiable): array { return ['mail','database']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Klaim Tugas Ditolak')
            ->greeting('Halo ' . $notifiable->name)
            ->line('Klaim tugas tambahan Anda ditolak: ' . $this->claim->task?->title)
            ->line('Catatan: ' . ($this->comment ?: 'Tidak ada'))
            ->action('Lihat Detail', url('/pegawai-medis/additional-contributions'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Klaim tugas ditolak',
            'task' => $this->claim->task?->title,
            'comment' => $this->comment,
            'claim_id' => $this->claim->id,
            'link' => url('/pegawai-medis/additional-contributions'),
        ];
    }
}
