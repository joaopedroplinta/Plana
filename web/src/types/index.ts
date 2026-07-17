/** Sinal (valor de reserva) cobrado online no agendamento. */
export type DepositType = 'none' | 'fixed' | 'percentage'

export interface Tenant {
  id: string
  name: string
  slug: string
  plan: 'starter' | 'pro' | 'enterprise'
  active: boolean
  description: string | null
  phone: string | null
  whatsapp: string | null
  address: string | null
  instagram: string | null
  /** Sinal padrão do salão, aplicado aos serviços sem override próprio. */
  deposit_type: DepositType
  /** Centavos (fixed) ou percentual 1..100 (percentage); null quando 'none'. */
  deposit_value: number | null
  current_tenant_role: 'owner' | 'staff' | 'client' | null
}

export interface UpdateTenantSettingsData {
  name?: string
  description?: string | null
  phone?: string | null
  whatsapp?: string | null
  address?: string | null
  instagram?: string | null
  deposit_type?: DepositType
  deposit_value?: number | null
}

export interface User {
  id: string
  name: string
  email: string
  email_verified_at: string | null
  // API retorna `getRoleNames()` do Spatie Permission — lista de strings, não de objetos.
  roles: string[]
  tenant?: Tenant
}

export interface AuthResponse {
  token: string
  user: User
  tenant: Tenant | null
}

export interface Service {
  id: string
  name: string
  description: string | null
  price: number
  /** null = herda o padrão do salão; 'none' desativa o sinal neste serviço. */
  deposit_type: DepositType | null
  deposit_value: number | null
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

/** 0 = domingo … 6 = sábado (Carbon::dayOfWeek). */
export type DayOfWeek = 0 | 1 | 2 | 3 | 4 | 5 | 6

/** Horário de trabalho de um profissional num dia da semana. */
export interface Schedule {
  id: string
  professional_id: string
  day_of_week: DayOfWeek
  start_time: string
  end_time: string
}

/** Horário de funcionamento do salão num dia da semana. */
export interface BusinessHour {
  day_of_week: DayOfWeek
  is_open: boolean
  open_time: string | null
  close_time: string | null
}

export interface Appointment {
  id: string
  starts_at: string
  ends_at: string
  status: 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'no_show'
  price: number
  /** Sinal cobrado online na reserva (centavos); null = cobrou o valor cheio. */
  deposit_amount: number | null
  /** Saldo a pagar presencialmente = price − valor cobrado online. */
  balance_due: number
  notes: string | null
  package_purchase_id: string | null
  service: Service
  professional: Professional
  client: User
  payments?: Payment[]
}

export interface Payment {
  id: string
  appointment_id: string | null
  amount: number
  /** Comissão da plataforma retida neste pagamento, em centavos (null quando não houve). */
  platform_fee: number | null
  method: 'pix' | 'credit_card'
  status: 'pending' | 'approved' | 'rejected' | 'refunded' | 'cancelled'
  pix_qr_code: string | null
  pix_qr_code_base64: string | null
  preference_url: string | null
  paid_at: string | null
}

/**
 * Dados do cartão tokenizado pelo Card Payment Brick (MercadoPago.js) no
 * frontend — nunca enviamos o número do cartão em si, só o token.
 */
export interface CardPaymentData {
  token: string
  payment_method_id: string
  installments: number
  issuer_id?: string
  payer?: {
    email?: string
    identification?: {
      type: string
      number: string
    }
  }
}

export interface PackagePurchase {
  id: string
  service_package: ServicePackage
  sessions_total: number
  sessions_used: number
  sessions_remaining: number
  price_paid: number
  status: 'pending' | 'active' | 'expired' | 'cancelled'
  purchased_at: string | null
  expires_at: string | null
  payment: Payment | null
  created_at: string
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
  package_purchase_id?: string
}

export interface TeamMember {
  id: number
  name: string
  email: string
  role: 'owner' | 'staff'
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

export interface MercadoPagoStatus {
  connected: boolean
  connected_at: string | null
  mp_user_id: string | null
}

export interface MercadoPagoConnectUrl {
  authorization_url: string
}
