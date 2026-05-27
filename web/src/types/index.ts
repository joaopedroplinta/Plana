export interface Tenant {
  id: string
  name: string
  slug: string
  plan: 'starter' | 'pro' | 'enterprise'
  active: boolean
}

export interface User {
  id: string
  name: string
  email: string
  email_verified_at: string | null
  roles: string[]
  tenant?: Tenant
}

export interface AuthResponse {
  token: string
  user: User
  tenant: Tenant
}

export interface Service {
  id: string
  name: string
  description: string | null
  price: number
  duration_minutes: number
  image_url: string | null
  active: boolean
}

export interface ServicePackage {
  id: string
  name: string
  description: string | null
  price: number
  sessions: number
  valid_days: number
  services: Service[]
}

export interface Professional {
  id: string
  name: string
  bio: string | null
  avatar_url: string | null
  active: boolean
}

export interface Appointment {
  id: string
  starts_at: string
  ends_at: string
  status: 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show'
  price: number
  notes: string | null
  service: Service
  professional: Professional
  client: User
}

export interface Payment {
  id: string
  amount: number
  method: 'pix' | 'credit_card'
  status: 'pending' | 'paid' | 'refunded' | 'failed'
  paid_at: string | null
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links: {
    first: string
    last: string
    prev: string | null
    next: string | null
  }
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}
