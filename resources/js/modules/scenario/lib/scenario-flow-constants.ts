import {ScenarioContextKey} from '@/modules/scenario/types/scenario-context-key'

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
    {id: 'condition_else', value: '{{ isElse() }}'},
]
