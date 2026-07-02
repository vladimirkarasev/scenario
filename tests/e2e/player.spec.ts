import {expect, test} from '@playwright/test'
import {login} from './helpers/auth'

const SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000001'
const CONDITION_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000010'
const VARIABLE_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000020'

test('пользователь проходит опрос от формы до завершения', async ({page}) => {
    await login(page)
    await page.goto(`/workspace/scenario/${SCENARIO_ID}`, {waitUntil: 'domcontentloaded'})

    await expect(page.getByRole('heading', {name: 'Данные участника'})).toBeVisible()
    await page.getByLabel('Имя участника').fill('Иван Петров')
    await page.getByLabel('Email участника').fill('ivan@example.test')
    await page.getByRole('button', {name: 'Далее'}).click()

    await expect(page.getByRole('heading', {name: 'Опрос завершён'})).toBeVisible()
    await expect(page.getByText('Спасибо, Иван Петров!')).toBeVisible()
})

test('player показывает серверную валидацию обязательных полей', async ({page}) => {
    await login(page)
    await page.goto(`/workspace/scenario/${SCENARIO_ID}`, {waitUntil: 'domcontentloaded'})

    await page.getByRole('button', {name: 'Далее'}).click()

    await expect(page.getByText('Поле обязательно для заполнения')).toHaveCount(2)
    await expect(page.getByRole('heading', {name: 'Данные участника'})).toBeVisible()
})

test('математический condition ведёт через action в ветку крупного заказа', async ({page}) => {
    await login(page)
    await page.goto(`/workspace/scenario/${CONDITION_SCENARIO_ID}`, {waitUntil: 'domcontentloaded'})

    await expect(page.getByRole('heading', {name: 'Расчёт заказа'})).toBeVisible()
    const numbers = page.locator('input[placeholder="0"]')
    await numbers.nth(0).fill('30')
    await numbers.nth(1).fill('4')
    await numbers.nth(2).fill('15')
    await page.getByRole('button', {name: 'Далее'}).click()

    await expect(page.getByRole('heading', {name: 'Крупный заказ'})).toBeVisible()
    await expect(page.getByText('Сумма: 105')).toBeVisible()
})

test('математический condition использует fallback-ветку', async ({page}) => {
    await login(page)
    await page.goto(`/workspace/scenario/${CONDITION_SCENARIO_ID}`, {waitUntil: 'domcontentloaded'})

    const numbers = page.locator('input[placeholder="0"]')
    await numbers.nth(0).fill('10')
    await numbers.nth(1).fill('3')
    await numbers.nth(2).fill('5')
    await page.getByRole('button', {name: 'Далее'}).click()

    await expect(page.getByRole('heading', {name: 'Обычный заказ'})).toBeVisible()
    await expect(page.getByText('Сумма меньше 100')).toBeVisible()
})

test('player подставляет переменные в шаг, текст и placeholder', async ({page}) => {
    await login(page)
    await page.goto(`/workspace/scenario/${VARIABLE_SCENARIO_ID}`, {waitUntil: 'domcontentloaded'})

    await expect(page.getByRole('heading', {name: 'Данные участника'})).toBeVisible()
    await page.getByLabel('Имя участника').fill('Иван Петров')
    await page.getByLabel('Email участника').fill('ivan@example.test')
    await page.getByRole('button', {name: 'Далее'}).click()

    await expect(page.getByRole('heading', {name: 'Подтверждение для Иван Петров'})).toBeVisible()
    await expect(page.getByText('Мы отправили письмо на ivan@example.test')).toBeVisible()

    const note = page.getByLabel('Комментарий для Иван Петров')
    await expect(note).toHaveAttribute('placeholder', 'Напишите сообщение для Иван Петров')
})

test('player завершает сценарий с подстановкой переменных из предыдущего шага', async ({page}) => {
    await login(page)
    await page.goto(`/workspace/scenario/${VARIABLE_SCENARIO_ID}`, {waitUntil: 'domcontentloaded'})

    await page.getByLabel('Имя участника').fill('Иван Петров')
    await page.getByLabel('Email участника').fill('ivan@example.test')
    await page.getByRole('button', {name: 'Далее'}).click()
    await page.getByRole('button', {name: 'Далее'}).click()

    await expect(page.getByRole('heading', {name: 'Финал для Иван Петров'})).toBeVisible()
    await expect(page.getByText('Спасибо, Иван Петров! Письмо ушло на ivan@example.test.')).toBeVisible()
})
