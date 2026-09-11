<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Card from 'primevue/card';

const logoutForm = useForm({});

function logout(): void {
    if (logoutForm.processing) {
        return;
    }

    logoutForm.post('/logout');
}

const page = usePage<{
    auth: {
        permissions: string[];
    };
}>();
</script>

<template>
    <Head title="Dashboard | Vidriería Maradiaga" />

    <main class="dashboard">
        <header class="dashboard-header">
            <div>
                <p class="eyebrow">PANEL PRINCIPAL</p>
                <h1>Vidriería Maradiaga ERP</h1>
                <p class="subtitle">
                    Resumen general del inventario.
                </p>
            </div>

            <Button
                type="button"
                label="Cerrar sesión"
                icon="pi pi-sign-out"
                severity="secondary"
                outlined
                :loading="logoutForm.processing"
                :disabled="logoutForm.processing"
                @click="logout"
            />
        </header>

        <p class="demo-notice" role="note">
            Datos de demostración. Los indicadores todavía no están
            conectados al inventario real.
        </p>

        <section class="cards" aria-label="Indicadores de inventario">
            <Card>
                <template #title>
                    Valor del inventario
                </template>

                <template #content>
                    <p class="metric">$248,540</p>
                </template>
            </Card>

            <Card>
                <template #title>
                    Productos
                </template>

                <template #content>
                    <p class="metric">1,284</p>
                </template>
            </Card>

            <Card>
                <template #title>
                    Entradas pendientes
                </template>

                <template #content>
                    <p class="metric">18</p>
                </template>
            </Card>

            <Card>
                <template #title>
                    Stock crítico
                </template>

                <template #content>
                    <p class="metric">24</p>
                </template>
            </Card>
        </section>

        <section class="actions" aria-label="Acciones de inventario">
            <Link
                v-if="page.props.auth.permissions.includes('inventory.products.view')"
                href="/inventory/products"
                class="catalog-link"
            >
                <i class="pi pi-box" aria-hidden="true"></i>
                Abrir catálogo de productos
            </Link>

            <Button
                type="button"
                label="Registrar entrada"
                icon="pi pi-plus"
                disabled
                aria-describedby="entry-status"
            />

            <p id="entry-status" class="action-note">
                Disponible cuando habilitemos el registro de entradas.
            </p>
        </section>
    </main>
</template>

<style scoped>
.dashboard {
    max-width: 1440px;
    margin: 0 auto;
    padding: clamp(1rem, 3vw, 2.5rem);
}

.dashboard-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1.5rem;
}

.eyebrow {
    margin: 0 0 0.5rem;
    color: var(--p-primary-color, #0f766e);
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.12em;
}

h1 {
    margin: 0;
    font-size: clamp(1.5rem, 3vw, 2rem);
    line-height: 1.2;
}

.subtitle {
    margin: 0.75rem 0 0;
    color: var(--p-text-muted-color, #64748b);
}

.demo-notice {
    margin-top: 1.75rem;
    padding: 0.9rem 1rem;
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 0.75rem;
    color: var(--p-text-muted-color, #64748b);
    font-size: 0.875rem;
    line-height: 1.6;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}

.metric {
    margin: 0.5rem 0 0;
    font-size: 2rem;
    font-weight: 700;
}

.actions {
    margin-top: 2rem;
}

.action-note {
    margin-top: 0.75rem;
    color: var(--p-text-muted-color, #64748b);
    font-size: 0.875rem;
}

@media (max-width: 900px) {
    .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .cards {
        grid-template-columns: 1fr;
    }
}
.catalog-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.7rem 1rem;
    margin-right: 1rem;
    margin-bottom: 0.75rem;
    border-radius: 0.5rem;
    background: var(--p-primary-color, #0f766e);
    color: var(--p-primary-contrast-color, #fff);
    font-weight: 600;
    text-decoration: none;
}

.catalog-link:hover {
    background: var(--p-primary-hover-color, #115e59);
}

.catalog-link:focus-visible {
    outline: 2px solid var(--p-primary-color, #0f766e);
    outline-offset: 3px;
}
</style>
