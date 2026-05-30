import { api } from '@/lib/api'
import type { Appointment, TimeSlot, CreateAppointmentData, PaginatedResponse } from '@/types/index'

interface AvailabilityResponse {
  data: TimeSlot[]
}

export const appointmentsService = {
  availability: (slug: string, professionalId: string, serviceId: string, date: string) =>
    api.get<AvailabilityResponse>(`/salao/${slug}/availability`, {
      params: { professional_id: professionalId, service_id: serviceId, date },
    }),

  create: (slug: string, data: CreateAppointmentData) =>
    api.post<{ data: Appointment }>(`/salao/${slug}/appointments`, data),

  list: (slug: string, params?: Record<string, string>) =>
    api.get<PaginatedResponse<Appointment>>(`/salao/${slug}/appointments`, { params }),

  confirm: (slug: string, id: string) =>
    api.patch<{ data: Appointment }>(`/salao/${slug}/appointments/${id}/confirm`),

  cancel: (slug: string, id: string) =>
    api.patch<{ data: Appointment }>(`/salao/${slug}/appointments/${id}/cancel`),

  complete: (slug: string, id: string) =>
    api.patch<{ data: Appointment }>(`/salao/${slug}/appointments/${id}/complete`),
}
