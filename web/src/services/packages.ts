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
    api.get<{ data: ServicePackage[] }>(`/salao/${slug}/packages`),

  create: (slug: string, data: CreatePackageData) =>
    api.post<{ data: ServicePackage }>(`/salao/${slug}/packages`, data),

  update: (slug: string, id: string, data: UpdatePackageData) =>
    api.put<{ data: ServicePackage }>(`/salao/${slug}/packages/${id}`, data),

  remove: (slug: string, id: string) =>
    api.delete(`/salao/${slug}/packages/${id}`),
}
