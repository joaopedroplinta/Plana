import { api } from '@/lib/api'
import type { Tenant, UpdateTenantSettingsData } from '@/types/index'

export const tenantsService = {
  show: (slug: string) => api.get<{ data: Tenant }>(`/negocio/${slug}`),

  updateSettings: (slug: string, data: UpdateTenantSettingsData) =>
    api.patch<{ data: Tenant }>(`/negocio/${slug}/settings`, data),

  uploadLogo: (slug: string, file: File) => {
    const form = new FormData()
    form.append('logo', file)
    return api.post<{ data: Tenant }>(`/negocio/${slug}/logo`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
