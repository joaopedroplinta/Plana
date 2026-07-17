import { api } from '@/lib/api'
import type { BusinessHour } from '@/types/index'

export interface BusinessHourInput {
  day_of_week: number
  is_open: boolean
  open_time: string | null
  close_time: string | null
}

export const businessHoursService = {
  list: (slug: string) =>
    api.get<{ data: BusinessHour[] }>(`/negocio/${slug}/business-hours`),

  // Substitui o horário de funcionamento da semana inteira.
  sync: (slug: string, days: BusinessHourInput[]) =>
    api.put<{ data: BusinessHour[] }>(`/negocio/${slug}/business-hours`, { days }),
}
