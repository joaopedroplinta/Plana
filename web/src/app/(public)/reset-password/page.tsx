'use client'

import { useState, Suspense } from 'react'
import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { api } from '@/lib/api'
import type { ApiError } from '@/types/index'
import { isAxiosError } from 'axios'

function ResetPasswordForm() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const token = searchParams.get('token') ?? ''
  const email = searchParams.get('email') ?? ''

  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setError(null)

    if (password !== passwordConfirmation) {
      setError('As senhas nao coincidem.')
      return
    }

    setIsLoading(true)

    try {
      await api.post('auth/reset-password', {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      router.push('/login')
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        setError(apiError?.message ?? 'Erro ao redefinir senha. Tente novamente.')
      } else {
        setError('Erro inesperado. Tente novamente.')
      }
    } finally {
      setIsLoading(false)
    }
  }

  if (!token || !email) {
    return (
      <div className="rounded-lg bg-red-50 dark:bg-red-950/40 px-4 py-4 text-center">
        <p className="text-sm text-red-700 dark:text-red-400">
          Link invalido ou expirado. Solicite um novo link de recuperacao.
        </p>
        <Link
          href="/forgot-password"
          className="mt-3 block text-sm font-medium text-primary hover:text-primary"
        >
          Solicitar novo link
        </Link>
      </div>
    )
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      <div className="space-y-1.5">
        <Label htmlFor="password">Nova senha</Label>
        <Input
          id="password"
          type="password"
          autoComplete="new-password"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          placeholder="••••••••"
          disabled={isLoading}
        />
      </div>

      <div className="space-y-1.5">
        <Label htmlFor="password_confirmation">Confirmar nova senha</Label>
        <Input
          id="password_confirmation"
          type="password"
          autoComplete="new-password"
          required
          value={passwordConfirmation}
          onChange={(e) => setPasswordConfirmation(e.target.value)}
          placeholder="••••••••"
          disabled={isLoading}
        />
      </div>

      {error && (
        <p className="rounded-lg bg-red-50 dark:bg-red-950/40 px-3 py-2 text-sm text-red-600 dark:text-red-400">
          {error}
        </p>
      )}

      <Button
        type="submit"
        className="w-full rounded-full"
        disabled={isLoading}
      >
        {isLoading ? 'Redefinindo...' : 'Redefinir senha'}
      </Button>
    </form>
  )
}

export default function ResetPasswordPage() {
  return (
    <div className="flex flex-1 items-center justify-center px-6 py-16">
      <div className="w-full max-w-sm">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-foreground">Redefinir senha</h1>
          <p className="mt-2 text-sm text-muted-foreground">
            Lembrou a senha?{' '}
            <Link
              href="/login"
              className="font-medium text-primary hover:text-primary"
            >
              Voltar ao login
            </Link>
          </p>
        </div>

        <div className="mt-8 rounded-2xl border border-border bg-card p-8 shadow-sm">
          <Suspense
            fallback={
              <p className="text-center text-sm text-muted-foreground">Carregando...</p>
            }
          >
            <ResetPasswordForm />
          </Suspense>
        </div>
      </div>
    </div>
  )
}
