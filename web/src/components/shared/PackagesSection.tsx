'use client'

import { useEffect, useRef, useState } from 'react'
import { useRouter } from 'next/navigation'
import { Package } from 'lucide-react'
import { toast } from 'sonner'
import { useAuth } from '@/hooks/useAuth'
import { packagePurchasesService } from '@/services/packagePurchases'
import type { CardPaymentData, PackagePurchase, ServicePackage } from '@/types/index'
import { formatPrice } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { CardPaymentBrick } from '@/components/shared/CardPaymentBrick'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

type PaymentMethod = 'pix' | 'credit_card'

type ModalState =
  | { step: 'choose_method'; pkg: ServicePackage }
  | { step: 'card_form'; pkg: ServicePackage }
  | { step: 'pix_waiting'; pkg: ServicePackage; purchase: PackagePurchase }
  | { step: 'approved'; pkg: ServicePackage }
  | null

interface PackagesSectionProps {
  slug: string
  packages: ServicePackage[]
}

export function PackagesSection({ slug, packages }: PackagesSectionProps) {
  const router = useRouter()
  const { isAuthenticated, user } = useAuth()

  const [modal, setModal] = useState<ModalState>(null)
  const [isSubmitting, setIsSubmitting] = useState(false)

  const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null)

  useEffect(() => {
    return () => {
      if (pollingRef.current) clearInterval(pollingRef.current)
    }
  }, [])

  function stopPolling() {
    if (pollingRef.current) {
      clearInterval(pollingRef.current)
      pollingRef.current = null
    }
  }

  function startPolling(pkg: ServicePackage, purchaseId: string) {
    stopPolling()
    pollingRef.current = setInterval(() => {
      packagePurchasesService
        .show(slug, purchaseId)
        .then((res) => {
          if (res.data.data.status === 'active') {
            stopPolling()
            setModal({ step: 'approved', pkg })
          }
        })
        .catch(() => {})
    }, 5000)
  }

  function handlePackageClick(pkg: ServicePackage) {
    if (!isAuthenticated) {
      router.push(`/login?redirect=${encodeURIComponent(`/${slug}`)}`)
      return
    }
    setModal({ step: 'choose_method', pkg })
  }

  async function handleSelectMethod(pkg: ServicePackage, method: PaymentMethod) {
    if (method === 'credit_card') {
      setModal({ step: 'card_form', pkg })
      return
    }

    setIsSubmitting(true)
    try {
      const res = await packagePurchasesService.purchase(slug, pkg.id, method)
      const purchase = res.data.data

      setModal({ step: 'pix_waiting', pkg, purchase })
      startPolling(pkg, purchase.id)
    } catch {
      toast.error('Erro ao processar pagamento. Tente novamente.')
    } finally {
      setIsSubmitting(false)
    }
  }

  async function handleCardSubmit(pkg: ServicePackage, cardData: CardPaymentData) {
    setIsSubmitting(true)
    try {
      const res = await packagePurchasesService.purchase(slug, pkg.id, 'credit_card', cardData)
      const purchase = res.data.data

      if (purchase.status === 'active') {
        setModal({ step: 'approved', pkg })
        return
      }

      if (purchase.payment?.status === 'rejected' || purchase.payment?.status === 'cancelled') {
        toast.error('Pagamento recusado pela operadora do cartão. Verifique os dados e tente novamente.')
        return
      }

      // Ainda pendente (incomum para cartão) — aguarda confirmação como no PIX.
      setModal({ step: 'pix_waiting', pkg, purchase })
      startPolling(pkg, purchase.id)
    } catch {
      toast.error('Erro ao processar pagamento. Tente novamente.')
    } finally {
      setIsSubmitting(false)
    }
  }

  function closeModal() {
    stopPolling()
    setModal(null)
  }

  if (packages.length === 0) return null

  return (
    <section className="mx-auto max-w-4xl px-6 py-12">
      <h2 className="text-xl font-bold text-foreground">Pacotes de sessões</h2>
      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        {packages.map((pkg) => (
          <Card key={pkg.id} className="flex flex-col p-5">
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="font-semibold text-foreground">{pkg.name}</p>
                {pkg.description && (
                  <p className="mt-1 text-sm text-muted-foreground line-clamp-2">{pkg.description}</p>
                )}
              </div>
              <p className="whitespace-nowrap text-lg font-bold text-indigo-600">
                {formatPrice(pkg.price)}
              </p>
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-2">
              <Badge variant="secondary" className="gap-1 text-xs">
                <Package className="h-3 w-3" />
                {pkg.sessions} sessões
              </Badge>
              <Badge variant="secondary" className="text-xs">
                Válido por {pkg.valid_days} dias
              </Badge>
            </div>
            {pkg.services.length > 0 && (
              <p className="mt-3 text-xs text-muted-foreground">
                Inclui: {pkg.services.map((s) => s.name).join(', ')}
              </p>
            )}
            <Button className="mt-4 w-full" onClick={() => handlePackageClick(pkg)}>
              Comprar pacote
            </Button>
          </Card>
        ))}
      </div>

      {/* Payment method choice modal */}
      <Dialog open={modal?.step === 'choose_method'} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              Comprar {modal?.step === 'choose_method' ? modal.pkg.name : ''}
            </DialogTitle>
            <DialogDescription>Escolha como deseja pagar</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 pt-2">
            <Button
              variant="outline"
              className="h-14 justify-start gap-3"
              disabled={isSubmitting}
              onClick={() =>
                modal?.step === 'choose_method' && handleSelectMethod(modal.pkg, 'pix')
              }
            >
              <span className="text-2xl">PIX</span>
              <span className="text-sm">Pagamento instantâneo via PIX</span>
            </Button>
            <Button
              variant="outline"
              className="h-14 justify-start gap-3"
              disabled={isSubmitting}
              onClick={() =>
                modal?.step === 'choose_method' && handleSelectMethod(modal.pkg, 'credit_card')
              }
            >
              <span className="text-2xl">Cartão</span>
              <span className="text-sm">Cartão de crédito via MercadoPago</span>
            </Button>
          </div>
        </DialogContent>
      </Dialog>

      {/* Card form modal — Checkout Transparente via Card Payment Brick */}
      <Dialog open={modal?.step === 'card_form'} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Pagamento com cartão</DialogTitle>
            <DialogDescription>
              {modal?.step === 'card_form' ? modal.pkg.name : ''} — {' '}
              {modal?.step === 'card_form' ? formatPrice(modal.pkg.price) : ''}
            </DialogDescription>
          </DialogHeader>
          {modal?.step === 'card_form' && (
            <CardPaymentBrick
              amount={modal.pkg.price / 100}
              payerEmail={user?.email}
              onApprove={(cardData) => handleCardSubmit(modal.pkg, cardData)}
            />
          )}
        </DialogContent>
      </Dialog>

      {/* PIX waiting modal */}
      <Dialog open={modal?.step === 'pix_waiting'} onOpenChange={(open) => !open && closeModal()}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Pagamento via PIX</DialogTitle>
            <DialogDescription>Escaneie o QR Code ou copie o código para pagar</DialogDescription>
          </DialogHeader>
          {modal?.step === 'pix_waiting' && (
            <div className="space-y-4 pt-2">
              {modal.purchase.payment?.pix_qr_code_base64 && (
                <div className="flex justify-center">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={`data:image/png;base64,${modal.purchase.payment.pix_qr_code_base64}`}
                    alt="QR Code PIX"
                    className="h-48 w-48"
                  />
                </div>
              )}
              {modal.purchase.payment?.pix_qr_code && (
                <div className="space-y-2">
                  <p className="text-xs font-medium text-muted-foreground">Código copia e cola</p>
                  <div
                    className="cursor-pointer rounded-md bg-muted p-3 text-xs break-all font-mono text-foreground border hover:bg-muted transition-colors"
                    onClick={() =>
                      navigator.clipboard.writeText(modal.purchase.payment?.pix_qr_code ?? '')
                    }
                  >
                    {modal.purchase.payment.pix_qr_code}
                  </div>
                  <p className="text-xs text-muted-foreground">Clique para copiar</p>
                </div>
              )}
              <p className="text-center text-sm text-muted-foreground">
                Aguardando confirmação do pagamento...
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
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <div>
              <DialogTitle className="text-lg font-bold text-green-600">
                Pacote ativado!
              </DialogTitle>
              <DialogDescription className="mt-1">
                {modal?.step === 'approved' ? modal.pkg.name : ''} já está disponível para uso.
              </DialogDescription>
            </div>
            <div className="flex w-full gap-3">
              <Button variant="outline" className="flex-1" onClick={closeModal}>
                Fechar
              </Button>
              <Button
                className="flex-1"
                onClick={() => router.push(`/${slug}/minha-conta/pacotes`)}
              >
                Ver meus pacotes
              </Button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
    </section>
  )
}
