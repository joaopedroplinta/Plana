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
     * Corpo (JSON decodificado) da última requisição enviada — capturado de
     * CURLOPT_POSTFIELDS para os testes poderem asserir o que foi mandado ao
     * MercadoPago (ex.: presença/valor de `marketplace_fee`).
     *
     * @var array<string, mixed>|null
     */
    public ?array $lastRequestBody = null;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload, private readonly int $statusCode = 200) {}

    public function setOptionArray(array $value): void
    {
        $body = $value[CURLOPT_POSTFIELDS] ?? null;

        if (is_string($body)) {
            $this->lastRequestBody = json_decode($body, true);
        }
    }

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
