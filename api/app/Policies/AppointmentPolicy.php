<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        // Qualquer usuário autenticado pode listar; o controller
        // restringe não-staff aos próprios agendamentos.
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($appointment->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->isStaffOfTenant($tenant) || $appointment->client_id === $user->id;
    }

    public function create(User $user): bool
    {
        // Qualquer usuário autenticado pode agendar — o vínculo de
        // cliente com o salão é criado no primeiro agendamento.
        return true;
    }

    public function confirm(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        return $appointment->tenant_id === $tenant->id
            && $user->isStaffOfTenant($tenant);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        if ($appointment->tenant_id !== $tenant->id) {
            return false;
        }

        return $user->isStaffOfTenant($tenant) || $appointment->client_id === $user->id;
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->confirm($user, $appointment);
    }

    public function noShow(User $user, Appointment $appointment): bool
    {
        return $this->confirm($user, $appointment);
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        // Mesma regra do cancelamento: staff do salão ou o próprio cliente.
        return $this->cancel($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        /** @var Tenant $tenant */
        $tenant = app('currentTenant');

        return $appointment->tenant_id === $tenant->id
            && $user->ownsTenant($tenant);
    }
}
