import {usePage} from '@inertiajs/vue3'
import {Building2, ClipboardList, Database, LayoutPanelLeft, RadioTower, Users, Workflow, Zap} from 'lucide-vue-next'
import {computed} from 'vue'

export function useDashboardNavigation() {
    const page = usePage()

    const navigationItems = computed(() => [
        {
            label: 'Workspace',
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
            label: 'Users and Roles',
            icon: Users,
            href: route('users.index'),
            active: page.url.startsWith('/users'),
        },
        {
            label: 'Scenarios',
            icon: Workflow,
            href: route('scenarios'),
            active: page.url.startsWith('/scenarios') || page.url.startsWith('/scenario-versions/'),
        },
        {
            label: 'Projects',
            icon: Building2,
            href: route('projects.index'),
            active: page.url.startsWith('/projects'),
        },
        {
            label: 'Actions',
            icon: Zap,
            href: route('actions'),
            active: page.url.startsWith('/actions'),
        },
        {
            label: 'Proxy',
            icon: RadioTower,
            href: route('proxy.endpoints'),
            active: page.url.startsWith('/proxy'),
        },
        {
            label: 'Directories',
            icon: Database,
            href: route('directories'),
            active: page.url.startsWith('/directories'),
        },
    ])

    return {navigationItems}
}
