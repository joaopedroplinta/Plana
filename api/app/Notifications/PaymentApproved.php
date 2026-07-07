<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class PaymentApproved extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private readonly Payment $payment) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payment = $this->payment;
        $appointment = $payment->appointment;
        $amount = number_format($payment->amount / 100, 2, ',', '.');

        return (new MailMessage)
            ->subject('Pagamento aprovado!')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Recebemos seu pagamento de **R$ {$amount}** referente a **{$appointment->service->name}**.")
            ->line('Data do agendamento: '.$appointment->starts_at->format('d/m/Y').' às '.$appointment->starts_at->format('H:i'))
            ->line('Seu horário está garantido. Até lá!');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
        ];
    }
}
