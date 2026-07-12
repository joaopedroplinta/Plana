'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import { useParams } from 'next/navigation'
import { isAxiosError } from 'axios'
import { Calendar, Check, CheckCheck, UserX, X } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { appointmentsService } from '@/services/appointments'
import type { ApiError, Appointment } from '@/types/index'
import { formatPrice, formatTime } from '@/lib/format'

type StatusFilter = '' | 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show'

const STATUS_FILTERS: Array<{ value: StatusFilter; label: string }> = [
  { value: '', label: 'Todos' },
  { value: 'pending', label: 'Pendentes' },
  { value: 'confirmed', label: 'Confirmados' },
  { value: 'completed', label: 'Concluídos' },
  { value: 'cancelled', label: 'Cancelados' },
  { value: 'no_show', label: 'Faltas' },
]

const STATUS_BADGE: Record<string, { label: string; className: string }> = {
  pending: { label: 'Pendente', className: 'bg-amber-100 dark:bg-amber-500/15 text-amber-800 dark:text-amber-300' },
  confirmed: { label: 'Confirmado', className: 'bg-blue-100 dark:bg-blue-500/15 text-blue-800 dark:text-blue-300' },
  completed: { label: 'Concluído', className: 'bg-green-100 dark:bg-green-500/15 text-green-800 dark:text-green-300' },
  cancelled: { label: 'Cancelado', className: 'bg-muted text-muted-foreground' },
  no_show: { label: 'Não compareceu', className: 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-400' },
}

function StatusBadge({ status }: { status: string }) {
  const badge = STATUS_BADGE[status] ?? { label: status, className: 'bg-muted text-muted-foreground' }
  return (
    <Badge variant="secondary" className={`text-xs font-medium ${badge.className}`}>
      {badge.label}
    </Badge>
  )
}

export default function SchedulePage() {
  const params = useParams()
  const slug = typeof params.slug === 'string' ? params.slug : ''

  const [date, setDate] = useState('')
  const [status, setStatus] = useState<StatusFilter>('')
  const [page, setPage] = useState(1)
  const [appointments, setAppointments] = useState<Appointment[]>([])
  const [lastPage, setLastPage] = useState(1)
  const [total, setTotal] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [actingId, setActingId] = useState<string | null>(null)

  // Troca rápida de filtro (ex: preencher a data) pode disparar uma nova busca
  // antes da anterior responder. Sem isso, se a resposta mais antiga chegar
  // depois da mais nova (comum: payload maior demora mais pra parsear), ela
  // sobrescreve a lista com dados desatualizados — guardamos um "número da
  // vez" e ignoramos qualquer resposta que não seja da requisição mais recente.
  const latestRequestId = useRef(0)

  const loadAppointments = useCallback(() => {
    if (!slug) return

    const requestId = ++latestRequestId.current
    const query: Record<string, string> = { page: String(page) }
    if (date) query.date = date
    if (status) query.status = status

    appointmentsService
      .list(slug, query)
      .then((res) => {
        if (requestId !== latestRequestId.current) return
        setAppointments(res.data.data)
        setLastPage(res.data.meta.last_page)
        setTotal(res.data.meta.total)
        setError('')
      })
      .catch(() => {
        if (requestId !== latestRequestId.current) return
        setError('Erro ao carregar agendamentos. Tente novamente.')
      })
      .finally(() => {
        if (requestId !== latestRequestId.current) return
        setIsLoading(false)
      })
  }, [slug, date, status, page])

  useEffect(() => {
    loadAppointments()
  }, [loadAppointments])

  async function handleAction(
    appointment: Appointment,
    action: 'confirm' | 'cancel' | 'complete' | 'noShow',
  ) {
    setActingId(appointment.id)
    setError('')
    try {
      await appointmentsService[action](slug, appointment.id)
      loadAppointments()
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        setError(apiError?.message ?? 'Erro ao atualizar agendamento.')
      } else {
        setError('Erro inesperado. Tente novamente.')
      }
    } finally {
      setActingId(null)
    }
  }

  const groupedByDay = appointments.reduce<Record<string, Appointment[]>>((acc, appt) => {
    const day = new Intl.DateTimeFormat('pt-BR', {
      weekday: 'long',
      day: '2-digit',
      month: 'long',
      timeZone: 'UTC',
    }).format(new Date(appt.starts_at))
    acc[day] = acc[day] ? [...acc[day], appt] : [appt]
    return acc
  }, {})

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Agenda</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Visualize e gerencie os agendamentos do seu salão
        </p>
      </div>

      <div className="flex flex-wrap items-end gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="filter-date">Data</Label>
          <Input
            id="filter-date"
            type="date"
            value={date}
            onChange={(e) => {
              setDate(e.target.value)
              setPage(1)
            }}
            className="max-w-[180px]"
          />
        </div>
        {date && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setDate('')
              setPage(1)
            }}
          >
            Limpar data
          </Button>
        )}
        <div className="ml-auto flex flex-wrap gap-1.5">
          {STATUS_FILTERS.map((f) => (
            <button
              key={f.value}
              onClick={() => {
                setStatus(f.value)
                setPage(1)
              }}
              className={`rounded-full px-3 py-1.5 text-xs font-medium transition-colors ${
                status === f.value
                  ? 'bg-indigo-600 text-white'
                  : 'bg-muted text-muted-foreground hover:bg-muted'
              }`}
            >
              {f.label}
            </button>
          ))}
        </div>
      </div>

      {error && (
        <div className="rounded-lg bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-600 dark:text-red-400">{error}</div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center rounded-xl border bg-card py-24">
          <p className="text-sm text-muted-foreground animate-pulse">Carregando agendamentos...</p>
        </div>
      ) : appointments.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-xl border bg-card py-24 text-center">
          <div className="rounded-full bg-indigo-50 dark:bg-indigo-500/15 p-4">
            <Calendar className="h-10 w-10 text-indigo-400" />
          </div>
          <h2 className="mt-4 text-lg font-semibold text-foreground">
            Nenhum agendamento encontrado
          </h2>
          <p className="mt-2 max-w-sm text-sm text-muted-foreground">
            Quando seus clientes agendarem, os horários aparecerão aqui.
          </p>
        </div>
      ) : (
        <div className="space-y-6">
          {Object.entries(groupedByDay).map(([day, dayAppointments]) => (
            <div key={day}>
              <h2 className="mb-2 text-sm font-semibold capitalize text-muted-foreground">{day}</h2>
              <div className="overflow-hidden rounded-xl border bg-card">
                <ul className="divide-y">
                  {dayAppointments.map((appt) => {
                    const isActing = actingId === appt.id
                    return (
                      <li
                        key={appt.id}
                        className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                      >
                        <div className="w-28 shrink-0 text-sm font-semibold text-foreground">
                          {formatTime(appt.starts_at)} – {formatTime(appt.ends_at)}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate font-medium text-foreground">
                            {appt.service.name}
                            <span className="ml-2 text-sm font-normal text-muted-foreground">
                              com {appt.professional.name}
                            </span>
                          </p>
                          <p className="truncate text-sm text-muted-foreground">
                            Cliente: {appt.client?.name ?? '—'}
                            {appt.notes && (
                              <span className="ml-2 text-muted-foreground">· {appt.notes}</span>
                            )}
                          </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-3">
                          <span className="text-sm font-semibold text-indigo-600">
                            {formatPrice(appt.price)}
                          </span>
                          <StatusBadge status={appt.status} />
                          <div className="flex gap-1.5">
                            {appt.status === 'pending' && (
                              <Button
                                size="sm"
                                variant="outline"
                                disabled={isActing}
                                onClick={() => handleAction(appt, 'confirm')}
                                title="Confirmar"
                              >
                                <Check className="h-4 w-4" />
                                Confirmar
                              </Button>
                            )}
                            {(appt.status === 'pending' || appt.status === 'confirmed') && (
                              <>
                                <Button
                                  size="sm"
                                  variant="outline"
                                  disabled={isActing}
                                  onClick={() => handleAction(appt, 'complete')}
                                  title="Concluir"
                                >
                                  <CheckCheck className="h-4 w-4" />
                                  Concluir
                                </Button>
                                {new Date(appt.starts_at) < new Date() && (
                                  <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={isActing}
                                    onClick={() => handleAction(appt, 'noShow')}
                                    className="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40"
                                    title="Cliente não compareceu"
                                  >
                                    <UserX className="h-4 w-4" />
                                    Falta
                                  </Button>
                                )}
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  disabled={isActing}
                                  onClick={() => handleAction(appt, 'cancel')}
                                  className="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 hover:text-red-700 dark:hover:text-red-400"
                                  title="Cancelar"
                                >
                                  <X className="h-4 w-4" />
                                </Button>
                              </>
                            )}
                          </div>
                        </div>
                      </li>
                    )
                  })}
                </ul>
              </div>
            </div>
          ))}

          {lastPage > 1 && (
            <div className="flex items-center justify-between">
              <p className="text-sm text-muted-foreground">
                {total} agendamento{total === 1 ? '' : 's'}
              </p>
              <div className="flex gap-2">
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page <= 1}
                  onClick={() => setPage((p) => p - 1)}
                >
                  Anterior
                </Button>
                <span className="flex items-center px-2 text-sm text-muted-foreground">
                  {page} / {lastPage}
                </span>
                <Button
                  variant="outline"
                  size="sm"
                  disabled={page >= lastPage}
                  onClick={() => setPage((p) => p + 1)}
                >
                  Próxima
                </Button>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
