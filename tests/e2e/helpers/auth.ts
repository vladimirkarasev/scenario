import {expect, type Page} from '@playwright/test'

export const E2E_USER = {
    email: 'e2e@scenario.local',
    password: 'e2e-password',
}

export async function login(page: Page): Promise<void> {
    await page.goto('/login', {waitUntil: 'domcontentloaded'})
    await page.getByLabel('Email').fill(E2E_USER.email)
    await page.getByLabel('Пароль').fill(E2E_USER.password)
    await page.getByRole('button', {name: 'Войти'}).click()

    await expect(page).toHaveURL(/\/scenarios$/)
    await expect.poll(() => page.evaluate(() => sessionStorage.getItem('access_token'))).not.toBeNull()
}
