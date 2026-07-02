import '../css/app.css';
import './bootstrap';
import '@vue-flow/core/dist/style.css';
import '@vue-flow/core/dist/theme-default.css';
import '@vue-flow/controls/dist/style.css';
import '@vue-flow/minimap/dist/style.css';

import {createInertiaApp, router} from '@inertiajs/vue3';
import {resolvePageComponent} from 'laravel-vite-plugin/inertia-helpers';
import {createApp, defineComponent, h} from 'vue';
import {createPinia} from 'pinia';
import axios from 'axios';
import {ZiggyVue} from '../../vendor/tightenco/ziggy';
import {Toaster} from 'vue-sonner';
import 'vue-sonner/style.css';
import {useAuthStore} from '@/stores/auth';
import {authForbidden, markForbidden} from '@/lib/auth-state';
import AuthForbidden from '@/components/AuthForbidden.vue';
import PrimeVue from 'primevue/config';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('before', (event) => {
    const token = sessionStorage.getItem('access_token')
    if (token) {
        event.detail.visit.headers['Authorization'] = `Bearer ${token}`
    }
})

// Глобальный вход по launch-токену: ловим ?_token= на любой странице, меняем на пару
// access/refresh, вырезаем токен из URL (чтобы не оседал в истории/логах) и продолжаем.
async function consumeLaunchToken(): Promise<void> {
    const params = new URLSearchParams(window.location.search)
    const token = params.get('_token')

    if (!token) {
        return
    }

    try {
        const {data} = await axios.post('/api/embed/auth/exchange', {_token: token})
        sessionStorage.setItem('access_token', data.access_token)
        sessionStorage.setItem('refresh_token', data.refresh_token)
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

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')) as any,
    setup({el, App, props, plugin}) {
        const pinia = createPinia();

        const Root = defineComponent({
            render: () => authForbidden.value
                ? h(AuthForbidden)
                : [h(App, props), h(Toaster, {position: 'bottom-right', richColors: true})],
        });

        const app = createApp(Root)
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
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
            })
            .mount(el);

        // Публичные страницы входа (dev) не требуют токена — иначе глобальная авторизация
        // покажет 403 поверх формы логина.
        const publicPaths = ['/login', '/auth'];
        if (!publicPaths.includes(window.location.pathname)) {
            void (async () => {
                await consumeLaunchToken();
                await useAuthStore(pinia).initialize();
            })();
        }

        return app;
    },
    progress: {
        color: '#4B5563',
    },
});
