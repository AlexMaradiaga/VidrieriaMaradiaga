/// <reference types="vite/client" />

import '../css/app.css';
import 'primeicons/primeicons.css';

import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';

import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';

import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(App, props),
        })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .mount(el);
    },
});
