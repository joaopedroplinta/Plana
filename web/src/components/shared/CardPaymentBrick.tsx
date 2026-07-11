'use client'

import { useState } from 'react'
import { CardPayment, initMercadoPago } from '@mercadopago/sdk-react'
import { toast } from 'sonner'
import type { CardPaymentData } from '@/types'

// initMercadoPago() só precisa (e só deve) ser chamado uma vez por sessão
// de página — evita reinicializar o SDK a cada montagem do Brick.
let mercadoPagoInitialized = false

interface CardPaymentBrickProps {
  /** Valor a ser cobrado, em reais (não em centavos). */
  amount: number
  payerEmail?: string
  onApprove: (data: CardPaymentData) => Promise<void>
}

/**
 * Formulário de cartão embutido (Checkout Transparente) via Card Payment
 * Brick do MercadoPago.js — substitui o antigo redirect para o Checkout
 * Pro. O número do cartão nunca passa pelo nosso backend: o Brick tokeniza
 * localmente e devolve só o token em `onSubmit`.
 */
export function CardPaymentBrick({ amount, payerEmail, onApprove }: CardPaymentBrickProps) {
  const publicKey = process.env.NEXT_PUBLIC_MERCADOPAGO_PUBLIC_KEY

  // Inicialização do SDK é um side-effect de singleton (idempotente via
  // `mercadoPagoInitialized`) — mesmo padrão de lazy initializer usado para
  // ler localStorage neste projeto, em vez de setState síncrono num efeito.
  const [ready] = useState(() => {
    if (!publicKey || mercadoPagoInitialized) return !!publicKey

    initMercadoPago(publicKey, { locale: 'pt-BR' })
    mercadoPagoInitialized = true

    return true
  })

  if (!publicKey) {
    return (
      <p className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
        Pagamento com cartão indisponível no momento (chave pública do MercadoPago não
        configurada).
      </p>
    )
  }

  if (!ready) {
    return <p className="text-center text-sm text-gray-400 animate-pulse">Carregando formulário de cartão...</p>
  }

  return (
    <CardPayment
      initialization={{
        amount,
        payer: payerEmail ? { email: payerEmail } : undefined,
      }}
      onSubmit={async (formData) => {
        await onApprove({
          token: formData.token,
          payment_method_id: formData.payment_method_id,
          installments: formData.installments,
          issuer_id: formData.issuer_id,
          payer: {
            email: formData.payer?.email,
            identification: formData.payer?.identification,
          },
        })
      }}
      onError={(error) => {
        toast.error(error.message || 'Erro ao processar dados do cartão.')
      }}
    />
  )
}
