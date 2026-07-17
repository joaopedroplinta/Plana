'use client'

import { Suspense, useEffect, useRef, useState } from 'react'
import { useParams, useSearchParams } from 'next/navigation'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { tenantsService } from '@/services/tenants'
import { businessHoursService } from '@/services/businessHours'
import type { DepositType } from '@/types/index'
import { getSafeErrorMessage } from '@/lib/api-error'
import { BookingLinkCard } from '@/components/shared/BookingLinkCard'
import { MercadoPagoConnectCard } from '@/components/shared/MercadoPagoConnectCard'
import { WeeklyHoursEditor, emptyWeek, type DayHours } from '@/components/shared/WeeklyHoursEditor'
import { LandingCustomizer } from '@/components/shared/LandingCustomizer'

function MercadoPagoOAuthToast() {
  const searchParams = useSearchParams()
  const handled = useRef(false)

  useEffect(() => {
    if (handled.current) return
    const mp = searchParams.get('mp')
    if (mp !== 'connected' && mp !== 'error') return
    handled.current = true

    if (mp === 'connected') {
      toast.success('Conta MercadoPago conectada com sucesso!')
    } else {
      toast.error('Não foi possível conectar sua conta MercadoPago. Tente novamente.')
    }

    // Limpa o query param sem recarregar a página nem adicionar histórico.
    const params = new URLSearchParams(searchParams.toString())
    params.delete('mp')
    const query = params.toString()
    window.history.replaceState(null, '', query ? `?${query}` : window.location.pathname)
  }, [searchParams])

  return null
}

/** Rótulos do Select de sinal (Base UI exibe o texto selecionado a partir daqui). */
const SALON_DEPOSIT_LABELS: Record<DepositType, string> = {
  none: 'Sem sinal (valor cheio)',
  fixed: 'Valor fixo (R$)',
  percentage: 'Percentual (%)',
}

interface FormState {
  name: string
  description: string
  phone: string
  whatsapp: string
  address: string
  instagram: string
}

const EMPTY_FORM: FormState = {
  name: '',
  description: '',
  phone: '',
  whatsapp: '',
  address: '',
  instagram: '',
}

