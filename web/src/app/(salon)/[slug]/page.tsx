import { notFound } from 'next/navigation'
import { AtSign, Clock, MapPin, Phone } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { servicesService } from '@/services/services'
import { tenantsService } from '@/services/tenants'
import type { Service, Tenant } from '@/types/index'
import { formatDuration, formatPrice } from '@/lib/format'

interface SalonHomeProps {
  params: Promise<{ slug: string }>
}

export default async function SalonHomePage({ params }: SalonHomeProps) {
  const { slug } = await params

  let tenant: Tenant
  let services: Service[] = []

  try {
    const [tenantRes, servicesRes] = await Promise.all([
      tenantsService.show(slug),
      servicesService.list(slug),
    ])
    tenant = tenantRes.data.data
    services = servicesRes.data.data
  } catch {
    notFound()
  }

  const whatsappDigits = tenant.whatsapp?.replace(/\D/g, '')

  return (
    <div className="flex-1">
      {/* Hero */}
      <section className="border-b bg-gradient-to-b from-indigo-50 to-white px-6 py-16 text-center">
        <div className="mx-auto max-w-2xl">
          <h1 className="text-4xl font-bold text-gray-900">{tenant.name}</h1>
          {tenant.description && (
            <p className="mt-4 text-base text-gray-600">{tenant.description}</p>
          )}
          <a
            href={`/${slug}/booking`}
            className="mt-8 inline-block rounded-full bg-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow hover:bg-indigo-500 transition-colors"
          >
            Agendar horário
          </a>

          {(tenant.address || tenant.phone || whatsappDigits || tenant.instagram) && (
            <div className="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-gray-500">
              {tenant.address && (
                <span className="flex items-center gap-1.5">
                  <MapPin className="h-4 w-4 text-indigo-400" />
                  {tenant.address}
                </span>
              )}
              {tenant.phone && (
                <span className="flex items-center gap-1.5">
                  <Phone className="h-4 w-4 text-indigo-400" />
                  {tenant.phone}
                </span>
              )}
              {whatsappDigits && (
                <a
                  href={`https://wa.me/${whatsappDigits}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-1.5 text-green-600 hover:underline"
                >
                  <Phone className="h-4 w-4" />
                  WhatsApp
                </a>
              )}
              {tenant.instagram && (
                <a
                  href={`https://instagram.com/${tenant.instagram.replace(/^@/, '')}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-1.5 text-pink-600 hover:underline"
                >
                  <AtSign className="h-4 w-4" />
                  {tenant.instagram.replace(/^@/, '')}
                </a>
              )}
            </div>
          )}
        </div>
      </section>

      {/* Serviços */}
      <section className="mx-auto max-w-4xl px-6 py-12">
        <h2 className="text-xl font-bold text-gray-900">Serviços</h2>
        {services.length === 0 ? (
          <p className="mt-6 rounded-xl border bg-gray-50 py-12 text-center text-sm text-gray-400">
            Nenhum serviço cadastrado ainda.
          </p>
        ) : (
          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            {services.map((service) => (
              <a
                key={service.id}
                href={`/${slug}/booking`}
                className="group rounded-xl border bg-white p-5 transition-all hover:border-indigo-300 hover:shadow-sm"
              >
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">
                      {service.name}
                    </p>
                    {service.description && (
                      <p className="mt-1 text-sm text-gray-500 line-clamp-2">
                        {service.description}
                      </p>
                    )}
                  </div>
                  <p className="whitespace-nowrap text-lg font-bold text-indigo-600">
                    {formatPrice(service.price)}
                  </p>
                </div>
                <Badge variant="secondary" className="mt-3 gap-1 text-xs">
                  <Clock className="h-3 w-3" />
                  {formatDuration(service.duration_minutes)}
                </Badge>
              </a>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
