import Link from 'next/link'

const features = [
  {
    title: 'Agendamento Online',
    description:
      'Seus clientes agendam horários 24h por dia, sem precisar ligar ou enviar mensagem.',
    icon: '📅',
  },
  {
    title: 'Gestão de Serviços',
    description:
      'Cadastre serviços, profissionais e horários disponíveis em poucos minutos.',
    icon: '✂️',
  },
  {
    title: 'Relatórios e Pagamentos',
    description:
      'Acompanhe faturamento, taxa de ocupação e histórico de clientes em tempo real.',
    icon: '📊',
  },
]

export default function LandingPage() {
  return (
    <div className="flex flex-col">
      {/* Hero */}
      <section className="flex flex-1 flex-col items-center justify-center bg-gradient-to-b from-muted to-background px-6 py-24 text-center">
        <div className="mx-auto max-w-3xl">
          <h1 className="text-4xl font-extrabold tracking-tight text-foreground sm:text-5xl">
            Sistema de Agendamentos{' '}
            <span className="text-indigo-600">para Salões</span>
          </h1>
          <p className="mt-6 text-lg leading-8 text-muted-foreground">
            Simplifique a gestão do seu salão. Agendamentos online, controle de
            profissionais e relatórios — tudo em um só lugar.
          </p>
          <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            <Link
              href="/login"
              className="rounded-full bg-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition-colors"
            >
              Começar grátis
            </Link>
            <a
              href="#features"
              className="rounded-full border border-border px-8 py-3 text-sm font-semibold text-foreground hover:border-border hover:bg-muted transition-colors"
            >
              Saiba mais
            </a>
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="bg-background px-6 py-20">
        <div className="mx-auto max-w-5xl">
          <h2 className="text-center text-2xl font-bold text-foreground">
            Tudo que você precisa para gerenciar seu salão
          </h2>
          <div className="mt-12 grid gap-8 sm:grid-cols-3">
            {features.map((feature) => (
              <div
                key={feature.title}
                className="rounded-2xl border border-border bg-muted p-8 shadow-sm"
              >
                <div className="text-4xl">{feature.icon}</div>
                <h3 className="mt-4 text-lg font-semibold text-foreground">
                  {feature.title}
                </h3>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                  {feature.description}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Footer CTA */}
      <section className="bg-indigo-600 px-6 py-16 text-center">
        <div className="mx-auto max-w-2xl">
          <h2 className="text-2xl font-bold text-white">
            Pronto para modernizar seu salão?
          </h2>
          <p className="mt-4 text-indigo-100">
            Crie sua conta grátis e comece a receber agendamentos ainda hoje.
          </p>
          <Link
            href="/login"
            className="mt-8 inline-block rounded-full bg-white px-8 py-3 text-sm font-semibold text-indigo-600 shadow hover:bg-indigo-50 transition-colors"
          >
            Começar grátis
          </Link>
        </div>
      </section>
    </div>
  )
}
