'use client'

import { useParams } from 'next/navigation'
import Link from 'next/link'

export default function SubscriptionSuccessPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50">
      <div className="max-w-md w-full text-center space-y-6 p-8">
        <div className="flex justify-center">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            className="h-16 w-16 text-green-500"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth={2}
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        </div>
        <div>
          <h1 className="text-2xl font-bold text-green-600">Assinatura confirmada!</h1>
          <p className="mt-2 text-gray-500">
            Seu plano foi ativado com sucesso. Agora voce tem acesso a todos os recursos do plano escolhido.
          </p>
        </div>
        <div className="flex flex-col gap-3">
          <Link
            href={`/${slug}/dashboard/planos`}
            className="px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
          >
            Ver meu plano
          </Link>
          <Link
            href={`/${slug}/dashboard`}
            className="px-6 py-3 border border-indigo-600 text-indigo-600 rounded-lg hover:bg-indigo-50 transition-colors"
          >
            Ir para o dashboard
          </Link>
        </div>
      </div>
    </div>
  )
}
