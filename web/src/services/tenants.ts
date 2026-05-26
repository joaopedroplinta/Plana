import { api } from '@/lib/api'
import type { Tenant } from '@/types/index'

export const tenantsService = {
  show: (slug: string) => api.get<{ data: Tenant }>(`/salao/${slug}`),
}
