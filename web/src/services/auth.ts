import { api } from '@/lib/api'
import type { AuthResponse, User } from '@/types/index'

export interface RegisterData {
  name: string
  email: string
  password: string
  password_confirmation: string
  account_type?: 'owner' | 'client'
  salon_name?: string
  tenant_slug?: string
}

export const authService = {
  login: (email: string, password: string) =>
    api.post<{ data: AuthResponse }>('/auth/login', { email, password }),
  logout: () => api.post('/auth/logout'),
  me: () => api.get<{ data: User }>('/auth/me'),
  register: (data: RegisterData) =>
    api.post<{ data: AuthResponse }>('/auth/register', data),
}
