import '../css/app.css';
import './bootstrap';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

import {createInertiaApp, router} from '@inertiajs/vue3';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {createApp, defineComponent, h, type App as VueApp, type Component} from 'vue';
import {createPinia} from 'pinia';
import {ZiggyVue} from '../../vendor/tightenco/ziggy';
import {Toaster} from 'vue-sonner';
import 'vue-sonner/style.css';
import {useAuthStore} from '@/stores/auth';
import {authForbidden, markForbidden} from '@/lib/auth-state';
import AuthForbidden from '@/components/AuthForbidden.vue';
import PrimeVue from 'primevue/config';
import {createYmaps} from 'vue-yandex-maps';
import {authRepository} from '@/modules/auth/repositories/authRepository';
import {shouldInitializeSentry} from '@/lib/sentry';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const yandexMapsRouterApiKey = import.meta.env.VITE_YANDEX_MAPS_ROUTER_API_KEY?.trim();

const publicPaths = ['/auth'];

type SentryInitializer = (vueApp: VueApp) => void;

router.on('before', (event) => {
    const token = sessionStorage.getItem('access_token')
    if (token) {
        event.detail.visit.headers['Authorization'] = `Bearer ${token}`
    }
})

async function consumeLaunchToken(): Promise<void> {
    const params = new URLSearchParams(window.location.search)
    const token = params.get('_token')

    if (!token) {
        return
    }

    try {
        const tokens = await authRepository.exchangeLaunchToken(token)
        sessionStorage.setItem('access_token', tokens.access_token)
        sessionStorage.setItem('refresh_token', tokens.refresh_token)
    } catch {
        markForbidden()
    } finally {
        params.delete('_token')
        const query = params.toString()
        window.history.replaceState(
            {},
            '',
            window.location.pathname + (query ? `?${query}` : '') + window.location.hash,
        )
    }
}

async function createSentryInitializer(): Promise<SentryInitializer | null> {
    const dsn = import.meta.env.VITE_SENTRY_DSN?.trim()

    if (!shouldInitializeSentry(dsn, import.meta.env.PROD)) {
        return null
    }

    const [{init}, {makeFetchTransport}] = await Promise.all([
        import('@sentry/vue'),
        import('@sentry/browser'),
    ])
    const sentryKey = new URL(dsn).username || 'sentry'

    return (vueApp) => {
        init({
            app: vueApp,
            dsn,
            transport: (options) => makeFetchTransport({
                ...options,
                headers: {
                    ...options.headers,
                    'X-Sentry-Auth': `Sentry sentry_version=7, sentry_key=${sentryKey}`,
                },
            }),
        })
    }
}

function initializeSentry(vueApp: VueApp, initializer: SentryInitializer | null): void {
    try {
        initializer?.(vueApp)
    } catch {
        return
    }
}

if (!publicPaths.includes(window.location.pathname)) {
    await consumeLaunchToken()
}

const sentryInitializer = await createSentryInitializer().catch(() => null)

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent<Component>(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({el, App, props, plugin}) {
        const pinia = createPinia();

        const Root = defineComponent({
            render: () => authForbidden.value
                ? h(AuthForbidden)
                : [h(App, props), h(Toaster, {position: 'bottom-right', richColors: true})],
        });

        const vueApp = createApp(Root)
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .use(createYmaps({
                apikey: import.meta.env.VITE_YANDEX_MAPS_API_KEY || '',
                servicesApikeys: yandexMapsRouterApiKey
                    ? {router: yandexMapsRouterApiKey}
                    : null,
            }))
            .use(PrimeVue, {
                unstyled: true,
                locale: {
                    firstDayOfWeek: 1,
                    dayNames: ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'],
                    dayNamesShort: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                    monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                    monthNamesShort: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'],
                    today: 'Сегодня',
                    clear: 'Очистить',
                    weekHeader: 'Нед',
                },
            });

        initializeSentry(vueApp, sentryInitializer)

        vueApp.mount(el);

        if (!publicPaths.includes(window.location.pathname)) {
            void useAuthStore(pinia).initialize();
        }

        return vueApp;
    },
    progress: {
        color: '#4B5563',
    },
});
