import { api } from '@/lib/api'
import type { CardPaymentData, Payment } from '@/types'

export const paymentsService = {
  create: (slug: string, appointmentId: string, method: 'pix' | 'credit_card', card?: CardPaymentData) =>
    api.post<{ data: Payment }>(`/negocio/${slug}/appointments/${appointmentId}/payments`, {
      method,
      ...card,
    }),

  status: (slug: string, paymentId: string) =>
    api.get<{ data: Payment }>(`/negocio/${slug}/payments/${paymentId}`),
}
