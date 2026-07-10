'use client'

import { useCallback, useEffect, useState } from 'react'
import { useParams, useRouter } from 'next/navigation'
import { isAxiosError } from 'axios'
import { CalendarX, Clock } from 'lucide-react'
import { toast } from 'sonner'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useAuth } from '@/hooks/useAuth'
import { appointmentsService } from '@/services/appointments'
import type { ApiError, Appointment, TimeSlot } from '@/types/index'
import { formatDate, formatPrice, formatTime } from '@/lib/format'

const STATUS_BADGE: Record<string, { label: string; className: string }> = {
  pending: { label: 'Aguardando confirmação', className: 'bg-amber-100 text-amber-800' },
  confirmed: { label: 'Confirmado', className: 'bg-blue-100 text-blue-800' },
  completed: { label: 'Concluído', className: 'bg-green-100 text-green-800' },
  cancelled: { label: 'Cancelado', className: 'bg-gray-100 text-gray-500' },
  no_show: { label: 'Não compareceu', className: 'bg-red-100 text-red-700' },
}

export default function MinhaContaPage() {
  const params = useParams()
  const router = useRouter()
  const slug = typeof params.slug === 'string' ? params.slug : ''
  const { isLoading: authLoading, isAuthenticated } = useAuth()

  const [appointments, setAppointments] = useState<Appointment[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState('')
  const [cancellingId, setCancellingId] = useState<string | null>(null)
  const [appointmentToCancel, setAppointmentToCancel] = useState<Appointment | null>(null)

  // Estado do diálogo de remarcação
  const [rescheduling, setRescheduling] = useState<Appointment | null>(null)
  const [newDate, setNewDate] = useState('')
  const [slots, setSlots] = useState<TimeSlot[]>([])
  const [slotsLoaded, setSlotsLoaded] = useState(false)
  const [dialogLoading, setDialogLoading] = useState(false)

  const today = new Date().toISOString().split('T')[0]

  useEffect(() => {
    if (!authLoading && !isAuthenticated) {
      router.replace(`/login?redirect=/${slug}/minha-conta`)
    }
  }, [authLoading, isAuthenticated, router, slug])

  const loadAppointments = useCallback(() => {
    if (!slug) return
    appointmentsService
      .list(slug)
      .then((res) => setAppointments(res.data.data))
      .catch(() => setError('Erro ao carregar seus agendamentos.'))
      .finally(() => setIsLoading(false))
  }, [slug])

  useEffect(() => {
    if (isAuthenticated) loadAppointments()
  }, [isAuthenticated, loadAppointments])

  async function handleCancel() {
    if (!appointmentToCancel) return
    const appointment = appointmentToCancel
    setAppointmentToCancel(null)
    setCancellingId(appointment.id)
    try {
      await appointmentsService.cancel(slug, appointment.id)
      loadAppointments()
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        toast.error(apiError?.message ?? 'Erro ao cancelar agendamento.')
      } else {
        toast.error('Erro inesperado. Tente novamente.')
      }
    } finally {
      setCancellingId(null)
    }
  }

  function openReschedule(appointment: Appointment) {
    setRescheduling(appointment)
    setNewDate('')
    setSlots([])
    setSlotsLoaded(false)
  }

  async function loadSlots() {
    if (!rescheduling || !newDate) return
    setDialogLoading(true)
    try {
      const res = await appointmentsService.availability(
        slug,
        rescheduling.professional.id,
        rescheduling.service.id,
        newDate,
      )
      setSlots(res.data.data)
      setSlotsLoaded(true)
    } catch {
      toast.error('Erro ao buscar horários. Tente novamente.')
    } finally {
      setDialogLoading(false)
    }
  }

  async function handleReschedule(slot: TimeSlot) {
    if (!rescheduling || !newDate) return
    setDialogLoading(true)
    try {
      await appointmentsService.reschedule(slug, rescheduling.id, `${newDate}T${slot.starts_at}:00`)
      setRescheduling(null)
      loadAppointments()
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        toast.error(
          apiError?.errors?.starts_at?.[0] ?? apiError?.message ?? 'Erro ao remarcar.',
        )
      } else {
        toast.error('Erro inesperado. Tente novamente.')
      }
    } finally {
      setDialogLoading(false)
    }
  }

  if (authLoading || (!isAuthenticated && !error)) {
    return (
      <div className="flex min-h-[60vh] items-center justify-center">
        <p className="text-sm text-gray-400 animate-pulse">Carregando...</p>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-900">Meus agendamentos</h1>
        <p className="mt-1 text-sm text-gray-500">
          Acompanhe seus horários e histórico neste salão
        </p>
      </div>

      {error && (
        <div className="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-600">{error}</div>
      )}

      {isLoading ? (
        <p className="py-16 text-center text-sm text-gray-400 animate-pulse">
          Carregando agendamentos...
        </p>
      ) : appointments.length === 0 ? (
        <div className="flex flex-col items-center rounded-xl border bg-white py-16 text-center">
          <CalendarX className="h-10 w-10 text-gray-300" />
          <p className="mt-4 text-sm text-gray-500">Você ainda não tem agendamentos.</p>
          <Button className="mt-4" onClick={() => router.push(`/${slug}/booking`)}>
            Agendar agora
          </Button>
        </div>
      ) : (
        <div className="space-y-3">
          {appointments.map((appt) => {
            const badge = STATUS_BADGE[appt.status] ?? {
              label: appt.status,
              className: 'bg-gray-100 text-gray-600',
            }
            const canCancel = appt.status === 'pending' || appt.status === 'confirmed'
            return (
              <Card key={appt.id} className="p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0">
                    <p className="font-semibold text-gray-900">{appt.service.name}</p>
                    <p className="mt-0.5 text-sm text-gray-500">
                      com {appt.professional.name}
                    </p>
                    <p className="mt-1.5 flex items-center gap-1.5 text-sm text-gray-600">
                      <Clock className="h-3.5 w-3.5 text-gray-400" />
                      {formatDate(appt.starts_at)} · {formatTime(appt.starts_at)} –{' '}
                      {formatTime(appt.ends_at)}
                    </p>
                  </div>
                  <div className="flex shrink-0 flex-col items-end gap-2">
                    <Badge variant="secondary" className={`text-xs ${badge.className}`}>
                      {badge.label}
                    </Badge>
                    <p className="text-sm font-bold text-indigo-600">
                      {formatPrice(appt.price)}
                    </p>
                    {canCancel && (
                      <div className="flex gap-1">
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700"
                          onClick={() => openReschedule(appt)}
                        >
                          Remarcar
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-red-600 hover:bg-red-50 hover:text-red-700"
                          disabled={cancellingId === appt.id}
                          onClick={() => setAppointmentToCancel(appt)}
                        >
                          {cancellingId === appt.id ? 'Cancelando...' : 'Cancelar'}
                        </Button>
                      </div>
                    )}
                  </div>
                </div>
              </Card>
            )
          })}
        </div>
      )}

      <Dialog open={rescheduling !== null} onOpenChange={(open) => !open && setRescheduling(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Remarcar agendamento</DialogTitle>
            <DialogDescription>
              {rescheduling
                ? `${rescheduling.service.name} com ${rescheduling.professional.name}`
                : ''}
            </DialogDescription>
          </DialogHeader>

          <div className="space-y-4">
            <div className="flex items-end gap-3">
              <div className="flex-1 space-y-1.5">
                <Label htmlFor="reschedule-date">Nova data</Label>
                <Input
                  id="reschedule-date"
                  type="date"
                  min={today}
                  value={newDate}
                  onChange={(e) => {
                    setNewDate(e.target.value)
                    setSlotsLoaded(false)
                    setSlots([])
                  }}
                  disabled={dialogLoading}
                />
              </div>
              <Button onClick={loadSlots} disabled={!newDate || dialogLoading}>
                {dialogLoading && !slotsLoaded ? 'Buscando...' : 'Ver horários'}
              </Button>
            </div>

            {slotsLoaded &&
              (slots.length === 0 ? (
                <p className="rounded-lg bg-gray-50 py-6 text-center text-sm text-gray-500">
                  Nenhum horário disponível nesta data.
                </p>
              ) : (
                <div className="grid max-h-56 grid-cols-3 gap-2 overflow-y-auto">
                  {slots.map((slot) => (
                    <button
                      key={`${slot.starts_at}-${slot.ends_at}`}
                      onClick={() => handleReschedule(slot)}
                      disabled={dialogLoading}
                      className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-all hover:border-indigo-400 hover:bg-indigo-50 disabled:opacity-50"
                    >
                      {slot.starts_at}
                    </button>
                  ))}
                </div>
              ))}

            <p className="text-xs text-gray-400">
              Ao remarcar, o salão precisa confirmar o novo horário.
            </p>
          </div>
        </DialogContent>
      </Dialog>

      <AlertDialog
        open={appointmentToCancel !== null}
        onOpenChange={(open) => !open && setAppointmentToCancel(null)}
      >
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Cancelar agendamento</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja cancelar este agendamento?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Voltar</AlertDialogCancel>
            <AlertDialogAction
              className="bg-red-600 text-white hover:bg-red-700"
              onClick={handleCancel}
            >
              Cancelar agendamento
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
