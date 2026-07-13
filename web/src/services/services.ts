import { api } from '@/lib/api'
import type { Service, PaginatedResponse } from '@/types/index'

export interface CreateServiceData {
  name: string
  description: string
  price: number
  duration_minutes: number
}

export type UpdateServiceData = Partial<CreateServiceData>

export const servicesService = {
  list: (slug: string) =>
    api.get<PaginatedResponse<Service>>(`/negocio/${slug}/services`),

  create: (slug: string, data: CreateServiceData) =>
    api.post<{ data: Service }>(`/negocio/${slug}/services`, data),

  update: (slug: string, id: string, data: UpdateServiceData) =>
    api.put<{ data: Service }>(`/negocio/${slug}/services/${id}`, data),

  remove: (slug: string, id: string) =>
    api.delete(`/negocio/${slug}/services/${id}`),
}
