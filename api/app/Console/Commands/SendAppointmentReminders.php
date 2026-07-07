<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('appointments:send-reminders')]
#[Description('Envia lembrete por e-mail para agendamentos que acontecem em ~24h')]
class SendAppointmentReminders extends Command
{
    /**
     * Roda de hora em hora. A janela de 22–24h antes do horário é mais
     * larga que a cadência, então nenhum agendamento escapa; o
     * reminder_sent_at garante idempotência.
     */
    public function handle(): int
    {
        $sent = 0;

        Appointment::withoutTenantScope()
            ->with(['client', 'service', 'professional'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now()->addHours(22), now()->addHours(24)])
            ->orderBy('starts_at')
            ->each(function (Appointment $appointment) use (&$sent) {
                if (! $appointment->client) {
                    return;
                }

                $appointment->update(['reminder_sent_at' => now()]);
                $appointment->client->notify(new AppointmentReminder($appointment));
                $sent++;
            });

        $this->info("{$sent} lembrete(s) enviado(s).");

        return self::SUCCESS;
    }
}
