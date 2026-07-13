<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class AppointmentBooked extends Notification implements ShouldQueue
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
            ->subject('Agendamento recebido — aguardando confirmação')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Recebemos seu agendamento de **{$appointment->service->name}** com {$appointment->professional->name}.")
            ->line('Data: '.$appointment->starts_at->format('d/m/Y').' às '.$appointment->starts_at->format('H:i'))
            ->line('Assim que o negócio confirmar, você receberá um novo e-mail.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
        ];
    }
}
