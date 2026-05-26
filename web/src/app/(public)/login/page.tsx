import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Entrar | Agendei',
}

export default function LoginPage() {
  return (
    <div className="flex flex-1 items-center justify-center px-6 py-16">
      <div className="w-full max-w-sm">
        <div className="text-center">
          <h1 className="text-2xl font-bold text-gray-900">Entrar na sua conta</h1>
          <p className="mt-2 text-sm text-gray-600">
            Ainda não tem conta?{' '}
            <a href="/" className="font-medium text-indigo-600 hover:text-indigo-500">
              Criar conta grátis
            </a>
          </p>
        </div>

        {/* Placeholder — formulário interativo será implementado como Client Component */}
        <div className="mt-8 rounded-2xl border border-gray-100 bg-white p-8 shadow-sm">
          <div className="space-y-5">
            <div>
              <label
                htmlFor="email"
                className="block text-sm font-medium text-gray-700"
              >
                E-mail
              </label>
              <div className="mt-1 h-10 rounded-lg border border-gray-300 bg-gray-50" />
            </div>
            <div>
              <label
                htmlFor="password"
                className="block text-sm font-medium text-gray-700"
              >
                Senha
              </label>
              <div className="mt-1 h-10 rounded-lg border border-gray-300 bg-gray-50" />
            </div>
            <div className="h-10 rounded-full bg-indigo-600 opacity-60" />
          </div>
          <p className="mt-4 text-center text-xs text-gray-400">
            Formulário interativo em breve
          </p>
        </div>
      </div>
    </div>
  )
}
