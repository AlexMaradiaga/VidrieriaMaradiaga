<script setup lang="ts">
import { computed, ref } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import ErpSidebar from "../Components/Common/ErpSidebar.vue";

const page = usePage<{ auth?: { user?: { name: string } | null } }>();
const sidebarOpen = ref(false);
const refreshing = ref(false);
const logoutForm = useForm({});

const currentPath = computed(() => page.url.split("?")[0]);
const sectionTitle = computed(() => {
    if (currentPath.value.startsWith("/inventory")) return "Inventario";
    if (currentPath.value.startsWith("/sales")) return "Ventas y clientes";
    if (
        currentPath.value.startsWith("/purchases") ||
        currentPath.value.startsWith("/suppliers")
    )
        return "Compras y proveedores";
    if (currentPath.value.startsWith("/accounting")) return "Contabilidad";
    if (currentPath.value.startsWith("/access")) return "Administración";
    return "Dashboard empresarial";
});

const refresh = (): void => {
    refreshing.value = true;
    router.reload({
        onFinish: () => {
            refreshing.value = false;
        },
    });
};

const logout = (): void => logoutForm.post("/logout");
</script>

<template>
    <ErpSidebar :open="sidebarOpen" @close="sidebarOpen = false" />
    <div class="erp-workspace">
        <header class="erp-topbar">
            <div class="topbar-title">
                <button
                    type="button"
                    class="menu-button"
                    aria-label="Abrir menú"
                    @click="sidebarOpen = true"
                >
                    <i class="pi pi-bars" />
                </button>
                <div>
                    <small>Vidriería Maradiaga ERP</small>
                    <strong>{{ sectionTitle }}</strong>
                </div>
            </div>
            <div class="topbar-actions">
                <button
                    type="button"
                    title="Actualizar pantalla"
                    @click="refresh"
                >
                    <i :class="['pi pi-refresh', { spin: refreshing }]" />
                    <span>Actualizar</span>
                </button>
                <span class="profile">
                    <b>{{
                        page.props.auth?.user?.name?.charAt(0).toUpperCase() ||
                        "U"
                    }}</b>
                    <span>{{ page.props.auth?.user?.name }}</span>
                </span>
                <button type="button" title="Cerrar sesión" @click="logout">
                    <i class="pi pi-sign-out" />
                    <span>Salir</span>
                </button>
            </div>
        </header>
        <div class="erp-content">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.erp-workspace {
    min-height: 100vh;
    margin-left: 17rem;
    background: var(--app-page-background);
    color: var(--app-page-text);
}
.erp-topbar {
    min-height: 4.5rem;
    background: var(--p-content-background, #fff);
    border-bottom: 1px solid var(--p-content-border-color, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.65rem 1.5rem;
    position: sticky;
    top: 0;
    z-index: 25;
    box-shadow: 0 3px 12px #0f172a0a;
}
.topbar-title,
.topbar-actions,
.profile {
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.topbar-title small,
.topbar-title strong {
    display: block;
}
.topbar-title small {
    color: var(--p-text-muted-color, #64748b);
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.topbar-title strong {
    margin-top: 0.12rem;
    font-size: 0.98rem;
}
.menu-button {
    display: none !important;
}
.erp-topbar button {
    border: 1px solid transparent;
    background: transparent;
    color: var(--p-text-muted-color, #526173);
    padding: 0.55rem 0.65rem;
    border-radius: 0.55rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    cursor: pointer;
}
.erp-topbar button:hover {
    background: var(--p-content-hover-background, #f1f5f9);
    color: var(--p-primary-color, #2563eb);
}
.profile {
    padding: 0.3rem 0.65rem;
    color: var(--p-text-color, inherit);
}
.profile b {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: color-mix(
        in srgb,
        var(--p-primary-color, #2563eb) 18%,
        transparent
    );
    color: var(--p-primary-color, #2563eb);
}
.erp-content {
    min-width: 0;
}
.spin {
    animation: spin 0.7s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
@media (max-width: 950px) {
    .erp-workspace {
        margin-left: 0;
    }
    .erp-topbar {
        padding: 0.6rem 0.85rem;
    }
    .menu-button {
        display: inline-flex !important;
    }
    .profile span,
    .topbar-actions button span {
        display: none;
    }
}
@media (max-width: 520px) {
    .topbar-title small {
        display: none;
    }
}
</style>
