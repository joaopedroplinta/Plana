'use client'

import { useEffect, useRef, useState } from 'react'
import { useParams, useRouter } from 'next/navigation'
import { useAuth } from '@/hooks/useAuth'
import { subscriptionService } from '@/services/subscription'
import type { Subscription, SubscriptionPlan, SubscriptionResponse } from '@/types'
import { formatPrice } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

type PaymentMethod = 'pix' | 'credit_card'

type ModalState =
  | { step: 'choose_method'; plan: SubscriptionPlan }
  | { step: 'pix_waiting'; plan: SubscriptionPlan; subscription: Subscription }
  | { step: 'approved'; plan: SubscriptionPlan }
  | null

export default function PlanosPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''
  const router = useRouter()
  const { user } = useAuth()

  const [data, setData] = useState<SubscriptionResponse | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [modal, setModal] = useState<ModalState>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null)

  const isOwner = user?.roles?.some((r) => r.name === 'salon_owner') ?? false

  useEffect(() => {
    if (!slug) return
    subscriptionService
      .get(slug)
      .then((res) => setData(res.data.data))
      .catch(() => setError('Erro ao carregar planos'))
      .finally(() => setIsLoading(false))
  }, [slug])

  // Cleanup polling on unmount
  useEffect(() => {
    return () => {
      if (pollingRef.current) {
        clearInterval(pollingRef.current)
      }
    }
  }, [])

  function startPolling(plan: SubscriptionPlan) {
    if (pollingRef.current) clearInterval(pollingRef.current)

    pollingRef.current = setInterval(() => {
      subscriptionService
        .get(slug)
        .then((res) => {
          const latest = res.data.data.subscriptions[0]
          if (latest?.status === 'approved') {
            clearInterval(pollingRef.current!)
            pollingRef.current = null
            setData(res.data.data)
            setModal({ step: 'approved', plan })
          }
        })
        .catch(() => {})
    }, 5000)
  }

  async function handleSelectPlan(plan: SubscriptionPlan, method: PaymentMethod) {
    if (!isOwner) return
    setIsSubmitting(true)

    try {
      const res = await subscriptionService.create(slug, {
        plan: plan.key,
        method,
      })
      const subscription = res.data.data

      if (plan.key === 'starter' || subscription.status === 'approved') {
        const refreshed = await subscriptionService.get(slug)
        setData(refreshed.data.data)
        setModal({ step: 'approved', plan })
        return
      }

      if (method === 'credit_card' && subscription.mp_preference_id) {
        router.push(subscription.mp_preference_id)
        return
      }

      if (method === 'pix') {
        setModal({ step: 'pix_waiting', plan, subscription })
        startPolling(plan)
      }
    } catch {
      setError('Erro ao processar pagamento. Tente novamente.')
    } finally {
      setIsSubmitting(false)
    }
  }

  function handlePlanClick(plan: SubscriptionPlan) {
    if (!isOwner) return
    if (plan.price === 0) {
      handleSelectPlan(plan, 'pix')
      return
    }
    setModal({ step: 'choose_method', plan })
  }

  function closeModal() {
    if (pollingRef.current) {
      clearInterval(pollingRef.current)
      pollingRef.current = null
    }
    setModal(null)
  }

  const planBadgeColors: Record<string, string> = {
    starter: 'bg-gray-100 text-gray-700',
    pro: 'bg-indigo-100 text-indigo-700',
    enterprise: 'bg-purple-100 text-purple-700',
  }

  if (isLoading) {
    return (
      <div className="flex items-center justify-center py-20">
        <p className="text-sm text-gray-500">Carregando planos...</p>
      </div>
    )
  }

  if (error) {
    return (
      <div className="flex items-center justify-center py-20">
        <p className="text-sm text-red-500">{error}</p>
      </div>
    )
  }

  const currentPlan = data?.current_plan ?? 'starter'
  const plans = data?.plans ?? []

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Planos e assinatura</h1>
        <p className="mt-1 text-sm text-gray-500">
          Escolha o plano ideal para o seu salao.
        </p>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        {plans.map((plan) => {
          const isActive = plan.key === currentPlan
          return (
            <Card
              key={plan.key}
              className={[
                'relative flex flex-col transition-shadow',
                isActive ? 'ring-2 ring-indigo-500 shadow-md' : 'hover:shadow-md',
              ].join(' ')}
            >
              {isActive && (
                <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                  <Badge className="bg-indigo-600 text-white px-3 py-0.5 text-xs">
                    Plano atual
                  </Badge>
                </div>
              )}
              <CardHeader className="pb-3">
                <CardTitle className="flex items-center justify-between">
                  <span>{plan.name}</span>
                  <span className={`text-xs font-medium px-2 py-1 rounded-full ${planBadgeColors[plan.key]}`}>
                    {plan.key.toUpperCase()}
                  </span>
                </CardTitle>
                <div className="mt-2">
                  {plan.price === 0 ? (
                    <span className="text-3xl font-bold text-gray-900">Gratis</span>
                  ) : (
                    <span className="text-3xl font-bold text-gray-900">
                      {formatPrice(plan.price)}
                      <span className="text-sm font-normal text-gray-500">/mes</span>
                    </span>
                  )}
                </div>
              </CardHeader>
              <CardContent className="flex-1 space-y-2">
                <ul className="space-y-1.5">
                  {plan.features.map((feature) => (
                    <li key={feature} className="flex items-center gap-2 text-sm text-gray-700">
                      <svg
                        className="h-4 w-4 shrink-0 text-green-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                      >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                      </svg>
                      {feature}
                    </li>
                  ))}
                </ul>
              </CardContent>
              <CardFooter>
                <Button
                  className="w-full"
                  variant={isActive ? 'outline' : 'default'}
                  disabled={isActive || !isOwner || isSubmitting}
                  onClick={() => handlePlanClick(plan)}
                >
                  {isActive ? 'Plano atual' : 'Selecionar'}
                </Button>
              </CardFooter>
            </Card>
          )
        })}
      </div>

      {/* Recent subscriptions */}
      {(data?.subscriptions ?? []).length > 0 && (
        <div className="space-y-3">
          <h2 className="text-base font-semibold text-gray-900">Historico de assinaturas</h2>
          <div className="overflow-hidden rounded-lg border bg-white">
            <table className="min-w-full divide-y divide-gray-200 text-sm">
              <thead className="bg-gray-50">
                <tr>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">Plano</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">Metodo</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">Valor</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">Status</th>
                  <th className="px-4 py-3 text-left font-medium text-gray-500">Data</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {data?.subscriptions.map((sub) => (
                  <tr key={sub.id}>
                    <td className="px-4 py-3 font-medium capitalize">{sub.plan}</td>
                    <td className="px-4 py-3 capitalize">{sub.method === 'credit_card' ? 'Cartao' : 'PIX'}</td>
                    <td className="px-4 py-3">{formatPrice(sub.amount)}</td>
                    <td className="px-4 py-3">
                      <StatusBadge status={sub.status} />
                    </td>
                    <td className="px-4 py-3 text-gray-500">
                      {new Date(sub.created_at).toLocaleDateString('pt-BR')}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Payment method choice modal */}
      <Dialog open={modal?.step === 'choose_method'} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              Assinar plano {modal?.step === 'choose_method' ? modal.plan.name : ''}
            </DialogTitle>
            <DialogDescription>Escolha como deseja pagar</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 pt-2">
            <Button
              variant="outline"
              className="h-14 justify-start gap-3"
              disabled={isSubmitting}
              onClick={() =>
                modal?.step === 'choose_method' && handleSelectPlan(modal.plan, 'pix')
              }
            >
              <span className="text-2xl">PIX</span>
              <span className="text-sm">Pagamento instantaneo via PIX</span>
            </Button>
            <Button
              variant="outline"
              className="h-14 justify-start gap-3"
              disabled={isSubmitting}
              onClick={() =>
                modal?.step === 'choose_method' &&
                handleSelectPlan(modal.plan, 'credit_card')
              }
            >
              <span className="text-2xl">Cartao</span>
              <span className="text-sm">Cartao de credito via MercadoPago</span>
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* PIX waiting modal */}
      <Dialog open={modal?.step === 'pix_waiting'} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Pagamento via PIX</DialogTitle>
            <DialogDescription>
              Escaneie o QR Code ou copie o codigo para pagar
            </DialogDescription>
          </DialogHeader>
          {modal?.step === 'pix_waiting' && (
            <div className="space-y-4 pt-2">
              {modal.subscription.pix_qr_code_base64 && (
                <div className="flex justify-center">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={`data:image/png;base64,${modal.subscription.pix_qr_code_base64}`}
                    alt="QR Code PIX"
                    className="h-48 w-48"
                  />
                </div>
              )}
              {modal.subscription.pix_qr_code && (
                <div className="space-y-2">
                  <p className="text-xs font-medium text-gray-600">Codigo copia e cola</p>
                  <div
                    className="cursor-pointer rounded-md bg-gray-50 p-3 text-xs break-all font-mono text-gray-700 border hover:bg-gray-100 transition-colors"
                    onClick={() => navigator.clipboard.writeText(modal.subscription.pix_qr_code!)}
                  >
                    {modal.subscription.pix_qr_code}
                  </div>
                  <p className="text-xs text-gray-400">Clique para copiar</p>
                </div>
              )}
              <p className="text-center text-sm text-gray-500">
                Aguardando confirmacao do pagamento...
              </p>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Approved modal */}
      <Dialog open={modal?.step === 'approved'} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="sm:max-w-sm text-center">
          <div className="flex flex-col items-center gap-4 pt-4">
            <svg
              className="h-14 w-14 text-green-500"
              fill="none"
              viewBox="0 0 24 24"
              stroke="currentColor"
              strokeWidth={2}
            >
              <path strokeLinecap="round" strokeLinejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
              <DialogTitle className="text-lg font-bold text-green-600">
                Plano ativado!
              </DialogTitle>
              <DialogDescription className="mt-1">
                Seu plano {modal?.step === 'approved' ? modal.plan.name : ''} foi ativado com sucesso.
              </DialogDescription>
            </div>
            <Button className="w-full" onClick={closeModal}>
              Continuar
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  )
}

function StatusBadge({ status }: { status: Subscription['status'] }) {
  const styles: Record<Subscription['status'], string> = {
    pending: 'bg-yellow-100 text-yellow-700',
    approved: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    cancelled: 'bg-gray-100 text-gray-500',
  }
  const labels: Record<Subscription['status'], string> = {
    pending: 'Pendente',
    approved: 'Aprovado',
    rejected: 'Recusado',
    cancelled: 'Cancelado',
  }
  return (
    <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${styles[status]}`}>
      {labels[status]}
    </span>
  )
}
