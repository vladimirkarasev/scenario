import {usePage} from '@inertiajs/vue3'
import {Building2, ClipboardList, Database, LayoutPanelLeft, PlugZap, Users, Workflow, Zap} from 'lucide-vue-next'
import {computed} from 'vue'

export function useDashboardNavigation() {
    const page = usePage()

    const navigationItems = computed(() => [
        {
            label: 'Рабочая область',
            icon: LayoutPanelLeft,
            href: route('workspace'),
            active: page.url.startsWith('/workspace'),
        },
        {
            label: 'Опросы',
            icon: ClipboardList,
            href: route('surveys'),
            active: page.url.startsWith('/surveys'),
        },
        {
            label: 'Пользователи и роли',
            icon: Users,
            href: route('users.index'),
            active: page.url.startsWith('/users'),
        },
        {
            label: 'Сценарии',
            icon: Workflow,
            href: route('scenarios'),
            active: page.url.startsWith('/scenarios') || page.url.startsWith('/scenario-versions/'),
        },
        {
            label: 'Проекты',
            icon: Building2,
            href: route('projects.index'),
            active: page.url.startsWith('/projects'),
        },
        {
            label: 'Действия',
            icon: Zap,
            href: route('actions'),
            active: page.url.startsWith('/actions'),
        },
        {
            label: 'Интеграции',
            icon: PlugZap,
            href: route('proxy.endpoints'),
            active: page.url.startsWith('/proxy'),
        },
        {
            label: 'Справочники',
            icon: Database,
            href: route('directories'),
            active: page.url.startsWith('/directories'),
        },
    ])

    return {navigationItems}
}
