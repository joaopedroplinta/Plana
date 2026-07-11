<?php

namespace Tests\Support;

use MercadoPago\Net\HttpRequest;

/**
 * Implementação fake de HttpRequest usada nos testes que exercitam
 * PaymentService/SubscriptionService de verdade (sem mockar o Service),
 * devolvendo um JSON fixo no formato da API Orders sem nenhuma chamada de
 * rede — mesma técnica usada pela própria SDK em seus testes unitários
 * (vendor/mercadopago/dx-php/tests/.../Unit/Base/BaseClient.php).
 */
class FakeMercadoPagoHttpRequest implements HttpRequest
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload, private readonly int $statusCode = 200) {}

    public function setOptionArray(array $value): void {}

    public function execute(): bool|string
    {
        return json_encode($this->payload);
    }

    public function getInfo(mixed $name): mixed
    {
        return $this->statusCode;
    }

    public function close(): void {}

    public function error(): string
    {
        return '';
    }
}
