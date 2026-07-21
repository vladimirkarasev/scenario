import {expect, type Page} from '@playwright/test'

export async function login(page: Page): Promise<void> {
    await page.goto('/auth', {waitUntil: 'domcontentloaded'})
    await page.getByRole('button', {name: 'Войти через _token'}).click()

    await expect(page).toHaveURL(/\/scenarios$/)
    await expect.poll(() => page.evaluate(() => sessionStorage.getItem('access_token'))).not.toBeNull()
}
