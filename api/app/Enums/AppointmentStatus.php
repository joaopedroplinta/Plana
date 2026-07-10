<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
    case NoShow = 'no_show';

    /**
     * Mapa de ações para os status de origem que as permitem.
     *
     * `reschedule` não é uma transição de status em si (o status final
     * depende de quem remarca), mas usa a mesma checagem de "o status
     * atual permite mexer neste agendamento" que `cancel`.
     *
     * @return array<string, list<self>>
     */
    private static function transitions(): array
    {
        return [
            'confirm' => [self::Pending],
            'cancel' => [self::Pending, self::Confirmed],
            'complete' => [self::Pending, self::Confirmed],
            'no_show' => [self::Pending, self::Confirmed],
            'reschedule' => [self::Pending, self::Confirmed],
        ];
    }

    public function allows(string $action): bool
    {
        return in_array($this, self::transitions()[$action] ?? [], true);
    }
}
