export interface MockDirectoryField {
    key: string
    name: string
}

export interface MockDirectory {
    id: string
    name: string
    fields: MockDirectoryField[]
}

export const MOCK_DIRECTORIES: MockDirectory[] = [
    {
        id: 'dir-1', name: 'Должности', fields: [
            {key: 'name', name: 'Наименование'},
            {key: 'code', name: 'Код'},
            {key: 'level', name: 'Уровень'},
        ]
    },
    {
        id: 'dir-2', name: 'Подразделения', fields: [
            {key: 'name', name: 'Наименование'},
            {key: 'code', name: 'Код'},
        ]
    },
    {
        id: 'dir-3', name: 'Сотрудники', fields: [
            {key: 'full_name', name: 'ФИО'},
            {key: 'tab_number', name: 'Табельный номер'},
            {key: 'email', name: 'Email'},
            {key: 'phone', name: 'Телефон'},
        ]
    },
    {
        id: 'dir-4', name: 'Города', fields: [
            {key: 'name', name: 'Название'},
            {key: 'region', name: 'Регион'},
            {key: 'code', name: 'Код'},
        ]
    },
    {
        id: 'dir-5', name: 'Типы документов', fields: [
            {key: 'name', name: 'Название'},
            {key: 'code', name: 'Код'},
            {key: 'format', name: 'Формат'},
        ]
    },
]
