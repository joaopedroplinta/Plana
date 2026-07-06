export interface Tenant {
  id: string
  name: string
  slug: string
  plan: 'starter' | 'pro' | 'enterprise'
  active: boolean
}

export interface Role {
  id: number
  name: string
  guard_name: string
}

export interface User {
  id: string
  name: string
  email: string
  email_verified_at: string | null
  roles: Role[]
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
  payments?: Payment[]
}

export interface Payment {
  id: string
  appointment_id: string
  amount: number
  method: 'pix' | 'credit_card'
  status: 'pending' | 'approved' | 'rejected' | 'refunded'
  pix_qr_code: string | null
  pix_qr_code_base64: string | null
  preference_url: string | null
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

export interface TimeSlot {
  starts_at: string // "HH:MM"
  ends_at: string   // "HH:MM"
}

export interface CreateAppointmentData {
  professional_id: string
  service_id: string
  starts_at: string // ISO datetime: "YYYY-MM-DDTHH:MM:00"
  notes?: string
}

export interface AdminTenantOwner {
  name: string
  email: string
}

export interface AdminTenant {
  id: string
  name: string
  slug: string
  plan: 'starter' | 'pro' | 'enterprise'
  active: boolean
  created_at: string
  trial_ends_at: string | null
  user_count: number
  owner: AdminTenantOwner | null
}

export interface SubscriptionPlan {
  key: 'starter' | 'pro' | 'enterprise'
  name: string
  price: number // centavos
  professionals: string
  appointments: string
  features: string[]
}

export interface Subscription {
  id: string
  plan: 'starter' | 'pro' | 'enterprise'
  amount: number
  method: 'pix' | 'credit_card'
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  pix_qr_code: string | null
  pix_qr_code_base64: string | null
  mp_preference_id: string | null
  paid_at: string | null
  expires_at: string | null
  created_at: string
}

export interface SubscriptionResponse {
  current_plan: 'starter' | 'pro' | 'enterprise'
  plans: SubscriptionPlan[]
  subscriptions: Subscription[]
}

export interface AdminMetrics {
  total_tenants: number
  active_tenants: number
  tenants_by_plan: Array<{ plan: string; count: number }>
  total_users: number
  total_appointments: number
  total_revenue: number
}

export interface DashboardSummary {
  total_appointments: number
  completed_appointments: number
  appointments_today: number
  total_clients: number
  total_revenue: number
  revenue_this_month: number
}

export interface DashboardMetrics {
  summary: DashboardSummary
  appointments_by_status: Array<{ status: string; count: number }>
  revenue_by_day: Array<{ date: string; revenue: number; count: number }>
  top_services: Array<{ name: string; count: number; revenue: number }>
  appointments_by_professional: Array<{ name: string; count: number; revenue: number }>
  period: number
}
