import {expect, test} from '@playwright/test'
import {E2E_USER} from './helpers/auth'

test('пользователь авторизуется через dev login', async ({page}) => {
    await page.goto('/login', {waitUntil: 'domcontentloaded'})

    await expect(page.getByRole('heading', {name: 'Вход'})).toBeVisible()
    await page.getByLabel('Email').fill(E2E_USER.email)
    await page.getByLabel('Пароль').fill(E2E_USER.password)
    await page.getByRole('button', {name: 'Войти'}).click()

    await expect(page).toHaveURL(/\/scenarios$/)
    await expect.poll(() => page.evaluate(() => sessionStorage.getItem('access_token'))).toMatch(/^[A-Za-z0-9]+$/)
})

test('неверный пароль показывает ошибку', async ({page}) => {
    await page.goto('/login', {waitUntil: 'domcontentloaded'})
    await page.getByLabel('Email').fill(E2E_USER.email)
    await page.getByLabel('Пароль').fill('wrong-password')
    await page.getByRole('button', {name: 'Войти'}).click()

    await expect(page.getByText('Invalid credentials.')).toBeVisible()
    await expect(page).toHaveURL(/\/login$/)
})
