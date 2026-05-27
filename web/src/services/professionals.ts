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
    api.get<{ data: Professional[] }>(`/salao/${slug}/professionals`),

  create: (slug: string, data: CreateProfessionalData) =>
    api.post<{ data: Professional }>(`/salao/${slug}/professionals`, data),

  update: (slug: string, id: string, data: UpdateProfessionalData) =>
    api.put<{ data: Professional }>(`/salao/${slug}/professionals/${id}`, data),

  remove: (slug: string, id: string) =>
    api.delete(`/salao/${slug}/professionals/${id}`),
}
