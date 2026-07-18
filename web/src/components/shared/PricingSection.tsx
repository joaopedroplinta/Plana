'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'
import { formatPrice } from '@/lib/format'
import type { BillingCycle } from '@/types'

// Espelha api/app/Services/SubscriptionService.php::PLANS — mantenha os dois em sincronia.
const plans = [
  {
    key: 'starter',
    name: 'Starter',
    price: 0,
    yearlyPrice: null,
    description: 'Pra começar sem compromisso.',
    features: ['1 profissional', '50 agendamentos/mês', 'Suporte básico'],
    highlighted: false,
  },
  {
    key: 'pro',
    name: 'Pro',
    price: 9700,
    yearlyPrice: 97000,
    description: 'Pra quem já tem uma agenda cheia.',
    features: [
      '5 profissionais',
      'Agendamentos ilimitados',
      'Suporte prioritário',
      'Relatórios avançados',
    ],
    highlighted: true,
  },
  {
    key: 'enterprise',
    name: 'Enterprise',
    price: 19700,
    yearlyPrice: 197000,
    description: 'Pra redes e negócios com múltiplas unidades.',
    features: [
      'Profissionais ilimitados',
      'Agendamentos ilimitados',
      'Suporte dedicado',
      'Relatórios avançados',
      'API access',
    ],
    highlighted: false,
  },
] as const

function priceForCycle(plan: (typeof plans)[number], billingCycle: BillingCycle): number {
  if (billingCycle === 'yearly' && plan.yearlyPrice !== null) {
    return plan.yearlyPrice
  }
  return plan.price
}

export function PricingSection() {
  const [billingCycle, setBillingCycle] = useState<BillingCycle>('monthly')

  return (
    <section id="planos" className="bg-muted px-6 py-20">
      <div className="mx-auto max-w-5xl">
        <h2 className="text-center font-heading text-2xl font-bold text-foreground">
          Planos para todo tamanho de negócio
        </h2>
        <p className="mx-auto mt-3 max-w-xl text-center text-sm text-muted-foreground">
          Comece grátis e mude de plano quando seu negócio crescer.
        </p>

        {/* Toggle mensal / anual */}
        <div className="mt-8 flex justify-center">
          <div className="inline-flex items-center rounded-full border border-border bg-card p-1">
            <button
              type="button"
              onClick={() => setBillingCycle('monthly')}
              className={cn(
                'rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                billingCycle === 'monthly'
                  ? 'bg-muted text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground',
              )}
            >
              Mensal
            </button>
            <button
              type="button"
              onClick={() => setBillingCycle('yearly')}
              className={cn(
                'flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                billingCycle === 'yearly'
                  ? 'bg-muted text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground',
              )}
            >
              Anual
              <span className="rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-white">
                -17%
              </span>
            </button>
          </div>
        </div>

        <div className="mt-12 grid gap-8 sm:grid-cols-3">
          {plans.map((plan) => {
            const cycle: BillingCycle = plan.key === 'starter' ? 'monthly' : billingCycle
            const price = priceForCycle(plan, cycle)
            const isYearly = cycle === 'yearly' && plan.yearlyPrice !== null

            return (
              <div
                key={plan.key}
                className={cn(
                  'flex flex-col rounded-2xl border bg-card p-8 shadow-sm',
                  plan.highlighted ? 'border-primary ring-1 ring-primary' : 'border-border',
                )}
              >
                {plan.highlighted && (
                  <span className="mb-4 inline-flex w-fit items-center rounded-full bg-primary px-3 py-1 text-xs font-semibold text-white">
                    Mais popular
                  </span>
                )}
                <h3 className="text-lg font-semibold text-foreground">{plan.name}</h3>
                <p className="mt-1 text-sm text-muted-foreground">{plan.description}</p>
                <p className="mt-6">
                  <span className="font-heading text-3xl font-extrabold text-foreground">
                    {price === 0 ? 'Grátis' : formatPrice(price)}
                  </span>
                  {price > 0 && (
                    <span className="text-sm text-muted-foreground">{isYearly ? '/ano' : '/mês'}</span>
                  )}
                </p>
                {isYearly && (
                  <p className="mt-1 text-xs text-muted-foreground">
                    equivalente a {formatPrice(Math.round(price / 12))}/mês · 2 meses grátis
                  </p>
                )}
                <ul className="mt-6 space-y-3">
                  {plan.features.map((feature) => (
                    <li key={feature} className="flex items-start gap-2 text-sm text-foreground">
                      <Check className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                      {feature}
                    </li>
                  ))}
                </ul>
                <Link
                  href="/register"
                  className={cn(
                    'mt-8 rounded-full px-4 py-2.5 text-center text-sm font-semibold transition-colors',
                    plan.highlighted
                      ? 'bg-primary text-white hover:bg-primary/90'
                      : 'border border-border text-foreground hover:bg-muted',
                  )}
                >
                  Começar grátis
                </Link>
              </div>
            )
          })}
        </div>
      </div>
    </section>
  )
}
