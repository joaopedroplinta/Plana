import { api } from '@/lib/api'
import type { CardPaymentData, Subscription, SubscriptionResponse } from '@/types'

export const subscriptionService = {
  get: (slug: string) =>
    api.get<{ data: SubscriptionResponse }>(`/salao/${slug}/subscription`),

  create: (
    slug: string,
    data: { plan: string; method: 'pix' | 'credit_card' },
    card?: CardPaymentData,
  ) => api.post<{ data: Subscription }>(`/salao/${slug}/subscription`, { ...data, ...card }),
}
