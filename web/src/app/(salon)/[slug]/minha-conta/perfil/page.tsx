'use client'

import { useEffect } from 'react'
import { useParams, useRouter } from 'next/navigation'
import { ProfileForm } from '@/components/shared/ProfileForm'
import { useAuth } from '@/hooks/useAuth'

export default function MeuPerfilPage() {
  const params = useParams()
  const router = useRouter()
  const slug = typeof params.slug === 'string' ? params.slug : ''
  const { isLoading: authLoading, isAuthenticated } = useAuth()

  useEffect(() => {
    if (!authLoading && !isAuthenticated) {
      router.replace(`/login?redirect=/${slug}/minha-conta/perfil`)
    }
  }, [authLoading, isAuthenticated, router, slug])

  if (authLoading || !isAuthenticated) {
    return (
      <div className="flex min-h-[60vh] items-center justify-center">
        <p className="text-sm text-muted-foreground animate-pulse">Carregando...</p>
      </div>
    )
  }

  return (
    <div className="mx-auto max-w-2xl px-4 py-8">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-foreground">Meu perfil</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Atualize seus dados pessoais e sua senha
        </p>
      </div>

      <ProfileForm />
    </div>
  )
}
