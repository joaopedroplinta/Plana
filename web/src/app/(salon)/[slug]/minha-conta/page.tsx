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
import { MinhaContaTabs } from '@/components/shared/MinhaContaTabs'
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
  pending: { label: 'Aguardando confirmação', className: 'bg-amber-100 dark:bg-amber-500/15 text-amber-800 dark:text-amber-300' },
  confirmed: { label: 'Confirmado', className: 'bg-blue-100 dark:bg-blue-500/15 text-blue-800 dark:text-blue-300' },
  completed: { label: 'Concluído', className: 'bg-green-100 dark:bg-green-500/15 text-green-800 dark:text-green-300' },
  cancelled: { label: 'Cancelado', className: 'bg-muted text-muted-foreground' },
  no_show: { label: 'Não compareceu', className: 'bg-red-100 dark:bg-red-500/15 text-red-700 dark:text-red-400' },
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
        rescheduling.id,
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
        <p className="text-sm text-muted-foreground animate-pulse">Carregando...</p>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <MinhaContaTabs slug={slug} />

      <div className="mb-6">
        <h1 className="text-2xl font-bold text-foreground">Meus agendamentos</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Acompanhe seus horários e histórico neste negócio
        </p>
      </div>

      {error && (
        <div className="mb-4 rounded-lg bg-red-50 dark:bg-red-950/40 px-4 py-3 text-sm text-red-600 dark:text-red-400">{error}</div>
      )}

      {isLoading ? (
        <p className="py-16 text-center text-sm text-muted-foreground animate-pulse">
          Carregando agendamentos...
        </p>
      ) : appointments.length === 0 ? (
        <div className="flex flex-col items-center rounded-xl border bg-card py-16 text-center">
          <CalendarX className="h-10 w-10 text-muted-foreground" />
          <p className="mt-4 text-sm text-muted-foreground">Você ainda não tem agendamentos.</p>
          <Button className="mt-4" onClick={() => router.push(`/${slug}/booking`)}>
            Agendar agora
          </Button>
        </div>
      ) : (
        <div className="space-y-3">
          {appointments.map((appt) => {
            const badge = STATUS_BADGE[appt.status] ?? {
              label: appt.status,
              className: 'bg-muted text-muted-foreground',
            }
            const canCancel = appt.status === 'pending' || appt.status === 'confirmed'
            return (
              <Card key={appt.id} data-testid="appointment-card" className="p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="min-w-0">
                    <p className="font-semibold text-foreground">{appt.service.name}</p>
                    <p className="mt-0.5 text-sm text-muted-foreground">
                      com {appt.professional.name}
                    </p>
                    <p className="mt-1.5 flex items-center gap-1.5 text-sm text-muted-foreground">
                      <Clock className="h-3.5 w-3.5 text-muted-foreground" />
                      {formatDate(appt.starts_at)} · {formatTime(appt.starts_at)} –{' '}
                      {formatTime(appt.ends_at)}
                    </p>
                  </div>
                  <div className="flex shrink-0 flex-col items-end gap-2">
                    <Badge variant="secondary" className={`text-xs ${badge.className}`}>
                      {badge.label}
                    </Badge>
                    <p className="text-sm font-bold text-primary">
                      {formatPrice(appt.price)}
                    </p>
                    {canCancel && (
                      <div className="flex gap-1">
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-primary hover:bg-secondary dark:hover:bg-primary/15 hover:text-primary"
                          onClick={() => openReschedule(appt)}
                        >
                          Remarcar
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          className="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 hover:text-red-700 dark:hover:text-red-400"
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
                <p className="rounded-lg bg-muted py-6 text-center text-sm text-muted-foreground">
                  Nenhum horário disponível nesta data.
                </p>
              ) : (
                <div className="grid max-h-56 grid-cols-3 gap-2 overflow-y-auto">
                  {slots.map((slot) => (
                    <button
                      key={`${slot.starts_at}-${slot.ends_at}`}
                      onClick={() => handleReschedule(slot)}
                      disabled={dialogLoading}
                      className="rounded-lg border border-border bg-card px-3 py-2 text-sm font-medium text-foreground transition-all hover:border-primary/60 hover:bg-secondary dark:hover:bg-primary/15 disabled:opacity-50"
                    >
                      {slot.starts_at}
                    </button>
                  ))}
                </div>
              ))}

            <p className="text-xs text-muted-foreground">
              Ao remarcar, o negócio precisa confirmar o novo horário.
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
