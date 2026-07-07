import { api } from '@/lib/api'
import type { Tenant, UpdateTenantSettingsData } from '@/types/index'

export const tenantsService = {
  show: (slug: string) => api.get<{ data: Tenant }>(`/salao/${slug}`),

  updateSettings: (slug: string, data: UpdateTenantSettingsData) =>
    api.patch<{ data: Tenant }>(`/salao/${slug}/settings`, data),
}
