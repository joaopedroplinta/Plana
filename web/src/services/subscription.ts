import { api } from '@/lib/api'
import type { BillingCycle, CardPaymentData, Subscription, SubscriptionResponse } from '@/types'

export const subscriptionService = {
  get: (slug: string) =>
    api.get<{ data: SubscriptionResponse }>(`/negocio/${slug}/subscription`),

  create: (
    slug: string,
    data: { plan: string; method: 'pix' | 'credit_card'; billing_cycle?: BillingCycle },
    card?: CardPaymentData,
  ) => api.post<{ data: Subscription }>(`/negocio/${slug}/subscription`, { ...data, ...card }),
}
