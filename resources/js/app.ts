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
import {ZiggyVue} from '../../vendor/tightenco/ziggy';
import {Toaster} from 'vue-sonner';
import 'vue-sonner/style.css';
import {useAuthStore} from '@/stores/auth';
import PrimeVue from 'primevue/config';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

router.on('before', (event) => {
    const token = sessionStorage.getItem('access_token')
    if (token) {
        event.detail.visit.headers['Authorization'] = `Bearer ${token}`
    }
})

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')) as any,
    setup({el, App, props, plugin}) {
        const pinia = createPinia();

        const Root = defineComponent({
            render: () => [h(App, props), h(Toaster, {position: 'bottom-right', richColors: true})],
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

        useAuthStore(pinia).initialize();

        return app;
    },
    progress: {
        color: '#4B5563',
    },
});
