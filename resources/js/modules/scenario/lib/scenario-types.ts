import {isContentNodeType} from '@/modules/scenario/lib/scenario-flow-document'
import type {ScenarioType} from '@/modules/scenario/types/scenario'

export const SCENARIO_TYPE_VALUES = ['colls', 'telegram', 'watsapp', 'call_bots'] as const

export const SCENARIO_TYPE_OPTIONS: ReadonlyArray<{value: ScenarioType; label: string}> = [
    {value: 'colls', label: 'Звонки'},
    {value: 'telegram', label: 'Телеграм'},
    {value: 'watsapp', label: 'Ватсап'},
    {value: 'call_bots', label: 'Боты звонков'},
]

export {isContentNodeType}
