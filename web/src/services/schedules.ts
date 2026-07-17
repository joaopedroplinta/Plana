import { api } from '@/lib/api'
import type { Schedule } from '@/types/index'

export interface ScheduleInput {
  day_of_week: number
  start_time: string
  end_time: string
}

export const schedulesService = {
  list: (slug: string, professionalId: string) =>
    api.get<{ data: Schedule[] }>(
      `/negocio/${slug}/professionals/${professionalId}/schedules`,
    ),

  // Substitui a semana inteira do profissional de uma vez.
  sync: (slug: string, professionalId: string, schedules: ScheduleInput[]) =>
    api.put<{ data: Schedule[] }>(
      `/negocio/${slug}/professionals/${professionalId}/schedules`,
      { schedules },
    ),
}
