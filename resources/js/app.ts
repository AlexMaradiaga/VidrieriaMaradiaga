/// <reference types="vite/client" />
/// <reference types="vite-plugin-pwa/client" />

import "../css/app.css";
import "primeicons/primeicons.css";

import ToastService from "primevue/toastservice";
import ConfirmationService from "primevue/confirmationservice";

import { createApp, Fragment, h, type DefineComponent } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";

import PrimeVue from "primevue/config";
import Aura from "@primevue/themes/aura";
import AppSettings from "./Components/Common/AppSettings.vue";
import ErpLayout from "./Layouts/ErpLayout.vue";
import { i18n } from "./i18n";
import { initializeAppSettings } from "./Composables/useAppSettings";
import { registerSW } from "virtual:pwa-register";

type PageModule = { default: DefineComponent };

initializeAppSettings();

registerSW({
    immediate: true,
    onRegisteredSW(_url, registration) {
        if (registration) {
            window.setInterval(
                () => void registration.update(),
                60 * 60 * 1000,
            );
        }
    },
});

createInertiaApp({
    resolve: async (name) => {
        const page = await resolvePageComponent<PageModule>(
            `./Pages/${name}.vue`,
            import.meta.glob<PageModule>("./Pages/**/*.vue"),
        );

        if (!name.startsWith("Auth/")) {
            Reflect.set(page.default, "layout", ErpLayout);
        }

        return page.default;
    },

    setup({ el, App, props, plugin }) {
        createApp({
            render: () => h(Fragment, null, [h(App, props), h(AppSettings)]),
        })
            .use(plugin)
            .use(i18n)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: ".app-dark",
                    },
                },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .mount(el);
    },
});
