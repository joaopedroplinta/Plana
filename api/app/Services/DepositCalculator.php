<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Tenant;

/**
 * Resolve o sinal (valor de reserva) cobrado online de um serviço, aplicando a
 * herança padrão-do-salão → override-do-serviço:
 *
 *   - o serviço com `deposit_type` próprio (não-null) vence o padrão do salão;
 *   - senão, herda o padrão do salão (tenants.settings.deposit_type/value);
 *   - 'none' (ou ausência de config) => sem sinal, cobra o valor cheio (null).
 *
 * O valor devolvido é em centavos, nunca maior que o preço do serviço nem
 * menor que 1 (sinal de R$0 não faz sentido — cai em "sem sinal"/null).
 */
class DepositCalculator
{
    /**
     * Sinal em centavos a cobrar na reserva, ou `null` quando não há sinal
     * (cobra-se o preço cheio do serviço).
     */
    public function amountFor(Service $service, Tenant $tenant): ?int
    {
        [$type, $value] = $this->resolveConfig($service, $tenant);

        if ($type === 'none' || $type === null || $value === null) {
            return null;
        }

        $deposit = match ($type) {
            'percentage' => (int) round($service->price * $value / 100),
            'fixed' => (int) $value,
            default => null,
        };

        if ($deposit === null || $deposit < 1) {
            return null;
        }

        // Sinal nunca ultrapassa o total do serviço.
        return min($deposit, $service->price);
    }

    /**
     * Config efetiva (tipo, valor): override do serviço se declarado, senão o
     * padrão do salão guardado no jsonb `settings`.
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function resolveConfig(Service $service, Tenant $tenant): array
    {
        if ($service->deposit_type !== null) {
            return [$service->deposit_type, $service->deposit_value];
        }

        $settings = $tenant->settings ?? [];

        return [
            $settings['deposit_type'] ?? 'none',
            isset($settings['deposit_value']) ? (int) $settings['deposit_value'] : null,
        ];
    }
}
