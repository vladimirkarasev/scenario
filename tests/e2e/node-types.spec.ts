import {expect, type Page, type Response, test} from '@playwright/test'
import {login} from './helpers/auth'

const SIMPLE_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000001'
const CONDITION_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000010'
const VARIABLE_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000020'
const MANUAL_CONDITION_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000030'
const ACTION_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000040'
const LINK_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000050'
const LINK_TARGET_SCENARIO_ID = '019f0e2e-0000-7000-a000-000000000052'

interface RunStep {
    node_id: string
    node_type: string
    exited_at: string | null
}

interface RunPayload {
    scenario_id: string
    current_node_id: string
    status: string
    context: Record<string, unknown>
    steps: RunStep[]
}

async function openScenario(page: Page, scenarioId: string): Promise<RunPayload> {
    const responsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST'
        && new URL(response.url()).pathname === '/api/scenarios/runner',
    )

    await page.goto(`/workspace/scenario/${scenarioId}`, {waitUntil: 'domcontentloaded'})

    return runFromResponse(await responsePromise)
}

async function runFromResponse(response: Response): Promise<RunPayload> {
    expect(response.ok()).toBeTruthy()
    const body = await response.json() as {run: RunPayload}
    return body.run
}

async function continueResponse(page: Page, action: () => Promise<void>): Promise<RunPayload> {
    const responsePromise = page.waitForResponse((response) =>
        response.request().method() === 'POST'
        && /\/api\/scenarios\/runner\/[^/]+\/continue$/.test(new URL(response.url()).pathname),
    )

    await action()

    return runFromResponse(await responsePromise)
}

test.beforeEach(async ({page}) => {
    await login(page)
})

test.describe('start node', () => {
    test('автоматически переводит прогон к первому интерактивному узлу', async ({page}) => {
        const run = await openScenario(page, SIMPLE_SCENARIO_ID)

        expect(run.current_node_id).toBe('participant')
        expect(run.steps).toEqual(expect.arrayContaining([
            expect.objectContaining({node_id: 'start', node_type: 'start'}),
        ]))
        await expect(page.getByRole('heading', {name: 'Данные участника'})).toBeVisible()
    })

    test('фиксируется в истории до автоматических action-узлов', async ({page}) => {
        const run = await openScenario(page, ACTION_SCENARIO_ID)

        const nodeTypes = run.steps.map((step) => step.node_type)
        expect(nodeTypes).toEqual(expect.arrayContaining(['start', 'action', 'block']))
        expect(run.current_node_id).toBe('confirmation')
    })
})

test.describe('block node', () => {
    test('сохраняет введённые данные в контекст прогона', async ({page}) => {
        await openScenario(page, SIMPLE_SCENARIO_ID)
        await page.getByLabel('Имя участника').fill('Анна')
        await page.getByLabel('Email участника').fill('anna@example.test')

        const run = await continueResponse(page, () => page.getByRole('button', {name: 'Далее'}).click())

        expect(run.context.participant).toEqual({
            participant_name: 'Анна',
            participant_email: 'anna@example.test',
        })
    })

    test('не пропускает обязательные поля', async ({page}) => {
        await openScenario(page, SIMPLE_SCENARIO_ID)

        await page.getByRole('button', {name: 'Далее'}).click()

        await expect(page.getByText('Поле обязательно для заполнения')).toHaveCount(2)
        await expect(page.getByRole('heading', {name: 'Данные участника'})).toBeVisible()
    })
})

test.describe('condition node', () => {
    test('ручное условие выбирает первую ветку', async ({page}) => {
        await openScenario(page, MANUAL_CONDITION_SCENARIO_ID)

        await expect(page.getByRole('heading', {name: 'Какой маршрут выбрать?'})).toBeVisible()
        await page.getByRole('button', {name: 'Быстрый маршрут'}).click()

        await expect(page.getByRole('heading', {name: 'Быстрый маршрут завершён'})).toBeVisible()
        await expect(page.getByText('Выбран короткий путь.')).toBeVisible()
    })

    test('ручное условие выбирает альтернативную ветку', async ({page}) => {
        await openScenario(page, MANUAL_CONDITION_SCENARIO_ID)
        await page.getByRole('button', {name: 'Подробный маршрут'}).click()

        await expect(page.getByRole('heading', {name: 'Подробный маршрут завершён'})).toBeVisible()
        await expect(page.getByText('Выбран подробный путь.')).toBeVisible()
    })
})

test.describe('action node', () => {
    test('fire-and-forget action выполняется автоматически перед блоком', async ({page}) => {
        const run = await openScenario(page, ACTION_SCENARIO_ID)
        const actionStep = run.steps.find((step) => step.node_id === 'prepare_action')

        expect(actionStep).toMatchObject({
            node_type: 'action',
        })
        expect(actionStep?.exited_at).not.toBeNull()
        await expect(page.getByRole('heading', {name: 'Подтверждение действия'})).toBeVisible()
    })

    test('wait_for_result action без стадий не блокирует завершение', async ({page}) => {
        await openScenario(page, ACTION_SCENARIO_ID)
        await page.getByLabel('Комментарий').fill('готово')

        const run = await continueResponse(page, () => page.getByRole('button', {name: 'Далее'}).click())

        expect(run.status).toBe('completed')
        expect(run.steps).toEqual(expect.arrayContaining([
            expect.objectContaining({node_id: 'finish_action', node_type: 'action'}),
        ]))
        await expect(page.getByRole('heading', {name: 'Действия завершены'})).toBeVisible()
        await expect(page.getByText('Комментарий: готово')).toBeVisible()
    })
})

