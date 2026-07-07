<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class StaffInvited extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly ?string $resetToken = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:3000'), '/');

        $message = (new MailMessage)
            ->subject("Você foi convidado para a equipe de {$this->tenant->name}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Você agora faz parte da equipe do salão **{$this->tenant->name}** e pode acessar a agenda e os agendamentos.");

        if ($this->resetToken) {
            $resetUrl = "{$frontendUrl}/reset-password?token={$this->resetToken}&email=".urlencode($notifiable->email);

            return $message
                ->line('Para começar, defina sua senha de acesso:')
                ->action('Definir senha', $resetUrl)
                ->line('Este link expira em 60 minutos. Se expirar, use "Esqueceu a senha?" na tela de login.');
        }

        return $message
            ->action('Acessar o painel', "{$frontendUrl}/{$this->tenant->slug}/dashboard")
            ->line('Use sua senha atual para entrar.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id' => $this->tenant->id,
        ];
    }
}
