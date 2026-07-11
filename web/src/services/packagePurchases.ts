import { api } from '@/lib/api'
import type { CardPaymentData, PackagePurchase, PaginatedResponse } from '@/types/index'

export const packagePurchasesService = {
  purchase: (slug: string, packageId: string, method: 'pix' | 'credit_card', card?: CardPaymentData) =>
    api.post<{ data: PackagePurchase }>(`/salao/${slug}/packages/${packageId}/purchase`, {
      method,
      ...card,
    }),

  list: (slug: string) =>
    api.get<PaginatedResponse<PackagePurchase>>(`/salao/${slug}/package-purchases`),

  show: (slug: string, id: string) =>
    api.get<{ data: PackagePurchase }>(`/salao/${slug}/package-purchases/${id}`),
}
