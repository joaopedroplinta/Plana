import { api } from '@/lib/api'
import type { Professional } from '@/types/index'

export interface CreateProfessionalData {
  name: string
  bio: string
  active: boolean
}

export type UpdateProfessionalData = Partial<CreateProfessionalData>

export const professionalsService = {
  list: (slug: string) =>
    api.get<{ data: Professional[] }>(`/negocio/${slug}/professionals`),

  create: (slug: string, data: CreateProfessionalData) =>
    api.post<{ data: Professional }>(`/negocio/${slug}/professionals`, data),

  update: (slug: string, id: string, data: UpdateProfessionalData) =>
    api.put<{ data: Professional }>(`/negocio/${slug}/professionals/${id}`, data),

  remove: (slug: string, id: string) =>
    api.delete(`/negocio/${slug}/professionals/${id}`),
}
