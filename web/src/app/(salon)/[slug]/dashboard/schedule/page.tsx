'use client'

import { useCallback, useEffect, useState } from 'react'
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
  pending: { label: 'Pendente', className: 'bg-amber-100 text-amber-800' },
  confirmed: { label: 'Confirmado', className: 'bg-blue-100 text-blue-800' },
  completed: { label: 'Concluído', className: 'bg-green-100 text-green-800' },
  cancelled: { label: 'Cancelado', className: 'bg-gray-100 text-gray-500' },
  no_show: { label: 'Não compareceu', className: 'bg-red-100 text-red-700' },
}

function StatusBadge({ status }: { status: string }) {
  const badge = STATUS_BADGE[status] ?? { label: status, className: 'bg-gray-100 text-gray-600' }
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

  const loadAppointments = useCallback(() => {
    if (!slug) return

    const query: Record<string, string> = { page: String(page) }
    if (date) query.date = date
    if (status) query.status = status

    appointmentsService
      .list(slug, query)
      .then((res) => {
        setAppointments(res.data.data)
        setLastPage(res.data.meta.last_page)
        setTotal(res.data.meta.total)
        setError('')
      })
      .catch(() => setError('Erro ao carregar agendamentos. Tente novamente.'))
      .finally(() => setIsLoading(false))
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
    }).format(new Date(appt.starts_at))
    acc[day] = acc[day] ? [...acc[day], appt] : [appt]
    return acc
  }, {})

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Agenda</h1>
        <p className="mt-1 text-sm text-gray-500">
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
                  : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              {f.label}
            </button>
          ))}
        </div>
      </div>

      {error && (
        <div className="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{error}</div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center rounded-xl border bg-white py-24">
          <p className="text-sm text-gray-400 animate-pulse">Carregando agendamentos...</p>
        </div>
      ) : appointments.length === 0 ? (
        <div className="flex flex-col items-center justify-center rounded-xl border bg-white py-24 text-center">
          <div className="rounded-full bg-indigo-50 p-4">
            <Calendar className="h-10 w-10 text-indigo-400" />
          </div>
          <h2 className="mt-4 text-lg font-semibold text-gray-700">
            Nenhum agendamento encontrado
          </h2>
          <p className="mt-2 max-w-sm text-sm text-gray-400">
            Quando seus clientes agendarem, os horários aparecerão aqui.
          </p>
        </div>
      ) : (
        <div className="space-y-6">
          {Object.entries(groupedByDay).map(([day, dayAppointments]) => (
            <div key={day}>
              <h2 className="mb-2 text-sm font-semibold capitalize text-gray-500">{day}</h2>
              <div className="overflow-hidden rounded-xl border bg-white">
                <ul className="divide-y">
                  {dayAppointments.map((appt) => {
                    const isActing = actingId === appt.id
                    return (
                      <li
                        key={appt.id}
                        className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center"
                      >
                        <div className="w-28 shrink-0 text-sm font-semibold text-gray-900">
                          {formatTime(appt.starts_at)} – {formatTime(appt.ends_at)}
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="truncate font-medium text-gray-900">
                            {appt.service.name}
                            <span className="ml-2 text-sm font-normal text-gray-500">
                              com {appt.professional.name}
                            </span>
                          </p>
                          <p className="truncate text-sm text-gray-500">
                            Cliente: {appt.client?.name ?? '—'}
                            {appt.notes && (
                              <span className="ml-2 text-gray-400">· {appt.notes}</span>
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
                                    className="text-red-600 hover:bg-red-50"
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
                                  className="text-red-600 hover:bg-red-50 hover:text-red-700"
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
              <p className="text-sm text-gray-500">
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
                <span className="flex items-center px-2 text-sm text-gray-500">
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
