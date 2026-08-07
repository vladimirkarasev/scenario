import {ScenarioContextKey} from '@/modules/scenario/types/scenario-context-key'

export interface FlowPaletteItem {
    type: 'start' | 'block' | 'action' | 'condition' | 'end' | 'scenario_link'
    label: string
}

export const PALETTE_ITEMS: FlowPaletteItem[] = [
    {type: 'start', label: 'Начало'},
    {type: 'block', label: 'Шаг'},
    {type: 'action', label: 'Действие'},
    {type: 'condition', label: 'Условие'},
    {type: 'end', label: 'Конец'},
    {type: 'scenario_link', label: 'Переход'},
]

export interface FlowUserVariable {
    id: string
    name: string
    label: string
}

export const USER_VARIABLES: FlowUserVariable[] = [
    {id: 'user.name', name: 'user.name', label: 'Имя'},
    {id: 'user.fio', name: 'user.fio', label: 'ФИО'},
    {id: 'user.email', name: 'user.email', label: 'Email'},
    {id: 'user.phone', name: 'user.phone', label: 'Телефон'},
    {id: 'user.auth_date', name: 'user.auth_date', label: 'Дата авторизации'},
]

export interface FlowLogicalVariable {
    id: string
    value: string
}

export const LOGICAL_VARIABLES: FlowLogicalVariable[] = [
    {id: 'condition_value', value: `{{ ${ScenarioContextKey.Condition}.value }}`},
    {id: 'logical_yes', value: 'Да'},
    {id: 'logical_no', value: 'Нет'},
    {id: 'logical_else', value: 'Иначе'},
    {id: 'logical_empty', value: 'Пусто'},
]
