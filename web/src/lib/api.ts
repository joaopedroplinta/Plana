import axios from 'axios'

export const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL ?? 'http://127.0.0.1:8000/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    // 401 em rota de auth (login errado, /me com token expirado) é tratado
    // pela própria página — redirecionar aqui apagaria a mensagem de erro.
    const isAuthRoute = error.config?.url?.includes('/auth/') ?? false

    if (error.response?.status === 401 && typeof window !== 'undefined' && !isAuthRoute) {
      localStorage.removeItem('token')
      document.cookie = 'token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT'
      const current = window.location.pathname + window.location.search
      window.location.href = `/login?redirect=${encodeURIComponent(current)}`
    }
    return Promise.reject(error)
  }
)
