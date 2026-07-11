import { test, expect } from '@playwright/test'
import {
  SALON_OWNER_EMAIL,
  SALON_OWNER_PASSWORD,
  SALON_SLUG,
  bookFirstAvailableSlot,
  login,
  registerClient,
  uniqueClient,
} from './helpers'

test.describe('Staff confirma o agendamento pelo dashboard', () => {
  test('cliente agenda e o dono do salão confirma na Agenda', async ({ browser }) => {
    // Sessão do cliente: registra e agenda um serviço.
    const clientContext = await browser.newContext()
    const clientPage = await clientContext.newPage()
    const client = uniqueClient('staffconfirm')
    await registerClient(clientPage, client, { redirect: `/${SALON_SLUG}/booking` })
    const booked = await bookFirstAvailableSlot(clientPage, SALON_SLUG, 2)
    await clientContext.close()

    // Sessão separada do dono do salão (não reaproveita o storage do cliente).
    const staffContext = await browser.newContext()
    const staffPage = await staffContext.newPage()
    await login(staffPage, SALON_OWNER_EMAIL, SALON_OWNER_PASSWORD)
    await staffPage.waitForURL((url) => url.pathname === `/${SALON_SLUG}/dashboard`)

    await staffPage.goto(`/${SALON_SLUG}/dashboard/schedule`)
    // Espera a primeira leva (sem filtro) terminar de carregar antes de mexer no
    // filtro de data — preenchê-lo cedo demais pode disparar antes da hidratação
    // do React terminar, e o `onChange` do input acaba não sendo capturado.
    await expect(staffPage.getByText('Carregando agendamentos...')).toHaveCount(0)
    await staffPage.getByLabel('Data').fill(booked.date)

    const row = staffPage.locator('li', { hasText: booked.serviceName })
    await expect(row).toBeVisible()
    await expect(row.getByText('Pendente')).toBeVisible()

    await row.getByRole('button', { name: 'Confirmar' }).click()

    await expect(row.getByText('Confirmado')).toBeVisible()
    await expect(row.getByText('Pendente')).toHaveCount(0)

    await staffContext.close()
  })
})
