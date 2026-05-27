'use client'

import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { authService } from '@/services/auth'
import type { ApiError } from '@/types/index'
import { isAxiosError } from 'axios'

interface FieldErrors {
  name?: string[]
  email?: string[]
  password?: string[]
  password_confirmation?: string[]
}

export default function RegisterPage() {
  const router = useRouter()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({})
  const [isLoading, setIsLoading] = useState(false)

  async function handleSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setError(null)
    setFieldErrors({})
    setIsLoading(true)

    try {
      const response = await authService.register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      })
      const { token, tenant } = response.data.data
      localStorage.setItem('token', token)
      document.cookie = `token=${token}; path=/; SameSite=Lax`
      router.push(`/${tenant.slug}/dashboard`)
    } catch (err) {
      if (isAxiosError(err)) {
        const apiError = err.response?.data as ApiError | undefined
        if (apiError?.errors) {
          setFieldErrors(apiError.errors as FieldErrors)
        }
        setError(apiError?.message ?? 'Erro ao criar conta. Tente novamente.')
      } else {
        setError('Erro inesperado. Tente novamente.')
      }
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="flex flex-1 items-center justify-center px-6 py-16">
      <div className="w-full max-w-sm">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-gray-900">Criar conta grátis</h1>
          <p className="mt-2 text-sm text-gray-600">
            Já tem uma conta?{' '}
            <Link
              href="/login"
              className="font-medium text-indigo-600 hover:text-indigo-500"
            >
              Entrar
            </Link>
          </p>
        </div>

        <div className="mt-8 rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
          <form onSubmit={handleSubmit} className="space-y-5">
            <div className="space-y-1.5">
              <Label htmlFor="name">Nome do salão</Label>
              <Input
                id="name"
                type="text"
                autoComplete="organization"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Ex: Salão da Maria"
                disabled={isLoading}
              />
              {fieldErrors.name && (
                <p className="text-xs text-red-600">{fieldErrors.name[0]}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="email">E-mail</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="seu@email.com"
                disabled={isLoading}
              />
              {fieldErrors.email && (
                <p className="text-xs text-red-600">{fieldErrors.email[0]}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="password">Senha</Label>
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
              {fieldErrors.password && (
                <p className="text-xs text-red-600">{fieldErrors.password[0]}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="password_confirmation">Confirmar senha</Label>
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
              {fieldErrors.password_confirmation && (
                <p className="text-xs text-red-600">
                  {fieldErrors.password_confirmation[0]}
                </p>
              )}
            </div>

            {error && (
              <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-600">
                {error}
              </p>
            )}

            <Button
              type="submit"
              className="w-full rounded-full"
              disabled={isLoading}
            >
              {isLoading ? 'Criando conta...' : 'Criar conta grátis'}
            </Button>
          </form>
        </div>
      </div>
    </div>
  )
}
