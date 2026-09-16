/// <reference types="vite/client" />

import '../css/app.css';
import 'primeicons/primeicons.css';

import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';

import { createApp, Fragment, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import AppSettings from './Components/Common/AppSettings.vue';
import { i18n } from './i18n';
import { initializeAppSettings } from './Composables/useAppSettings';

initializeAppSettings();

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),

    setup({ el, App, props, plugin }) {
        createApp({
            render: () =>
                h(Fragment, null, [
                    h(App, props),
                    h(AppSettings),
                ]),
        })
            .use(plugin)
            .use(i18n)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.app-dark',
                    },
                },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .mount(el);
    },
});
