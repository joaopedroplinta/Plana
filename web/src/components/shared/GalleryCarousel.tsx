'use client'

import Autoplay from 'embla-carousel-autoplay'
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselPrevious,
  CarouselNext,
} from '@/components/ui/carousel'
import { assetUrl } from '@/lib/assets'
import type { GalleryImage } from '@/types/index'

export function GalleryCarousel({ gallery }: { gallery: GalleryImage[] }) {
  if (gallery.length === 0) return null

  return (
    <section className="mx-auto max-w-4xl px-6 pb-12">
      <h2 className="text-xl font-bold text-foreground">Nossos trabalhos</h2>
      <Carousel
        opts={{ loop: true, align: 'start' }}
        plugins={[Autoplay({ delay: 4000, stopOnInteraction: false })]}
        className="mt-6"
      >
        <CarouselContent>
          {gallery.map((image) => {
            const url = assetUrl(image.image_url)
            if (!url) return null
            return (
              <CarouselItem key={image.id} className="basis-full">
                <figure className="overflow-hidden rounded-xl border bg-card">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={url}
                    alt={image.caption ?? 'Trabalho do salão'}
                    className="aspect-video w-full object-cover"
                  />
                  {image.caption && (
                    <figcaption className="px-3 py-2 text-sm text-muted-foreground">
                      {image.caption}
                    </figcaption>
                  )}
                </figure>
              </CarouselItem>
            )
          })}
        </CarouselContent>
        <CarouselPrevious className="hidden sm:flex" />
        <CarouselNext className="hidden sm:flex" />
      </Carousel>
    </section>
  )
}
