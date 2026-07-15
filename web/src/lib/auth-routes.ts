import type { User } from '@/types/index'

/**
 * Onde mandar um usuário logado que clica em "home"/logo — não existe uma
 * home única, depende do papel dele (super admin, dono/staff de salão, ou
 * cliente). Mesma prioridade usada no redirect pós-login.
 */
export function resolveHomePath(user: User | null): string {
  if (!user) return '/'

  if (user.roles.includes('super_admin')) return '/super-admin'

  const tenant = user.tenant
  if (!tenant) return '/'

  if (user.roles.some((role) => ['salon_owner', 'salon_staff'].includes(role))) {
    return `/${tenant.slug}/dashboard`
  }

  return `/${tenant.slug}/minha-conta`
}
