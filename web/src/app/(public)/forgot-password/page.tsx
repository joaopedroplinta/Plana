import Link from 'next/link'
import type { Metadata } from 'next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export const metadata: Metadata = {
  title: 'Recuperar senha | Agendei',
}

export default function ForgotPasswordPage() {
  return (
    <div className="flex flex-1 items-center justify-center px-6 py-16">
      <div className="w-full max-w-sm">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-gray-900">Recuperar senha</h1>
          <p className="mt-2 text-sm text-gray-600">
            Lembrou a senha?{' '}
            <Link
              href="/login"
              className="font-medium text-indigo-600 hover:text-indigo-500"
            >
              Voltar ao login
            </Link>
          </p>
        </div>

        <div className="mt-8 rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
          <div className="space-y-5">
            <div className="space-y-1.5">
              <Label htmlFor="email">E-mail</Label>
              <Input
                id="email"
                type="email"
                placeholder="seu@email.com"
                disabled
              />
            </div>

            <Button className="w-full rounded-full" disabled>
              Enviar link de recuperação
            </Button>

            <p className="text-center text-xs text-gray-400">
              Em breve — funcionalidade disponível na próxima versão.
            </p>
          </div>
        </div>
      </div>
    </div>
  )
}
