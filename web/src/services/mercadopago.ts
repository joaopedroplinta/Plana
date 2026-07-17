import { api } from '@/lib/api'
import type { MercadoPagoConnectUrl, MercadoPagoStatus } from '@/types/index'

export const mercadopagoService = {
  getStatus: (slug: string) =>
    api.get<{ data: MercadoPagoStatus }>(`/negocio/${slug}/mercadopago/status`),

  getConnectUrl: (slug: string) =>
    api.get<{ data: MercadoPagoConnectUrl }>(`/negocio/${slug}/mercadopago/connect`),

  disconnect: (slug: string) =>
    api.delete<{ data: { connected: false } }>(`/negocio/${slug}/mercadopago/disconnect`),
}
