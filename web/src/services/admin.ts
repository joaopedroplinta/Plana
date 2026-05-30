import { api } from '@/lib/api'
import type { AdminMetrics, AdminTenant, PaginatedResponse } from '@/types'

export const adminService = {
  metrics: () =>
    api.get<{ data: AdminMetrics }>('/admin/metrics'),

  listTenants: (page = 1) =>
    api.get<PaginatedResponse<AdminTenant>>('/admin/tenants', { params: { page } }),

  updateTenant: (id: string, data: { plan?: string; active?: boolean }) =>
    api.patch<{ data: AdminTenant }>(`/admin/tenants/${id}`, data),
}
