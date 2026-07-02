import {expect, type Page} from '@playwright/test'

// Вход через dev-страницу /auth (симуляция embed-входа): создаёт пользователя
// demo-проекта, выпускает одноразовый _token и редиректит на /scenarios, где
// app.ts обменивает его на пару токенов. Форма предзаполнена дефолтами seeder-а.
export async function login(page: Page): Promise<void> {
    await page.goto('/auth', {waitUntil: 'domcontentloaded'})
    await page.getByRole('button', {name: 'Войти через _token'}).click()

    await expect(page).toHaveURL(/\/scenarios$/)
    await expect.poll(() => page.evaluate(() => sessionStorage.getItem('access_token'))).not.toBeNull()
}
