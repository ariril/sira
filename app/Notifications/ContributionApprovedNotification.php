<?php

namespace App\Notifications;

use App\Models\AdditionalContribution;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ContributionApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(public AdditionalContribution $contribution) {}

    public function via($notifiable): array { return ['mail','database']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kontribusi Tambahan Disetujui')
            ->greeting('Halo ' . $notifiable->name)
            ->line('Kontribusi tambahan Anda disetujui: ' . $this->contribution->title)
            ->line('Bonus akan dihitung dalam remunerasi periode terkait.')
            ->action('Lihat Kontribusi', url('/pegawai-medis/additional-contributions'));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'title' => 'Kontribusi tambahan disetujui',
            'contribution_id' => $this->contribution->id,
            'task_id' => $this->contribution->task_id,
            'bonus_awarded' => $this->contribution->bonus_awarded,
            'score' => $this->contribution->score,
            'link' => url('/pegawai-medis/additional-contributions'),
        ];
    }
}
