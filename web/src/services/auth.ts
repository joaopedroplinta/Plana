import { api } from '@/lib/api'
import type { User } from '@/types/index'

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
}

export const authService = {
  login: (email: string, password: string) =>
    api.post<{ token: string; user: User }>('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  me: () => api.get<{ data: User }>('/auth/me'),
  register: (data: RegisterData) =>
    api.post<{ token: string; user: User }>('/auth/register', data),
}
