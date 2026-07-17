<?php

use App\Models\Service;
use App\Models\Tenant;
use App\Services\DepositCalculator;

/*
|--------------------------------------------------------------------------
| Resolução do sinal (valor de reserva): herança padrão-do-salão → override
| do serviço, formatos fixo/percentual, e os limites (clamp no preço, sinal
| mínimo). Modelos montados em memória — não toca o banco.
|--------------------------------------------------------------------------
*/

function depositService(int $price, ?string $type = null, ?int $value = null): Service
{
    return new Service(['price' => $price, 'deposit_type' => $type, 'deposit_value' => $value]);
}

function depositTenant(?string $type = null, ?int $value = null): Tenant
{
    $settings = [];

    if ($type !== null) {
        $settings['deposit_type'] = $type;
    }

    if ($value !== null) {
        $settings['deposit_value'] = $value;
    }

    return new Tenant(['settings' => $settings]);
}

function calc(): DepositCalculator
{
    return new DepositCalculator;
}

it('sem sinal em nenhum nível => cobra valor cheio (null)', function () {
    expect(calc()->amountFor(depositService(10000), depositTenant()))->toBeNull();
});

it('herda o padrão percentual do salão quando o serviço não tem override', function () {
    // 20% de R$100 = R$20
    expect(calc()->amountFor(depositService(10000), depositTenant('percentage', 20)))->toBe(2000);
});

it('herda o padrão fixo do salão', function () {
    expect(calc()->amountFor(depositService(10000), depositTenant('fixed', 3000)))->toBe(3000);
});

it('override do serviço vence o padrão do salão', function () {
    // salão fixo R$30, mas o serviço manda 50% => R$50
    expect(calc()->amountFor(depositService(10000, 'percentage', 50), depositTenant('fixed', 3000)))->toBe(5000);
});

it("serviço 'none' desativa o sinal mesmo com padrão do salão ativo", function () {
    expect(calc()->amountFor(depositService(10000, 'none'), depositTenant('percentage', 20)))->toBeNull();
});

it('sinal fixo nunca ultrapassa o preço do serviço (clamp)', function () {
    // fixo R$150 num serviço de R$100 => cobra no máximo R$100
    expect(calc()->amountFor(depositService(10000, 'fixed', 15000), depositTenant()))->toBe(10000);
});

it('percentual arredonda para o centavo', function () {
    // 33% de R$99,99 (9999) = 3299.67 => 3300
    expect(calc()->amountFor(depositService(9999, 'percentage', 33), depositTenant()))->toBe(3300);
});

it("salão 'none' => sem sinal", function () {
    expect(calc()->amountFor(depositService(10000), depositTenant('none')))->toBeNull();
});
