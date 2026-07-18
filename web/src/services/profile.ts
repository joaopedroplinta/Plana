import { api } from '@/lib/api'
import type { UpdatePasswordData, UpdateProfileData, User } from '@/types/index'

export const profileService = {
  show: () => api.get<{ data: User }>('/auth/profile'),

  update: (data: UpdateProfileData) => api.patch<{ data: User }>('/auth/profile', data),

  updatePassword: (data: UpdatePasswordData) =>
    api.put<{ message: string }>('/auth/profile/password', data),

  uploadAvatar: (file: File) => {
    const form = new FormData()
    form.append('avatar', file)
    return api.post<{ data: User }>('/auth/profile/avatar', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },
}
