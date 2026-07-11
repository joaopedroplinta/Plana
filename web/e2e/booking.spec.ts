import { test, expect } from '@playwright/test'
import { SALON_SLUG, bookFirstAvailableSlot, registerClient, uniqueClient } from './helpers'

test.describe('Cliente agenda, vê em Minha Conta e cancela', () => {
  test('agenda um serviço, confere em "meus agendamentos" e cancela', async ({ page }) => {
    const client = uniqueClient('booking')
    // Replica o fluxo real: agendar exige login, então o registro já nasce
    // com o redirect de volta pra página de booking.
    await registerClient(page, client, { redirect: `/${SALON_SLUG}/booking` })

    const booked = await bookFirstAvailableSlot(page, SALON_SLUG, 1)

    await page.getByRole('button', { name: 'Ver meus agendamentos' }).click()
    await expect(page).toHaveURL(`/${SALON_SLUG}/minha-conta`)

    await expect(page.getByText(booked.serviceName, { exact: true })).toBeVisible()
    await expect(page.getByText('Aguardando confirmação')).toBeVisible()

    const cancelButton = page.getByRole('button', { name: 'Cancelar' })
    await expect(cancelButton).toBeVisible()
    await cancelButton.click()

    // AlertDialog de confirmação (não window.confirm nativo).
    const dialog = page.getByRole('alertdialog')
    await expect(dialog).toBeVisible()
    await expect(dialog.getByText('Tem certeza que deseja cancelar este agendamento?')).toBeVisible()
    await dialog.getByRole('button', { name: 'Cancelar agendamento' }).click()

    await expect(page.getByText('Cancelado')).toBeVisible()
    await expect(page.getByRole('button', { name: 'Cancelar' })).toHaveCount(0)
  })
})
