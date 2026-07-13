import { api } from '@/lib/api'
import type { DashboardMetrics } from '@/types'

export const metricsService = {
  dashboard: (slug: string, period = 30) =>
    api.get<{ data: DashboardMetrics }>(`/negocio/${slug}/dashboard`, { params: { period } }),
}
