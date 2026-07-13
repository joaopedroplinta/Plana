'use client'

import { useParams, useSearchParams } from 'next/navigation'
import { Suspense } from 'react'

function PaymentSuccessContent() {
  const params = useParams()
  const searchParams = useSearchParams()
  const slug = params?.slug as string
  const paymentId = searchParams.get('payment_id')
  const externalRef = searchParams.get('external_reference')

  return (
    <div className="min-h-screen flex items-center justify-center bg-muted">
      <div className="max-w-md w-full text-center space-y-6 p-8">
        <div className="text-6xl flex justify-center">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            className="h-16 w-16 text-green-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
          >
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <h1 className="text-2xl font-bold text-green-600">Pagamento confirmado!</h1>
        <p className="text-muted-foreground">
          Seu agendamento está garantido. Em breve você receberá uma confirmação.
        </p>
        {(paymentId || externalRef) && (
          <p className="text-xs text-muted-foreground">
            Referência: {externalRef ?? paymentId}
          </p>
        )}
        <div className="flex flex-col gap-3">
          <a
            href={`/${slug}`}
            className="px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors"
          >
            Voltar ao negócio
          </a>
          <a
            href={`/${slug}/booking`}
            className="px-6 py-3 border border-primary text-primary rounded-lg hover:bg-secondary dark:hover:bg-primary/15 transition-colors"
          >
            Fazer outro agendamento
          </a>
        </div>
      </div>
    </div>
  )
}

export default function PaymentSuccessPage() {
  return (
    <Suspense fallback={<div className="min-h-screen flex items-center justify-center"><p>Carregando...</p></div>}>
      <PaymentSuccessContent />
    </Suspense>
  )
}