export default function SalonSettingsPage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  const [form, setForm] = useState<FormState>(EMPTY_FORM)
  const [isLoading, setIsLoading] = useState(true)
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')

  // Horário de funcionamento do salão.
  const [week, setWeek] = useState<DayHours[]>(emptyWeek)
  const [isSavingHours, setIsSavingHours] = useState(false)
  const [hoursError, setHoursError] = useState('')
  const [hoursSuccess, setHoursSuccess] = useState('')

  // Sinal padrão do salão (aplicado a serviços sem override próprio).
  const [depositType, setDepositType] = useState<DepositType>('none')
  const [depositValue, setDepositValue] = useState('')
  const [isSavingDeposit, setIsSavingDeposit] = useState(false)
  const [depositError, setDepositError] = useState('')
  const [depositSuccess, setDepositSuccess] = useState('')

  useEffect(() => {
    if (!slug) return
    tenantsService
      .show(slug)
      .then((res) => {
        const t = res.data.data
        setForm({
          name: t.name ?? '',
          description: t.description ?? '',
          phone: t.phone ?? '',
          whatsapp: t.whatsapp ?? '',
          address: t.address ?? '',
          instagram: t.instagram ?? '',
        })
        setDepositType(t.deposit_type ?? 'none')
        if (t.deposit_value != null) {
          setDepositValue(
            t.deposit_type === 'fixed'
              ? (t.deposit_value / 100).toFixed(2).replace('.', ',')
              : String(t.deposit_value),
          )
        }
      })
      .catch(() => setError('Erro ao carregar os dados do negócio.'))
      .finally(() => setIsLoading(false))
  }, [slug])

  useEffect(() => {
    if (!slug) return
    businessHoursService
      .list(slug)
      .then((res) => {
        const configured = res.data.data
        if (configured.length === 0) return
        setWeek(
          emptyWeek().map((day) => {
            const match = configured.find((h) => h.day_of_week === day.day_of_week)
            if (!match || !match.is_open || !match.open_time || !match.close_time) {
              return { ...day, enabled: false }
            }
            return { ...day, enabled: true, start: match.open_time, end: match.close_time }
          }),
        )
      })
      .catch(() => {
        // Silencioso: o editor apenas fica com a semana em branco.
      })
  }, [slug])

  async function handleSaveHours() {
    setHoursError('')
    setHoursSuccess('')
    setIsSavingHours(true)
    try {
      await businessHoursService.sync(
        slug,
        week.map((d) => ({
          day_of_week: d.day_of_week,
          is_open: d.enabled,
          open_time: d.enabled ? d.start : null,
          close_time: d.enabled ? d.end : null,
        })),
      )
      setHoursSuccess('Horário de funcionamento salvo! A agenda dos profissionais respeita esses horários.')
    } catch (err) {
      setHoursError(getSafeErrorMessage(err, 'Erro ao salvar o horário. Tente novamente.'))
    } finally {
      setIsSavingHours(false)
    }
  }

  function setField(field: keyof FormState, value: string) {
    setForm((f) => ({ ...f, [field]: value }))
  }

  async function handleSaveDeposit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setDepositError('')
    setDepositSuccess('')

    let value: number | null = null
    if (depositType !== 'none') {
      value =
        depositType === 'fixed'
          ? Math.round(parseFloat(depositValue.replace(',', '.')) * 100)
          : parseInt(depositValue, 10)

      if (value === null || isNaN(value) || value <= 0) {
        setDepositError('Informe um valor de sinal válido.')
        return
      }
      if (depositType === 'percentage' && value > 100) {
        setDepositError('O percentual do sinal não pode passar de 100%.')
        return
      }
    }

    setIsSavingDeposit(true)
    try {
      await tenantsService.updateSettings(slug, {
        deposit_type: depositType,
        deposit_value: value,
      })
      setDepositSuccess('Sinal padrão atualizado! Vale para os serviços sem configuração própria.')
    } catch (err) {
      setDepositError(getSafeErrorMessage(err, 'Erro ao salvar. Tente novamente.'))
    } finally {
      setIsSavingDeposit(false)
    }
  }

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setError('')
    setSuccess('')
    setIsSaving(true)
    try {
      await tenantsService.updateSettings(slug, {
        name: form.name.trim(),
        description: form.description.trim() || null,
        phone: form.phone.trim() || null,
        whatsapp: form.whatsapp.trim() || null,
        address: form.address.trim() || null,
        instagram: form.instagram.trim().replace(/^@/, '') || null,
      })
      setSuccess('Perfil do negócio atualizado! As mudanças já aparecem na sua página pública.')
    } catch (err) {
      setError(getSafeErrorMessage(err, 'Erro ao salvar. Tente novamente.'))
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="space-y-6">
      <Suspense fallback={null}>
        <MercadoPagoOAuthToast />
      </Suspense>

      <div>
        <h1 className="text-2xl font-bold text-foreground">Meu negócio</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Essas informações aparecem na página pública do seu negócio
        </p>
      </div>

      <div className="space-y-3">
        <div>
          <h2 className="text-lg font-semibold text-foreground">Link de agendamento</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Compartilhe este link com seus clientes para que eles agendem online.
          </p>
        </div>
        <BookingLinkCard slug={slug} />
      </div>

      <div className="space-y-3">
        <div>
          <h2 className="text-lg font-semibold text-foreground">Pagamentos</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Conecte sua conta MercadoPago para receber os pagamentos dos agendamentos.
          </p>
        </div>
        <MercadoPagoConnectCard slug={slug} />
      </div>

      <div className="space-y-3">
        <div>
          <h2 className="text-lg font-semibold text-foreground">Horário de funcionamento</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Defina os dias e horários que o estabelecimento abre. Nenhum profissional
            pode ser agendado fora do funcionamento do salão.
          </p>
        </div>
        <Card className="max-w-2xl p-6">
          <WeeklyHoursEditor value={week} onChange={setWeek} disabled={isSavingHours} offLabel="Fechado" />

          {hoursError && (
            <p className="mt-4 rounded-lg bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-600 dark:text-red-400">{hoursError}</p>
          )}
          {hoursSuccess && (
            <p className="mt-4 rounded-lg bg-green-50 dark:bg-green-950/40 px-3 py-2 text-sm text-green-700 dark:text-green-400">{hoursSuccess}</p>
          )}

          <Button className="mt-5" onClick={handleSaveHours} disabled={isSavingHours}>
            {isSavingHours ? 'Salvando...' : 'Salvar horário'}
          </Button>
        </Card>
      </div>

      <LandingCustomizer slug={slug} />

      {!isLoading && (
        <div className="space-y-3">
          <div>
            <h2 className="text-lg font-semibold text-foreground">Sinal na reserva</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Cobre um sinal para confirmar o agendamento; o restante o cliente paga presencialmente.
              Vale para todos os serviços, exceto os que tiverem valor próprio.
            </p>
          </div>
          <Card className="max-w-2xl p-6">
            <form onSubmit={handleSaveDeposit} className="space-y-5">
              <div className="grid gap-5 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label htmlFor="deposit-type">Tipo de sinal</Label>
                  <Select
                    items={SALON_DEPOSIT_LABELS}
                    value={depositType}
                    onValueChange={(v) => {
                      setDepositType(v as DepositType)
                      setDepositValue('')
                    }}
                    disabled={isSavingDeposit}
                  >
                    <SelectTrigger id="deposit-type" className="w-full">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">{SALON_DEPOSIT_LABELS.none}</SelectItem>
                      <SelectItem value="fixed">{SALON_DEPOSIT_LABELS.fixed}</SelectItem>
                      <SelectItem value="percentage">{SALON_DEPOSIT_LABELS.percentage}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                {depositType !== 'none' && (
                  <div className="space-y-1.5">
                    <Label htmlFor="deposit-value">
                      {depositType === 'fixed' ? 'Valor do sinal (R$)' : 'Percentual do sinal (%)'}
                    </Label>
                    <Input
                      id="deposit-value"
                      required
                      placeholder={depositType === 'fixed' ? '30,00' : '20'}
                      value={depositValue}
                      onChange={(e) => setDepositValue(e.target.value)}
                      disabled={isSavingDeposit}
                    />
                  </div>
                )}
              </div>

              {depositError && (
                <p className="rounded-lg bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-600 dark:text-red-400">{depositError}</p>
              )}
              {depositSuccess && (
                <p className="rounded-lg bg-green-50 dark:bg-green-950/40 px-3 py-2 text-sm text-green-700 dark:text-green-400">{depositSuccess}</p>
              )}

              <Button type="submit" disabled={isSavingDeposit}>
                {isSavingDeposit ? 'Salvando...' : 'Salvar sinal padrão'}
              </Button>
            </form>
          </Card>
        </div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center rounded-xl border bg-card py-24">
          <p className="text-sm text-muted-foreground animate-pulse">Carregando...</p>
        </div>
      ) : (
        <Card className="max-w-2xl p-6">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-1.5">
              <Label htmlFor="salon-name">Nome do negócio</Label>
              <Input
                id="salon-name"
                required
                value={form.name}
                onChange={(e) => setField('name', e.target.value)}
                disabled={isSaving}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="salon-description">Descrição</Label>
              <Textarea
                id="salon-description"
                rows={3}
                placeholder="Conte aos clientes o que torna seu negócio especial..."
                value={form.description}
                onChange={(e) => setField('description', e.target.value)}
                disabled={isSaving}
              />
            </div>

            <div className="grid gap-5 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label htmlFor="salon-phone">Telefone</Label>
                <Input
                  id="salon-phone"
                  placeholder="(42) 3333-0000"
                  value={form.phone}
                  onChange={(e) => setField('phone', e.target.value)}
                  disabled={isSaving}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="salon-whatsapp">WhatsApp</Label>
                <Input
                  id="salon-whatsapp"
                  placeholder="5542999990000"
                  value={form.whatsapp}
                  onChange={(e) => setField('whatsapp', e.target.value)}
                  disabled={isSaving}
                />
              </div>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="salon-address">Endereço</Label>
              <Input
                id="salon-address"
                placeholder="Rua das Flores, 123 — Centro"
                value={form.address}
                onChange={(e) => setField('address', e.target.value)}
                disabled={isSaving}
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="salon-instagram">Instagram</Label>
              <Input
                id="salon-instagram"
                placeholder="meunegocio (sem @)"
                value={form.instagram}
                onChange={(e) => setField('instagram', e.target.value)}
                disabled={isSaving}
              />
            </div>

            {error && (
              <p className="rounded-lg bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-600 dark:text-red-400">{error}</p>
            )}
            {success && (
              <p className="rounded-lg bg-green-50 dark:bg-green-950/40 px-3 py-2 text-sm text-green-700 dark:text-green-400">
                {success}
              </p>
            )}

            <Button type="submit" disabled={isSaving}>
              {isSaving ? 'Salvando...' : 'Salvar alterações'}
            </Button>
          </form>
        </Card>
      )}
    </div>
  )
}
