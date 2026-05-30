import { api } from '@/lib/api'
import type { Payment } from '@/types'

export const paymentsService = {
  create: (slug: string, appointmentId: string, method: 'pix' | 'credit_card') =>
    api.post<{ data: Payment }>(`/salao/${slug}/appointments/${appointmentId}/payments`, { method }),

  status: (slug: string, paymentId: string) =>
    api.get<{ data: Payment }>(`/salao/${slug}/payments/${paymentId}`),
}
