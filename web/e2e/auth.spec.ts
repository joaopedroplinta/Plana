import { test, expect } from '@playwright/test'
import { login, registerClient, uniqueClient } from './helpers'

test.describe('Registro e login de cliente', () => {
  test('cliente novo se registra e consegue fazer login em seguida', async ({ page }) => {
    const client = uniqueClient('registro')

    await registerClient(page, client)
    await expect(page).toHaveURL('/')

    const tokenAfterRegister = await page.evaluate(() => localStorage.getItem('token'))
    expect(tokenAfterRegister).toBeTruthy()

    // Simula uma nova sessão: limpa o token local (mas mantém a conta criada) e
    // faz login de novo com as mesmas credenciais.
    await page.evaluate(() => {
      localStorage.removeItem('token')
      document.cookie = 'token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT'
    })

    await login(page, client.email, client.password)
    await expect(page).toHaveURL('/')
    await expect(page.getByText('Erro ao fazer login')).toHaveCount(0)

    const tokenAfterLogin = await page.evaluate(() => localStorage.getItem('token'))
    expect(tokenAfterLogin).toBeTruthy()
  })
})
