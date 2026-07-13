import { api } from '@/lib/api'
import type { ServicePackage } from '@/types/index'

export interface CreatePackageData {
  name: string
  description: string
  price: number
  sessions: number
  valid_days: number
  service_ids: string[]
}

export type UpdatePackageData = Partial<CreatePackageData>

export const packagesService = {
  list: (slug: string) =>
    api.get<{ data: ServicePackage[] }>(`/negocio/${slug}/packages`),

  create: (slug: string, data: CreatePackageData) =>
    api.post<{ data: ServicePackage }>(`/negocio/${slug}/packages`, data),

  update: (slug: string, id: string, data: UpdatePackageData) =>
    api.put<{ data: ServicePackage }>(`/negocio/${slug}/packages/${id}`, data),

  remove: (slug: string, id: string) =>
    api.delete(`/negocio/${slug}/packages/${id}`),
}
