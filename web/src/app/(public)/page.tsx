import Link from 'next/link'
import { BarChart3, Calendar, Check, Scissors } from 'lucide-react'
import { cn } from '@/lib/utils'

const features = [
  {
    title: 'Agendamento Online',
    description:
      'Seus clientes agendam horários 24h por dia, sem precisar ligar ou enviar mensagem.',
    icon: Calendar,
  },
  {
    title: 'Gestão de Serviços',
    description:
      'Cadastre serviços, profissionais e horários disponíveis em poucos minutos.',
    icon: Scissors,
  },
  {
    title: 'Relatórios e Pagamentos',
    description:
      'Acompanhe faturamento, taxa de ocupação e histórico de clientes em tempo real.',
    icon: BarChart3,
  },
]

type SlotState = 'booked' | 'available' | 'selected'

const slots: { time: string; state: SlotState }[] = [
  { time: '09:00', state: 'booked' },
  { time: '09:30', state: 'booked' },
  { time: '10:00', state: 'available' },
  { time: '10:30', state: 'selected' },
  { time: '11:00', state: 'available' },
  { time: '11:30', state: 'booked' },
]

const slotClasses: Record<SlotState, string> = {
  booked: 'border-border bg-muted text-muted-foreground/70 line-through',
  available:
    'border-[var(--lima-500)]/50 bg-[var(--lima-100)] text-[var(--teal-900)] dark:text-[var(--lima-500)]',
  selected: 'border-primary bg-primary text-white shadow-sm',
}

export default function LandingPage() {
  return (
    <div className="flex flex-col">
      {/* Hero */}
      <section className="bg-gradient-to-b from-muted to-background px-6 py-24">
        <div className="mx-auto grid max-w-5xl items-center gap-12 lg:grid-cols-2">
          <div className="text-center lg:text-left">
            <h1 className="font-heading text-4xl font-extrabold tracking-tight text-foreground sm:text-5xl">
              Sistema de Agendamentos{' '}
              <span className="text-primary">para o seu Negócio</span>
            </h1>
            <p className="mt-6 text-lg leading-8 text-muted-foreground">
              Simplifique a gestão do seu negócio. Agendamentos online, controle de
              profissionais e relatórios — tudo em um só lugar.
            </p>
            <div className="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center lg:justify-start">
              <Link
                href="/login"
                className="rounded-full bg-primary px-8 py-3 text-sm font-semibold text-white shadow-sm hover:bg-primary/90 transition-colors"
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

          {/* Mockup da grade de horários — a coisa mais característica que o produto faz */}
          <div className="flex justify-center lg:justify-end">
            <div className="w-full max-w-sm rounded-2xl border border-border bg-card p-5 shadow-lg">
              <div className="mb-4 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <Calendar className="h-4 w-4 text-primary" />
                Corte + Barba · 45 min
              </div>
              <div className="grid grid-cols-3 gap-2">
                {slots.map((slot) => (
                  <div
                    key={slot.time}
                    className={cn(
                      'flex items-center justify-center gap-1 rounded-lg border px-2 py-2.5 text-xs font-medium tabular-nums transition-colors',
                      slotClasses[slot.state],
                    )}
                  >
                    {slot.state === 'selected' && <Check className="h-3 w-3" />}
                    {slot.time}
                  </div>
                ))}
              </div>
              <p className="mt-4 text-xs text-muted-foreground">
                <span className="font-medium text-[var(--teal-900)] dark:text-[var(--lima-500)]">
                  10:00
                </span>{' '}
                e{' '}
                <span className="font-medium text-[var(--teal-900)] dark:text-[var(--lima-500)]">
                  11:00
                </span>{' '}
                ainda disponíveis hoje
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="bg-background px-6 py-20">
        <div className="mx-auto max-w-5xl">
          <h2 className="text-center font-heading text-2xl font-bold text-foreground">
            Tudo que você precisa para gerenciar seu negócio
          </h2>
          <div className="mt-12 grid gap-8 sm:grid-cols-3">
            {features.map((feature) => (
              <div
                key={feature.title}
                className="rounded-2xl border border-border bg-card p-8 shadow-sm"
              >
                <feature.icon className="h-8 w-8 text-primary" strokeWidth={1.75} />
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
      <section className="bg-[var(--teal-900)] px-6 py-16 text-center">
        <div className="mx-auto max-w-2xl">
          <h2 className="font-heading text-2xl font-bold text-white">
            Pronto para <span className="text-[var(--lima-500)]">modernizar</span> seu
            negócio?
          </h2>
          <p className="mt-4 text-white/80">
            Crie sua conta grátis e comece a receber agendamentos ainda hoje.
          </p>
          <Link
            href="/login"
            className="mt-8 inline-block rounded-full bg-white px-8 py-3 text-sm font-semibold text-primary shadow hover:bg-secondary transition-colors"
          >
            Começar grátis
          </Link>
        </div>
      </section>
    </div>
  )
}
