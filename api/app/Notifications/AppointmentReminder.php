<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class AppointmentReminder extends Notification implements ShouldQueue
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
            ->subject('Lembrete: você tem um horário amanhã')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Passando para lembrar do seu horário de **{$appointment->service->name}** com {$appointment->professional->name}.")
            ->line('Data: '.$appointment->starts_at->format('d/m/Y').' às '.$appointment->starts_at->format('H:i'))
            ->line('Se não puder comparecer, cancele pelo painel para liberar o horário.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'appointment_id' => $this->appointment->id,
        ];
    }
}
