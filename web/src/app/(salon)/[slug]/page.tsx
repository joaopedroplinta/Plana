import { notFound } from 'next/navigation'
import { AtSign, Clock, MapPin, Phone } from 'lucide-react'
import { PackagesSection } from '@/components/shared/PackagesSection'
import { Badge } from '@/components/ui/badge'
import { packagesService } from '@/services/packages'
import { servicesService } from '@/services/services'
import { tenantsService } from '@/services/tenants'
import { businessHoursService } from '@/services/businessHours'
import { galleryService } from '@/services/gallery'
import type { BusinessHour, GalleryImage, Service, ServicePackage, Tenant } from '@/types/index'
import { formatDuration, formatPrice } from '@/lib/format'
import { assetUrl } from '@/lib/assets'

const DAY_LABELS = ['Domingo', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado']
const DAY_ORDER = [1, 2, 3, 4, 5, 6, 0]

interface SalonHomeProps {
  params: Promise<{ slug: string }>
}

export default async function SalonHomePage({ params }: SalonHomeProps) {
  const { slug } = await params

  let tenant: Tenant
  let services: Service[] = []
  let packages: ServicePackage[] = []

  try {
    const [tenantRes, servicesRes, packagesRes] = await Promise.all([
      tenantsService.show(slug),
      servicesService.list(slug),
      packagesService.list(slug),
    ])
    tenant = tenantRes.data.data
    services = servicesRes.data.data
    packages = packagesRes.data.data
  } catch {
    notFound()
  }

  // Horário de funcionamento é opcional — nunca deve derrubar a página.
  let businessHours: BusinessHour[] = []
  try {
    businessHours = (await businessHoursService.list(slug)).data.data
  } catch {
    businessHours = []
  }

  // Galeria de atendimentos é opcional — nunca deve derrubar a página.
  let gallery: GalleryImage[] = []
  try {
    gallery = (await galleryService.list(slug)).data.data
  } catch {
    gallery = []
  }

  const whatsappDigits = tenant.whatsapp?.replace(/\D/g, '')
  const logoUrl = assetUrl(tenant.logo_url)

  // Cor da marca sobrescreve --primary (Tailwind v4), fazendo botões e acentos
  // herdarem a identidade do salão. Sem cor definida, mantém o tema padrão.
  const brandStyle = tenant.brand_color
    ? ({ '--primary': tenant.brand_color } as React.CSSProperties)
    : undefined

  return (
    <div className="flex-1" style={brandStyle}>
      {/* Hero */}
      <section className="border-b bg-gradient-to-b from-secondary to-background dark:from-primary/10 px-6 py-16 text-center">
        <div className="mx-auto max-w-2xl">
          {logoUrl && (
            <>
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={logoUrl}
                alt={`Logo ${tenant.name}`}
                className="mx-auto mb-6 h-24 w-auto object-contain"
              />
            </>
          )}
          <h1 className="text-4xl font-bold text-foreground">{tenant.name}</h1>
          {tenant.description && (
            <p className="mt-4 text-base text-muted-foreground">{tenant.description}</p>
          )}
          <a
            href={`/${slug}/booking`}
            className="mt-8 inline-block rounded-full bg-primary px-8 py-3 text-sm font-semibold text-white shadow hover:bg-primary/90 transition-colors"
          >
            Agendar horário
          </a>

          {(tenant.address || tenant.phone || whatsappDigits || tenant.instagram) && (
            <div className="mt-8 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-muted-foreground">
              {tenant.address && (
                <span className="flex items-center gap-1.5">
                  <MapPin className="h-4 w-4 text-primary/70" />
                  {tenant.address}
                </span>
              )}
              {tenant.phone && (
                <span className="flex items-center gap-1.5">
                  <Phone className="h-4 w-4 text-primary/70" />
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
        <h2 className="text-xl font-bold text-foreground">Serviços</h2>
        {services.length === 0 ? (
          <p className="mt-6 rounded-xl border bg-muted py-12 text-center text-sm text-muted-foreground">
            Nenhum serviço cadastrado ainda.
          </p>
        ) : (
          <div className="mt-6 grid gap-4 sm:grid-cols-2">
            {services.map((service) => {
              const imageUrl = assetUrl(service.image_url)
              return (
              <a
                key={service.id}
                href={`/${slug}/booking`}
                className="group overflow-hidden rounded-xl border bg-card transition-all hover:border-primary/60 hover:shadow-sm"
              >
                {imageUrl && (
                  <>
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={imageUrl}
                      alt={service.name}
                      className="aspect-video w-full object-cover transition-transform group-hover:scale-105"
                    />
                  </>
                )}
                <div className="p-5">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <p className="font-semibold text-foreground group-hover:text-primary transition-colors">
                      {service.name}
                    </p>
                    {service.description && (
                      <p className="mt-1 text-sm text-muted-foreground line-clamp-2">
                        {service.description}
                      </p>
                    )}
                  </div>
                  <p className="whitespace-nowrap text-lg font-bold text-primary">
                    {formatPrice(service.price)}
                  </p>
                </div>
                <Badge variant="secondary" className="mt-3 gap-1 text-xs">
                  <Clock className="h-3 w-3" />
                  {formatDuration(service.duration_minutes)}
                </Badge>
                </div>
              </a>
              )
            })}
          </div>
        )}
      </section>

      <PackagesSection slug={slug} packages={packages} />

      {/* Galeria de atendimentos */}
      {gallery.length > 0 && (
        <section className="mx-auto max-w-4xl px-6 pb-12">
          <h2 className="text-xl font-bold text-foreground">Nossos trabalhos</h2>
          <div className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
            {gallery.map((image) => {
              const url = assetUrl(image.image_url)
              if (!url) return null
              return (
                <figure key={image.id} className="overflow-hidden rounded-xl border bg-card">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={url}
                    alt={image.caption ?? 'Trabalho do salão'}
                    className="aspect-square w-full object-cover transition-transform hover:scale-105"
                  />
                  {image.caption && (
                    <figcaption className="px-3 py-2 text-xs text-muted-foreground">
                      {image.caption}
                    </figcaption>
                  )}
                </figure>
              )
            })}
          </div>
        </section>
      )}

      {/* Horário de funcionamento */}
      {businessHours.length > 0 && (
        <section className="mx-auto max-w-4xl px-6 pb-12">
          <h2 className="flex items-center gap-2 text-xl font-bold text-foreground">
            <Clock className="h-5 w-5 text-primary/70" />
            Horário de funcionamento
          </h2>
          <div className="mt-6 max-w-md divide-y rounded-xl border bg-card">
            {DAY_ORDER.map((dow) => {
              const day = businessHours.find((h) => h.day_of_week === dow)
              const open = day?.is_open && day.open_time && day.close_time
              return (
                <div key={dow} className="flex items-center justify-between px-5 py-2.5 text-sm">
                  <span className="text-foreground">{DAY_LABELS[dow]}</span>
                  {open ? (
                    <span className="font-medium text-foreground">
                      {day!.open_time} às {day!.close_time}
                    </span>
                  ) : (
                    <span className="text-muted-foreground">Fechado</span>
                  )}
                </div>
              )
            })}
          </div>
        </section>
      )}
    </div>
  )
}
