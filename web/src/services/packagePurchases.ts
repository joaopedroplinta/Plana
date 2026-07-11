import { api } from '@/lib/api'
import type { PackagePurchase, PaginatedResponse } from '@/types/index'

export const packagePurchasesService = {
  purchase: (slug: string, packageId: string, method: 'pix' | 'credit_card') =>
    api.post<{ data: PackagePurchase }>(`/salao/${slug}/packages/${packageId}/purchase`, {
      method,
    }),

  list: (slug: string) =>
    api.get<PaginatedResponse<PackagePurchase>>(`/salao/${slug}/package-purchases`),

  show: (slug: string, id: string) =>
    api.get<{ data: PackagePurchase }>(`/salao/${slug}/package-purchases/${id}`),
}
