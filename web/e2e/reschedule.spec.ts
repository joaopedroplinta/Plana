import { test, expect } from '@playwright/test'
import { SALON_SLUG, bookFirstAvailableSlot, bookingDate, registerClient, uniqueClient } from './helpers'

test.describe('Cliente remarca um agendamento', () => {
  test('agenda e depois remarca pra uma nova data/horário', async ({ page }) => {
    const client = uniqueClient('reschedule')
    await registerClient(page, client, { redirect: `/${SALON_SLUG}/booking` })
    await bookFirstAvailableSlot(page, SALON_SLUG, 3)

    await page.getByRole('button', { name: 'Ver meus agendamentos' }).click()
    await expect(page).toHaveURL(`/${SALON_SLUG}/minha-conta`)

    const card = page.getByTestId('appointment-card')
    await expect(card).toBeVisible()
    const textBeforeReschedule = await card.innerText()

    await card.getByRole('button', { name: 'Remarcar' }).click()

    const dialog = page.getByRole('dialog')
    await expect(dialog).toBeVisible()

    // Semana diferente da usada pra criar o agendamento — evita disputar o
    // mesmo horário e garante que a data realmente mudou.
    const newDate = bookingDate(4)
    await dialog.getByLabel('Nova data').fill(newDate)
    await dialog.getByRole('button', { name: 'Ver horários' }).click()

    const slotButton = dialog.getByRole('button', { name: /^\d{2}:\d{2}$/ }).first()
    await expect(slotButton).toBeVisible()
    await slotButton.click()

    await expect(dialog).toBeHidden()

    // Remarcação pelo cliente mantém o agendamento pendente (o salão precisa
    // reconfirmar o novo horário) e o card reflete a nova data/hora.
    await expect(card.getByText('Aguardando confirmação')).toBeVisible()
    await expect(card).not.toHaveText(textBeforeReschedule)
  })
})
