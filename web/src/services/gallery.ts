import { api } from '@/lib/api'
import type { GalleryImage } from '@/types/index'

export const galleryService = {
  list: (slug: string) =>
    api.get<{ data: GalleryImage[] }>(`/negocio/${slug}/gallery`),

  add: (slug: string, file: File, caption?: string) => {
    const form = new FormData()
    form.append('image', file)
    if (caption) form.append('caption', caption)
    return api.post<{ data: GalleryImage }>(`/negocio/${slug}/gallery`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
  },

  remove: (slug: string, id: string) =>
    api.delete(`/negocio/${slug}/gallery/${id}`),
}
