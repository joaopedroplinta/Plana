import { api } from '@/lib/api'
import type { DepositType, Service, PaginatedResponse } from '@/types/index'

export interface CreateServiceData {
  name: string
  description: string
  price: number
  duration_minutes: number
  active?: boolean
  /** null = herda o sinal padrão do salão; 'none' desativa neste serviço. */
  deposit_type?: DepositType | null
  deposit_value?: number | null
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

  uploadImage: (slug: string, id: string, file: File) => {
    const form = new FormData()
    form.append('image', file)
    return api.post<{ data: Service }>(`/negocio/${slug}/services/${id}/image`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
