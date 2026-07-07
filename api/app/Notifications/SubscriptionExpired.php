<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpired extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $previousPlan,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = ucfirst($this->previousPlan);

        return (new MailMessage)
            ->subject('Sua assinatura expirou')
            ->greeting("Olá, {$notifiable->name}!")
            ->line("A assinatura do plano **{$plan}** do salão **{$this->tenant->name}** expirou e o salão voltou para o plano Starter.")
            ->line('Para manter os recursos do seu plano, renove a assinatura no painel do salão em Planos.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
            'previous_plan' => $this->previousPlan,
        ];
    }
}
