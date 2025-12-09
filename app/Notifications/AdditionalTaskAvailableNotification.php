<?php

namespace App\Notifications;

use App\Models\AdditionalTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdditionalTaskAvailableNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AdditionalTask $task) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tugas Tambahan Baru Tersedia')
            ->greeting('Halo ' . $notifiable->name)
            ->line('Sebuah tugas tambahan baru tersedia untuk unit Anda:')
            ->line($this->task->title)
            ->line('Batas waktu: ' . $this->task->due_date)
            ->action('Lihat Tugas', url('/pegawai-medis/additional-tasks'))
            ->line('Segera klaim jika relevan.');
    }
}
