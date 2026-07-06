'use client'

import { useParams, useSearchParams } from 'next/navigation'
import { Suspense } from 'react'
import { XCircle } from 'lucide-react'

function PaymentFailureContent() {
  const params = useParams()
  const searchParams = useSearchParams()
  const slug = params?.slug as string
  const paymentId = searchParams.get('payment_id')
  const externalRef = searchParams.get('external_reference')

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full text-center space-y-6 p-8">
        <div className="flex justify-center">
          <XCircle className="h-16 w-16 text-red-500" />
        </div>
        <h1 className="text-2xl font-bold text-red-600">Pagamento não aprovado</h1>
        <p className="text-gray-500">
          Não foi possível concluir o pagamento. Seu horário continua reservado como
          pendente — você pode tentar pagar novamente ou pagar no local.
        </p>
        {(paymentId || externalRef) && (
          <p className="text-xs text-gray-400">Referência: {externalRef ?? paymentId}</p>
        )}
        <div className="flex flex-col gap-3">
          <a
            href={`/${slug}/minha-conta`}
            className="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
          >
            Ver meus agendamentos
          </a>
          <a
            href={`/${slug}`}
            className="px-6 py-3 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Voltar ao salão
          </a>
        </div>
      </div>
    </div>
  )
}

export default function PaymentFailurePage() {
  return (
    <Suspense
      fallback={
        <div className="min-h-screen flex items-center justify-center">
          <p>Carregando...</p>
        </div>
      }
    >
      <PaymentFailureContent />
    </Suspense>
  )
}
