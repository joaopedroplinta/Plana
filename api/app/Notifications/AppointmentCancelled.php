<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class AppointmentCancelled extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private readonly Appointment $appointment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment;

        return (new MailMessage)
            ->subject('Agendamento cancelado')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O agendamento de **{$appointment->service->name}** com {$appointment->professional->name} foi cancelado.")
            ->line('Data que estava marcada: '.$appointment->starts_at->format('d/m/Y').' às '.$appointment->starts_at->format('H:i'));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
        ];
    }
}
