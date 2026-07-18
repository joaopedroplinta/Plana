import { ProfileForm } from '@/components/shared/ProfileForm'

/**
 * Perfil do usuário logado (salon_owner/salon_staff) — dados pessoais e
 * troca de senha. Não confundir com `/[slug]/dashboard/settings`, que é a
 * configuração do negócio (salão), não do usuário. Auth/role guard já
 * acontece no `DashboardLayout` pai.
 */
export default function DashboardPerfilPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground">Meu perfil</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Atualize seus dados pessoais e sua senha
        </p>
      </div>

      <ProfileForm />
    </div>
  )
}
