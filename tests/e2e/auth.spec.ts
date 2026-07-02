import {expect, test} from '@playwright/test'
import {login} from './helpers/auth'

test('пользователь авторизуется через dev /auth (_token flow)', async ({page}) => {
    await login(page)

    await expect(page).toHaveURL(/\/scenarios$/)
    await expect.poll(() => page.evaluate(() => sessionStorage.getItem('access_token'))).toMatch(/^[A-Za-z0-9]+$/)
})
