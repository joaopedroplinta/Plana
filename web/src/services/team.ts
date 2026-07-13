import { api } from '@/lib/api'
import type { TeamMember } from '@/types/index'

export const teamService = {
  list: (slug: string) => api.get<{ data: TeamMember[] }>(`/negocio/${slug}/team`),

  invite: (slug: string, data: { name: string; email: string }) =>
    api.post<{ data: TeamMember }>(`/negocio/${slug}/team`, data),

  remove: (slug: string, userId: number) => api.delete(`/negocio/${slug}/team/${userId}`),
}
