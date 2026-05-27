'use client'

import { useState, useEffect, useCallback } from 'react'
import type { User } from '@/types/index'
import { authService } from '@/services/auth'

interface UseAuthReturn {
  user: User | null
  token: string | null
  isLoading: boolean
  isAuthenticated: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
}

export function useAuth(): UseAuthReturn {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(() => {
    if (typeof window === 'undefined') return null
    return localStorage.getItem('token')
  })
  const [isLoading, setIsLoading] = useState(() => {
    if (typeof window === 'undefined') return false
    return !!localStorage.getItem('token')
  })

  const saveToken = useCallback((newToken: string) => {
    localStorage.setItem('token', newToken)
    document.cookie = `token=${newToken}; path=/; SameSite=Lax`
    setToken(newToken)
  }, [])

  const clearToken = useCallback(() => {
    localStorage.removeItem('token')
    document.cookie = 'token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT'
    setToken(null)
    setUser(null)
  }, [])

  useEffect(() => {
    const storedToken = localStorage.getItem('token')
    if (!storedToken) return

    authService
      .me()
      .then((response) => {
        setUser(response.data.data)
      })
      .catch(() => {
        clearToken()
      })
      .finally(() => {
        setIsLoading(false)
      })
  }, [clearToken])

  const login = useCallback(
    async (email: string, password: string) => {
      const response = await authService.login(email, password)
      const { token: newToken, user: loggedUser } = response.data
      saveToken(newToken)
      setUser(loggedUser)
    },
    [saveToken],
  )

  const logout = useCallback(async () => {
    try {
      await authService.logout()
    } finally {
      clearToken()
    }
  }, [clearToken])

  return {
    user,
    token,
    isLoading,
    isAuthenticated: !!token && !!user,
    login,
    logout,
  }
}
