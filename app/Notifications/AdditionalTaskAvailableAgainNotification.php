<?php

namespace App\Notifications;

use App\Models\AdditionalTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdditionalTaskAvailableAgainNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public AdditionalTask $task) {}

    public function via($notifiable): array { return ['mail']; }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Slot Tugas Tambahan Dibuka Kembali')
            ->greeting('Halo ' . $notifiable->name)
            ->line('Sebuah tugas tambahan kini tersedia lagi:')
            ->line($this->task->title)
            ->action('Klaim Sekarang', url('/pegawai-medis/additional-tasks'))
            ->line('Slot baru terbuka setelah pembatalan klaim sebelumnya.');
    }
}