test.describe('end node', () => {
    test('завершает прогон и отображает итоговый текст', async ({page}) => {
        await openScenario(page, SIMPLE_SCENARIO_ID)
        await page.getByLabel('Имя участника').fill('Иван')
        await page.getByLabel('Email участника').fill('ivan@example.test')

        const run = await continueResponse(page, () => page.getByRole('button', {name: 'Далее'}).click())

        expect(run.status).toBe('completed')
        expect(run.current_node_id).toBe('end')
        await expect(page.getByText('Спасибо, Иван!')).toBeVisible()
    })

    test('отображает end выбранной автоматическим condition ветки', async ({page}) => {
        await openScenario(page, CONDITION_SCENARIO_ID)
        const numbers = page.locator('input[placeholder="0"]')
        await numbers.nth(0).fill('10')
        await numbers.nth(1).fill('2')
        await numbers.nth(2).fill('0')
        await page.getByRole('button', {name: 'Далее'}).click()

        await expect(page.getByRole('heading', {name: 'Обычный заказ'})).toBeVisible()
        await expect(page.getByText('Сумма меньше 100')).toBeVisible()
    })
})

test.describe('scenario_link node', () => {
    test('переключает прогон на целевой сценарий и его первый блок', async ({page}) => {
        const run = await openScenario(page, LINK_SCENARIO_ID)

        expect(run.scenario_id).toBe(LINK_TARGET_SCENARIO_ID)
        expect(run.current_node_id).toBe('linked_block')
        expect(run.steps).toEqual(expect.arrayContaining([
            expect.objectContaining({node_id: 'scenario_link', node_type: 'scenario_link'}),
            expect.objectContaining({node_id: 'target_start', node_type: 'start'}),
        ]))
        await expect(page.getByRole('heading', {name: 'Шаг связанного сценария'})).toBeVisible()
    })

    test('продолжает связанный сценарий до его end с общим контекстом', async ({page}) => {
        await openScenario(page, LINK_SCENARIO_ID)
        await page.getByLabel('Значение связанного сценария').fill('данные перехода')

        const run = await continueResponse(page, () => page.getByRole('button', {name: 'Далее'}).click())

        expect(run.scenario_id).toBe(LINK_TARGET_SCENARIO_ID)
        expect(run.status).toBe('completed')
        expect(run.context.linked_block).toEqual({linked_value: 'данные перехода'})
        await expect(page.getByRole('heading', {name: 'Связанный сценарий завершён'})).toBeVisible()
        await expect(page.getByText('Получено: данные перехода')).toBeVisible()
    })
})

test.describe('кнопка отмена', () => {
    test('возвращает с последующего блока к предыдущему и сохраняет введённые значения', async ({page}) => {
        await openScenario(page, VARIABLE_SCENARIO_ID)
        await page.getByLabel('Имя участника').fill('Мария')
        await page.getByLabel('Email участника').fill('maria@example.test')
        await page.getByRole('button', {name: 'Далее'}).click()
        await expect(page.getByRole('heading', {name: 'Подтверждение для Мария'})).toBeVisible()

        const jumpResponse = page.waitForResponse((response) =>
            response.request().method() === 'POST'
            && /\/api\/scenarios\/runner\/[^/]+\/jump$/.test(new URL(response.url()).pathname),
        )
        await page.getByRole('button', {name: 'Отмена'}).click()
        const run = await runFromResponse(await jumpResponse)

        expect(run.current_node_id).toBe('participant')
        expect(run.status).toBe('active')
        await expect(page.getByRole('heading', {name: 'Данные участника'})).toBeVisible()
        await expect(page.getByLabel('Имя участника')).toHaveValue('Мария')
        await expect(page.getByLabel('Email участника')).toHaveValue('maria@example.test')
    })

    test('возвращает с end к ручному условию и позволяет выбрать другую ветку', async ({page}) => {
        await openScenario(page, MANUAL_CONDITION_SCENARIO_ID)
        await page.getByRole('button', {name: 'Быстрый маршрут'}).click()
        await expect(page.getByRole('heading', {name: 'Быстрый маршрут завершён'})).toBeVisible()

        const jumpResponse = page.waitForResponse((response) =>
            response.request().method() === 'POST'
            && /\/api\/scenarios\/runner\/[^/]+\/jump$/.test(new URL(response.url()).pathname),
        )
        await page.getByRole('button', {name: 'Отмена'}).click()
        const run = await runFromResponse(await jumpResponse)

        expect(run.current_node_id).toBe('choice')
        expect(run.status).toBe('active')
        await expect(page.getByRole('heading', {name: 'Какой маршрут выбрать?'})).toBeVisible()

        await page.getByRole('button', {name: 'Подробный маршрут'}).click()
        await expect(page.getByRole('heading', {name: 'Подробный маршрут завершён'})).toBeVisible()
    })
})
