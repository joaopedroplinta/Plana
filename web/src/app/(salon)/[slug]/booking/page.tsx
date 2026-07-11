'use client'

import { useState, useEffect } from 'react'
import { useParams, useRouter } from 'next/navigation'
import { isAxiosError } from 'axios'
import { ArrowLeft, CheckCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { useAuth } from '@/hooks/useAuth'
import { servicesService } from '@/services/services'
import { professionalsService } from '@/services/professionals'
import { appointmentsService } from '@/services/appointments'
import { packagePurchasesService } from '@/services/packagePurchases'
import { paymentsService } from '@/services/payments'
import type { Service, Professional, TimeSlot, ApiError, Payment, PackagePurchase } from '@/types/index'
import { formatPrice, formatDate, formatDuration } from '@/lib/format'

const TOTAL_STEPS = 5

const STEP_LABELS = [
  'Serviço',
  'Profissional',
  'Data',
  'Horário',
  'Confirmar',
]

function StepProgress({ current }: { current: number }) {
  return (
    <div className="mb-8">
      <div className="flex items-center justify-between">
        {STEP_LABELS.map((label, idx) => {
          const stepNum = idx + 1
          const isCompleted = stepNum < current
          const isActive = stepNum === current
          return (
            <div key={stepNum} className="flex flex-1 flex-col items-center">
              <div className="flex w-full items-center">
                {idx > 0 && (
                  <div
                    className={`h-0.5 flex-1 ${
                      isCompleted ? 'bg-indigo-600' : 'bg-gray-200'
                    }`}
                  />
                )}
                <div
                  className={`flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full text-xs font-semibold ${
                    isCompleted
                      ? 'bg-indigo-600 text-white'
                      : isActive
                        ? 'border-2 border-indigo-600 bg-white text-indigo-600'
                        : 'border-2 border-gray-200 bg-white text-gray-400'
                  }`}
                >
                  {isCompleted ? <CheckCircle className="h-4 w-4" /> : stepNum}
                </div>
                {idx < TOTAL_STEPS - 1 && (
                  <div
                    className={`h-0.5 flex-1 ${
                      isCompleted ? 'bg-indigo-600' : 'bg-gray-200'
                    }`}
                  />
                )}
              </div>
              <span
                className={`mt-1.5 text-xs font-medium ${
                  isActive ? 'text-indigo-600' : isCompleted ? 'text-gray-700' : 'text-gray-400'
                }`}
              >
                {label}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}

export default function BookingPage() {
  const params = useParams()
  const router = useRouter()
  const slug = typeof params.slug === 'string' ? params.slug : ''
  const { isLoading: authLoading, isAuthenticated } = useAuth()

  const [step, setStep] = useState(1)
  const [selectedService, setSelectedService] = useState<Service | null>(null)
  const [selectedProfessional, setSelectedProfessional] = useState<Professional | null>(null)
  const [selectedDate, setSelectedDate] = useState('')
  const [selectedSlot, setSelectedSlot] = useState<TimeSlot | null>(null)
  const [notes, setNotes] = useState('')
  const [slots, setSlots] = useState<TimeSlot[]>([])
  const [services, setServices] = useState<Service[]>([])
  const [professionals, setProfessionals] = useState<Professional[]>([])
  const [isLoading, setIsLoading] = useState(false)
  const [initialLoading, setInitialLoading] = useState(true)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)

  // Pacotes de sessão do cliente logado, usáveis como forma de pagamento
  const [packagePurchases, setPackagePurchases] = useState<PackagePurchase[]>([])
  const [selectedPackagePurchaseId, setSelectedPackagePurchaseId] = useState<string | null>(null)

  // Payment state
  const [appointmentId, setAppointmentId] = useState<string | null>(null)
  const [payment, setPayment] = useState<Payment | null>(null)
  const [paymentLoading, setPaymentLoading] = useState(false)
  const [paymentError, setPaymentError] = useState('')
  const [pixPaid, setPixPaid] = useState(false)

  const today = new Date().toISOString().split('T')[0]

  useEffect(() => {
    if (!slug) return

    Promise.all([
      servicesService.list(slug),
      professionalsService.list(slug),
    ])
      .then(([svcRes, proRes]) => {
        setServices(svcRes.data.data)
        setProfessionals(proRes.data.data)
      })
      .catch(() => {
        setError('Erro ao carregar dados do salão. Tente novamente.')
      })
      .finally(() => {
        setInitialLoading(false)
      })
  }, [slug])

  useEffect(() => {
    if (!slug || !isAuthenticated) return

    packagePurchasesService
      .list(slug)
      .then((res) => {
        setPackagePurchases(
          res.data.data.filter((p) => p.status === 'active' && p.sessions_remaining > 0),
        )
      })
      .catch(() => {})
  }, [slug, isAuthenticated])

  const usablePackagePurchases = packagePurchases.filter((purchase) =>
    purchase.service_package.services.some((s) => s.id === selectedService?.id),
  )

  // PIX polling
  useEffect(() => {
    if (!payment || payment.method !== 'pix' || payment.status === 'approved' || pixPaid) return

    const interval = setInterval(async () => {
      try {
        const res = await paymentsService.status(slug, payment.id)
        if (res.data.data.status === 'approved') {
          setPixPaid(true)
          setPayment(res.data.data)
          clearInterval(interval)
        }
      } catch {
        // silenciar erros de polling
      }
    }, 5000)

    return () => clearInterval(interval)
  }, [payment, slug, pixPaid])

  async function loadAvailability() {
    if (!selectedProfessional || !selectedService || !selectedDate) return
    setIsLoading(true)
    setError('')
    try {
      const res = await appointmentsService.availability(
        slug,
        selectedProfessional.id,
        selectedService.id,
        selectedDate,
      )
      setSlots(res.data.data)
      setStep(4)
    } catch {
      setError('Erro ao buscar horários disponíveis. Tente novamente.')
    } finally {
      setIsLoading(false)
    }
  }

  async function handleConfirm() {
    if (!selectedService || !selectedProfessional || !selectedDate || !selectedSlot) return

    // Agendar exige conta — envia para o login e volta direto para cá.
    if (!authLoading && !isAuthenticated) {
      router.push(`/login?redirect=/${slug}/booking`)
      return
    }

    setIsLoading(true)
    setError('')
    try {
      const result = await appointmentsService.create(slug, {
        professional_id: selectedProfessional.id,
        service_id: selectedService.id,
        starts_at: `${selectedDate}T${selectedSlot.starts_at}:00`,
        notes: notes.trim() || undefined,
        package_purchase_id: selectedPackagePurchaseId ?? undefined,
      })
      setAppointmentId(result.data.data.id)

      // Pago com pacote: sessão já foi consumida, nada a cobrar.
      if (result.data.data.package_purchase_id) {
        setSuccess(true)
        return
      }

      setSuccess(false)
      setStep(6)
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        setError(apiError?.message ?? 'Erro ao confirmar agendamento.')
      } else {
        setError('Erro inesperado. Tente novamente.')
      }
    } finally {
      setIsLoading(false)
    }
  }

  async function handlePayPix() {
    if (!appointmentId) return
    setPaymentLoading(true)
    setPaymentError('')
    try {
      const res = await paymentsService.create(slug, appointmentId, 'pix')
      setPayment(res.data.data)
      setStep(7)
    } catch {
      setPaymentError('Erro ao gerar PIX. Tente novamente.')
    } finally {
      setPaymentLoading(false)
    }
  }

  async function handlePayCard() {
    if (!appointmentId) return
    setPaymentLoading(true)
    setPaymentError('')
    try {
      const res = await paymentsService.create(slug, appointmentId, 'credit_card')
      const url = res.data.data.preference_url
      if (url) window.location.href = url
    } catch {
      setPaymentError('Erro ao iniciar pagamento. Tente novamente.')
    } finally {
      setPaymentLoading(false)
    }
  }

  function goBack() {
    setError('')
    setStep((s) => s - 1)
  }

  if (initialLoading) {
    return (
      <div className="flex min-h-[60vh] items-center justify-center">
        <p className="text-sm text-gray-400">Carregando...</p>
      </div>
    )
  }

  if (success) {
    return (
      <div className="flex min-h-[60vh] flex-col items-center justify-center px-4 text-center">
        <div className="rounded-full bg-green-100 p-4 text-green-600">
          <CheckCircle className="h-10 w-10" />
        </div>
        <h2 className="mt-4 text-2xl font-bold text-gray-900">Agendamento realizado!</h2>
        <p className="mt-2 text-sm text-gray-500">
          Seu horário foi reservado com sucesso. Aguarde a confirmação do salão.
        </p>
        <div className="mt-6 space-y-2 rounded-xl border bg-gray-50 px-6 py-4 text-left text-sm">
          <p>
            <span className="font-medium text-gray-700">Serviço:</span>{' '}
            {selectedService?.name}
          </p>
          <p>
            <span className="font-medium text-gray-700">Profissional:</span>{' '}
            {selectedProfessional?.name}
          </p>
          <p>
            <span className="font-medium text-gray-700">Data:</span>{' '}
            {selectedDate ? formatDate(selectedDate) : '—'}
          </p>
          <p>
            <span className="font-medium text-gray-700">Horário:</span>{' '}
            {selectedSlot
              ? `${selectedSlot.starts_at} – ${selectedSlot.ends_at}`
              : '—'}
          </p>
        </div>
        <div className="mt-6 flex gap-3">
          <Button onClick={() => router.push(`/${slug}/minha-conta`)}>
            Ver meus agendamentos
          </Button>
          <Button variant="outline" onClick={() => router.push(`/${slug}`)}>
            Voltar ao início
          </Button>
        </div>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      {/* Show progress bar only for steps 1–5 */}
      {step <= 5 && <StepProgress current={step} />}

      <div className="mb-4 flex items-center gap-3">
        {step > 1 && step <= 5 && (
          <button
            onClick={goBack}
            className="flex items-center gap-1 text-sm text-gray-500 hover:text-gray-800 transition-colors"
          >
            <ArrowLeft className="h-4 w-4" />
            Voltar
          </button>
        )}
        {step <= 5 && (
          <button
            onClick={() => router.push(`/${slug}`)}
            className="ml-auto text-sm text-gray-400 hover:text-gray-600 transition-colors"
          >
            Cancelar
          </button>
        )}
      </div>

      {error && (
        <div className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">
          {error}
        </div>
      )}

      {/* Step 1: Selecionar Serviço */}
      {step === 1 && (
        <div className="space-y-4">
          <div>
            <h2 className="text-xl font-bold text-gray-900">Escolha um serviço</h2>
            <p className="mt-1 text-sm text-gray-500">
              Selecione o serviço que deseja agendar
            </p>
          </div>
          {services.length === 0 ? (
            <p className="py-8 text-center text-sm text-gray-400">
              Nenhum serviço disponível no momento.
            </p>
          ) : (
            <div className="grid gap-3">
              {services
                .filter((s) => s.active)
                .map((service) => (
                  <Card
                    key={service.id}
                    data-testid="service-option"
                    className={`cursor-pointer p-4 transition-all hover:border-indigo-400 hover:shadow-sm ${
                      selectedService?.id === service.id
                        ? 'border-indigo-500 ring-2 ring-indigo-200'
                        : ''
                    }`}
                    onClick={() => {
                      setSelectedService(service)
                      setSelectedPackagePurchaseId(null)
                      setStep(2)
                    }}
                  >
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex-1">
                        <p className="font-semibold text-gray-900">{service.name}</p>
                        {service.description && (
                          <p className="mt-0.5 text-sm text-gray-500 line-clamp-2">
                            {service.description}
                          </p>
                        )}
                        <div className="mt-2 flex items-center gap-2">
                          <Badge variant="secondary" className="text-xs">
                            {formatDuration(service.duration_minutes)}
                          </Badge>
                        </div>
                      </div>
                      <p className="whitespace-nowrap text-lg font-bold text-indigo-600">
                        {formatPrice(service.price)}
                      </p>
                    </div>
                  </Card>
                ))}
            </div>
          )}
        </div>
      )}

      {/* Step 2: Selecionar Profissional */}
      {step === 2 && (
        <div className="space-y-4">
          <div>
            <h2 className="text-xl font-bold text-gray-900">Escolha um profissional</h2>
            <p className="mt-1 text-sm text-gray-500">
              Selecione com quem deseja ser atendido
            </p>
          </div>
          {professionals.length === 0 ? (
            <p className="py-8 text-center text-sm text-gray-400">
              Nenhum profissional disponível no momento.
            </p>
          ) : (
            <div className="grid gap-3">
              {professionals
                .filter((p) => p.active)
                .map((professional) => (
                  <Card
                    key={professional.id}
                    data-testid="professional-option"
                    className={`cursor-pointer p-4 transition-all hover:border-indigo-400 hover:shadow-sm ${
                      selectedProfessional?.id === professional.id
                        ? 'border-indigo-500 ring-2 ring-indigo-200'
                        : ''
                    }`}
                    onClick={() => {
                      setSelectedProfessional(professional)
                      setStep(3)
                    }}
                  >
                    <div className="flex items-center gap-3">
                      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-600">
                        {professional.name.charAt(0).toUpperCase()}
                      </div>
                      <div className="flex-1">
                        <p className="font-semibold text-gray-900">{professional.name}</p>
                        {professional.bio && (
                          <p className="mt-0.5 text-sm text-gray-500 line-clamp-2">
                            {professional.bio}
                          </p>
                        )}
                      </div>
                    </div>
                  </Card>
                ))}
            </div>
          )}
        </div>
      )}

      {/* Step 3: Selecionar Data */}
      {step === 3 && (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-bold text-gray-900">Escolha uma data</h2>
            <p className="mt-1 text-sm text-gray-500">
              Selecione o dia em que deseja ser atendido
            </p>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="booking-date">Data</Label>
            <Input
              id="booking-date"
              type="date"
              min={today}
              value={selectedDate}
              onChange={(e) => setSelectedDate(e.target.value)}
              className="max-w-xs"
            />
          </div>
          <Button
            onClick={loadAvailability}
            disabled={!selectedDate || isLoading}
          >
            {isLoading ? 'Buscando horários...' : 'Ver horários disponíveis'}
          </Button>
        </div>
      )}

      {/* Step 4: Selecionar Horário */}
      {step === 4 && (
        <div className="space-y-4">
          <div>
            <h2 className="text-xl font-bold text-gray-900">Escolha um horário</h2>
            <p className="mt-1 text-sm text-gray-500">
              Horários disponíveis para {selectedDate ? formatDate(selectedDate) : ''}
            </p>
          </div>
          {slots.length === 0 ? (
            <div className="rounded-xl border bg-gray-50 py-12 text-center">
              <p className="text-sm text-gray-500">
                Nenhum horário disponível nesta data.
              </p>
              <button
                onClick={() => setStep(3)}
                className="mt-2 text-sm text-indigo-600 hover:underline"
              >
                Escolher outra data
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {slots.map((slot) => {
                const key = `${slot.starts_at}-${slot.ends_at}`
                const isSelected =
                  selectedSlot?.starts_at === slot.starts_at &&
                  selectedSlot?.ends_at === slot.ends_at
                return (
                  <button
                    key={key}
                    onClick={() => {
                      setSelectedSlot(slot)
                      setStep(5)
                    }}
                    className={`rounded-lg border px-3 py-2.5 text-sm font-medium transition-all ${
                      isSelected
                        ? 'border-indigo-500 bg-indigo-600 text-white'
                        : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-400 hover:bg-indigo-50'
                    }`}
                  >
                    {slot.starts_at} – {slot.ends_at}
                  </button>
                )
              })}
            </div>
          )}
        </div>
      )}

      {/* Step 5: Confirmar e Agendar */}
      {step === 5 && (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-bold text-gray-900">Confirmar agendamento</h2>
            <p className="mt-1 text-sm text-gray-500">
              Revise os detalhes antes de confirmar
            </p>
          </div>

          <div className="rounded-xl border bg-gray-50 p-5 space-y-3">
            <div className="flex items-center justify-between border-b border-gray-200 pb-3">
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-gray-400">
                  Serviço
                </p>
                <p className="mt-0.5 font-semibold text-gray-900">{selectedService?.name}</p>
                {selectedService && (
                  <p className="text-xs text-gray-500">
                    {formatDuration(selectedService.duration_minutes)}
                  </p>
                )}
              </div>
              <p className="text-lg font-bold text-indigo-600">
                {selectedPackagePurchaseId
                  ? 'Incluso no pacote'
                  : selectedService
                    ? formatPrice(selectedService.price)
                    : ''}
              </p>
            </div>

            <div className="grid grid-cols-2 gap-4 pt-1">
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-gray-400">
                  Profissional
                </p>
                <p className="mt-0.5 font-medium text-gray-900">{selectedProfessional?.name}</p>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-gray-400">
                  Data
                </p>
                <p className="mt-0.5 font-medium text-gray-900">
                  {selectedDate ? formatDate(selectedDate) : '—'}
                </p>
              </div>
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-gray-400">
                  Horário
                </p>
                <p className="mt-0.5 font-medium text-gray-900">
                  {selectedSlot
                    ? `${selectedSlot.starts_at} – ${selectedSlot.ends_at}`
                    : '—'}
                </p>
              </div>
            </div>
          </div>

          {usablePackagePurchases.length > 0 && (
            <div className="space-y-1.5">
              <Label>Forma de pagamento</Label>
              <div className="grid gap-2">
                <button
                  type="button"
                  onClick={() => setSelectedPackagePurchaseId(null)}
                  className={`rounded-lg border px-4 py-3 text-left text-sm transition-all ${
                    selectedPackagePurchaseId === null
                      ? 'border-indigo-500 bg-indigo-50'
                      : 'border-gray-200 hover:border-indigo-300'
                  }`}
                >
                  <p className="font-medium text-gray-900">Pagar agora</p>
                  <p className="text-xs text-gray-500">PIX, cartão ou no local</p>
                </button>
                {usablePackagePurchases.map((purchase) => (
                  <button
                    key={purchase.id}
                    type="button"
                    onClick={() => setSelectedPackagePurchaseId(purchase.id)}
                    className={`rounded-lg border px-4 py-3 text-left text-sm transition-all ${
                      selectedPackagePurchaseId === purchase.id
                        ? 'border-indigo-500 bg-indigo-50'
                        : 'border-gray-200 hover:border-indigo-300'
                    }`}
                  >
                    <p className="font-medium text-gray-900">
                      Usar pacote: {purchase.service_package.name}
                    </p>
                    <p className="text-xs text-gray-500">
                      {purchase.sessions_remaining} sessões restantes
                    </p>
                  </button>
                ))}
              </div>
            </div>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="booking-notes">Observações (opcional)</Label>
            <Textarea
              id="booking-notes"
              placeholder="Ex: Tenho alergia a determinado produto..."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              disabled={isLoading}
            />
          </div>

          <Button
            className="w-full"
            onClick={handleConfirm}
            disabled={isLoading}
          >
            {isLoading ? 'Confirmando...' : 'Confirmar Agendamento'}
          </Button>
        </div>
      )}

      {/* Step 6: Escolher método de pagamento */}
      {step === 6 && (
        <div className="space-y-6">
          <div>
            <h2 className="text-xl font-bold text-gray-900">Pagamento</h2>
            <p className="mt-1 text-sm text-gray-500">
              Horário reservado! Escolha como deseja pagar.
            </p>
          </div>
          <p className="text-gray-600">
            {selectedService?.name} —{' '}
            <span className="font-semibold text-indigo-600">
              {formatPrice(selectedService?.price ?? 0)}
            </span>
          </p>
          {paymentError && (
            <p className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{paymentError}</p>
          )}
          <div className="grid grid-cols-2 gap-4">
            <button
              onClick={handlePayPix}
              disabled={paymentLoading}
              className="border-2 border-green-500 rounded-lg p-6 text-center hover:bg-green-50 transition-colors disabled:opacity-50"
            >
              <div className="text-3xl mb-2">PIX</div>
              <div className="font-semibold text-green-700">PIX</div>
              <div className="text-sm text-gray-500">Pagamento imediato</div>
            </button>
            <button
              onClick={handlePayCard}
              disabled={paymentLoading}
              className="border-2 border-blue-500 rounded-lg p-6 text-center hover:bg-blue-50 transition-colors disabled:opacity-50"
            >
              <div className="text-3xl mb-2">💳</div>
              <div className="font-semibold text-blue-700">Cartão de crédito</div>
              <div className="text-sm text-gray-500">Redirecionado para o MercadoPago</div>
            </button>
          </div>
          <button
            onClick={() => setSuccess(true)}
            disabled={paymentLoading}
            className="w-full rounded-lg border border-gray-200 px-4 py-3 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors disabled:opacity-50"
          >
            Pagar no local — finalizar sem pagamento online
          </button>
          {paymentLoading && <p className="text-center text-gray-400 animate-pulse">Aguarde...</p>}
        </div>
      )}

      {/* Step 7: QR Code PIX */}
      {step === 7 && payment && (
        <div className="space-y-6 text-center">
          {pixPaid ? (
            <div className="space-y-4">
              <div className="text-6xl flex justify-center">
                <CheckCircle className="h-16 w-16 text-green-500" />
              </div>
              <h2 className="text-2xl font-bold text-green-600">Pagamento confirmado!</h2>
              <p className="text-gray-500">Seu agendamento está garantido.</p>
              <div className="flex justify-center gap-3">
                <a
                  href={`/${slug}/minha-conta`}
                  className="inline-block mt-4 px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"
                >
                  Ver meus agendamentos
                </a>
                <a
                  href={`/${slug}`}
                  className="inline-block mt-4 px-6 py-2 border border-gray-200 text-gray-600 rounded-lg hover:bg-gray-50"
                >
                  Voltar ao início
                </a>
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              <h2 className="text-xl font-semibold">Pague via PIX</h2>
              <p className="text-gray-500">Escaneie o QR code ou copie o código abaixo</p>
              {payment.pix_qr_code_base64 && (
                <div className="flex justify-center">
                  <img
                    src={`data:image/png;base64,${payment.pix_qr_code_base64}`}
                    alt="QR Code PIX"
                    className="w-48 h-48 border rounded-lg"
                  />
                </div>
              )}
              {payment.pix_qr_code && (
                <div className="space-y-2">
                  <p className="text-sm font-medium text-gray-600">Código PIX (copia e cola):</p>
                  <div className="flex items-center gap-2">
                    <input
                      readOnly
                      value={payment.pix_qr_code}
                      className="flex-1 text-xs p-2 border rounded bg-gray-50 font-mono truncate"
                    />
                    <button
                      onClick={() => navigator.clipboard.writeText(payment.pix_qr_code ?? '')}
                      className="px-3 py-2 bg-gray-100 border rounded hover:bg-gray-200 text-sm"
                    >
                      Copiar
                    </button>
                  </div>
                </div>
              )}
              <p className="text-sm text-gray-400 animate-pulse">Aguardando confirmação do pagamento...</p>
              <p className="text-xs text-gray-300">Verificando a cada 5 segundos</p>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
