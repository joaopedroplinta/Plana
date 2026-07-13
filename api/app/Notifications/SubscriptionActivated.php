<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class SubscriptionActivated extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(private readonly Subscription $subscription) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subscription = $this->subscription;
        $plan = ucfirst($subscription->plan);

        return (new MailMessage)
            ->subject("Plano {$plan} ativado!")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("O plano **{$plan}** do negócio **{$subscription->tenant->name}** está ativo.")
            ->line('Válido até: '.$subscription->expires_at?->format('d/m/Y'))
            ->line('Bom trabalho!');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
        ];
    }
}
