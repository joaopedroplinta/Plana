import { api } from '@/lib/api'
import type { TeamMember } from '@/types/index'

export const teamService = {
  list: (slug: string) => api.get<{ data: TeamMember[] }>(`/salao/${slug}/team`),

  invite: (slug: string, data: { name: string; email: string }) =>
    api.post<{ data: TeamMember }>(`/salao/${slug}/team`, data),

  remove: (slug: string, userId: number) => api.delete(`/salao/${slug}/team/${userId}`),
}
