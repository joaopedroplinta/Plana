import { api } from '@/lib/api'
import type { Appointment, TimeSlot, CreateAppointmentData, PaginatedResponse } from '@/types/index'

interface AvailabilityResponse {
  data: TimeSlot[]
}

export const appointmentsService = {
  availability: (
    slug: string,
    professionalId: string,
    serviceId: string,
    date: string,
    ignoreAppointmentId?: string,
  ) =>
    api.get<AvailabilityResponse>(`/negocio/${slug}/availability`, {
      params: {
        professional_id: professionalId,
        service_id: serviceId,
        date,
        ignore_appointment_id: ignoreAppointmentId,
      },
    }),

  create: (slug: string, data: CreateAppointmentData) =>
    api.post<{ data: Appointment }>(`/negocio/${slug}/appointments`, data),

  list: (slug: string, params?: Record<string, string>) =>
    api.get<PaginatedResponse<Appointment>>(`/negocio/${slug}/appointments`, { params }),

  confirm: (slug: string, id: string) =>
    api.patch<{ data: Appointment }>(`/negocio/${slug}/appointments/${id}/confirm`),

  cancel: (slug: string, id: string) =>
    api.patch<{ data: Appointment }>(`/negocio/${slug}/appointments/${id}/cancel`),

  complete: (slug: string, id: string) =>
    api.patch<{ data: Appointment }>(`/negocio/${slug}/appointments/${id}/complete`),

  noShow: (slug: string, id: string) =>
    api.patch<{ data: Appointment }>(`/negocio/${slug}/appointments/${id}/no-show`),

  reschedule: (slug: string, id: string, startsAt: string) =>
    api.patch<{ data: Appointment }>(`/negocio/${slug}/appointments/${id}/reschedule`, {
      starts_at: startsAt,
    }),
}
