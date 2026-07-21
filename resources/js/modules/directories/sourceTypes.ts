import {Cloud, Database, FileSpreadsheet, Globe} from 'lucide-vue-next'
import type {Component} from 'vue'
import type {SourceType} from '@/modules/directories/types/directory'

export interface SourceTypeMeta {
    id: SourceType
    label: string
    description: string
    icon: Component
}

export const SOURCE_TYPES: readonly SourceTypeMeta[] = [
    {id: 'manual', label: 'Вручную', description: 'Заполнять через интерфейс', icon: Database},
    {id: 'excel', label: 'Excel', description: 'Импорт из файла Excel или CSV', icon: FileSpreadsheet},
    {id: 'api', label: 'API', description: 'Периодическая синхронизация из прокси в БД', icon: Globe},
    {id: 'external', label: 'External', description: 'Виртуальный вид — данные всегда из прокси напрямую', icon: Cloud},
]
