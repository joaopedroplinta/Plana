<?php

namespace App\Jobs;

use App\Services\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * Processa o webhook fora do ciclo da request — o MercadoPago
     * recebe 200 imediatamente e nós reprocessamos em caso de falha.
     */
    public function handle(PaymentService $paymentService): void
    {
        $paymentService->handleWebhook($this->payload);
    }
}
