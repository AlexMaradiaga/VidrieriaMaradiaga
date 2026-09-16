<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import Card from "primevue/card";

interface StockAlert {
    id: number;
    sku: string;
    name: string;
    unit_symbol: string;
    quantity: string;
    minimum_stock: string;
}
interface Dashboard {
    inventory_value: string;
    products_total: number;
    products_active: number;
    pending_entries: number;
    critical_products: number;
    low_stock: StockAlert[];
}
const props = defineProps<{ dashboard: Dashboard | null }>();
const page = usePage<{ auth: { permissions: string[] } }>();
const logoutForm = useForm({});
const refreshing = ref(false);
const refreshError = ref("");
const canView = computed(() =>
    page.props.auth.permissions.includes("inventory.products.view"),
);
const canRegister = computed(
    () =>
        page.props.auth.permissions.includes("inventory.entries.create") &&
        page.props.auth.permissions.includes("inventory.entries.post"),
);

// Mantener precisión al mostrar importes grandes: no convertir dinero a Number.
function formatAmount(value: string): string {
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(value);
    if (!match) return "—";
    const fraction = (match[3] ?? "").padEnd(3, "0");
    let cents = BigInt(match[2]!) * 100n + BigInt(fraction.slice(0, 2));
    if (Number(fraction[2]) >= 5) cents += 1n;
    const whole = (cents / 100n)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    return `${match[1] && cents !== 0n ? "-" : ""}${whole}.${(cents % 100n).toString().padStart(2, "0")}`;
}
function formatQuantity(value: string): string {
    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(value);
    if (!match) return "—";
    const whole = match[2]!.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    const fraction = (match[3] ?? "").replace(/0+$/, "");
    return `${match[1]}${whole}${fraction ? `.${fraction}` : ""}`;
}
function refresh(): void {
    if (refreshing.value) return;
    refreshing.value = true;
    refreshError.value = "";
    router.reload({
        only: ["dashboard", "auth"],
        onError: () => {
            refreshError.value =
                "No se pudo actualizar el resumen. Intentá nuevamente.";
        },
        onFinish: () => {
            refreshing.value = false;
        },
    });
}
function logout(): void {
    if (!logoutForm.processing) logoutForm.post("/logout");
}
</script>

<template>
    <Head title="Dashboard | Vidriería Maradiaga" />
    <main class="dashboard">
        <header class="dashboard-header">
            <div>
                <p class="eyebrow">PANEL PRINCIPAL</p>
                <h1>Vidriería Maradiaga ERP</h1>
                <p class="subtitle">Resumen general del inventario.</p>
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
        <p v-if="!props.dashboard" class="summary-note" role="status">
            Tu usuario no tiene permiso para consultar el inventario.
        </p>
        <template v-if="props.dashboard">
            <div class="summary-toolbar">
                <p class="subtitle">
                    Existencias de todas las ubicaciones. Actualizá para
                    consultar los últimos movimientos.
                </p>
                <Button
                    type="button"
                    label="Actualizar"
                    icon="pi pi-refresh"
                    severity="info"
                    :loading="refreshing"
                    :disabled="refreshing"
                    @click="refresh"
                />
            </div>
            <p v-if="refreshError" role="alert">{{ refreshError }}</p>
            <section
                class="cards"
                aria-label="Indicadores de inventario"
                :aria-busy="refreshing"
            >
                <Card>
                    <template #title>Valor del inventario</template>
                    <template #content>
                        <p class="metric">
                            {{ formatAmount(props.dashboard.inventory_value) }}
                        </p>
                        <p class="metric-note">
                            A costo promedio, en la moneda de tus costos.
                            Incluye saldos de productos inactivos.
                        </p>
                    </template>
                </Card>
                <Card>
                    <template #title>Productos</template>
                    <template #content>
                        <p class="metric">
                            {{
                                props.dashboard.products_total.toLocaleString(
                                    "en-US",
                                )
                            }}
                        </p>
                        <p class="metric-note">
                            {{ props.dashboard.products_active }} activos;
                            {{
                                props.dashboard.products_total -
                                props.dashboard.products_active
                            }}
                            inactivos.
                        </p>
                    </template>
                </Card>
                <Card>
                    <template #title>Entradas pendientes</template>
                    <template #content>
                        <p class="metric">
                            {{
                                props.dashboard.pending_entries.toLocaleString(
                                    "en-US",
                                )
                            }}
                        </p>
                        <p class="metric-note">
                            Entradas en borrador. Las confirmadas ya afectan las
                            existencias.
                        </p>
                    </template>
                </Card>
                <Card>
                    <template #title>Productos en mínimo o menos</template>
                    <template #content>
                        <p class="metric">
                            {{
                                props.dashboard.critical_products.toLocaleString(
                                    "en-US",
                                )
                            }}
                        </p>
                        <p class="metric-note">
                            Productos activos con control de existencias.
                        </p>
                    </template>
                </Card>
            </section>
            <section class="stock-panel" aria-labelledby="stock-heading">
                <h2 id="stock-heading">Productos que requieren revisión</h2>
                <p class="metric-note">
                    La existencia total se compara con el mínimo configurado
                    para cada producto.
                </p>
                <p v-if="props.dashboard.critical_products === 0" role="status">
                    No hay productos activos con existencias iguales o
                    inferiores al mínimo.
                </p>
                <template v-else>
                    <p class="metric-note">
                        Mostrando {{ props.dashboard.low_stock.length }} de
                        {{ props.dashboard.critical_products }}, ordenados por
                        menor existencia.
                    </p>
                    <div
                        class="table-scroll"
                        tabindex="0"
                        role="region"
                        aria-label="Productos en mínimo o menos"
                    >
                        <table>
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Unidad</th>
                                    <th class="numeric">Existencia total</th>
                                    <th class="numeric">Mínimo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="product in props.dashboard.low_stock"
                                    :key="product.id"
                                >
                                    <td>
                                        <Link
                                            :href="`/inventory/products?search=${encodeURIComponent(product.sku)}`"
                                            >{{ product.sku }}</Link
                                        >
                                    </td>
                                    <td>{{ product.name }}</td>
                                    <td>{{ product.unit_symbol }}</td>
                                    <td class="numeric">
                                        {{ formatQuantity(product.quantity) }}
                                    </td>
                                    <td class="numeric">
                                        {{
                                            formatQuantity(
                                                product.minimum_stock,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </section>
        </template>
        <section class="actions" aria-label="Acciones de inventario">
            <Link
                v-if="canView"
                href="/inventory/products"
                class="catalog-link"
            >
                <i class="pi pi-box" aria-hidden="true"></i>
                Abrir catálogo de productos
            </Link>

            <Link
                v-if="canRegister"
                href="/inventory/entries/create"
                class="catalog-link"
            >
                <i class="pi pi-plus" aria-hidden="true"></i>
                Registrar entrada
            </Link>

            <Link
                v-if="
                    page.props.auth.permissions.includes(
                        'inventory.entries.view',
                    )
                "
                href="/inventory/entries"
                class="catalog-link"
            >
                <i class="pi pi-history" aria-hidden="true"></i>
                Historial de entradas
            </Link>

            <Link
                v-if="
                    page.props.auth.permissions.includes(
                        'inventory.kardex.view',
                    )
                "
                href="/inventory/kardex"
                class="catalog-link"
            >
                <i class="pi pi-list" aria-hidden="true"></i>
                Kardex por producto
            </Link>
            <Link
                v-if="
                    page.props.auth.permissions.includes(
                        'inventory.exits.create',
                    )
                "
                href="/inventory/exits/create"
                class="catalog-link"
                ><i class="pi pi-minus" /> Registrar salida</Link
            >
            <Link
                v-if="
                    page.props.auth.permissions.includes(
                        'inventory.transfers.create',
                    )
                "
                href="/inventory/transfers/create"
                class="catalog-link"
                ><i class="pi pi-arrow-right-arrow-left" /> Trasladar
                existencias</Link
            >
            <Link
                v-if="
                    page.props.auth.permissions.includes(
                        'inventory.counts.view',
                    )
                "
                href="/inventory/counts"
                class="catalog-link"
                ><i class="pi pi-clipboard" /> Conteos físicos</Link
            >
            <Link
                v-if="
                    page.props.auth.permissions.includes(
                        'inventory.alerts.view',
                    )
                "
                href="/inventory/alerts"
                class="catalog-link"
                ><i class="pi pi-bell" /> Alertas de reposición</Link
            >
            <Link
                v-if="
                    page.props.auth.permissions.includes(
                        'inventory.remnants.view',
                    )
                "
                href="/inventory/remnants"
                class="catalog-link"
                ><i class="pi pi-th-large" /> Retazos</Link
            >
            <Link
                v-if="
                    page.props.auth.permissions.includes('inventory.kits.view')
                "
                href="/inventory/kits"
                class="catalog-link"
                ><i class="pi pi-box" /> Kits</Link
            >
            <Link
                v-if="
                    page.props.auth.permissions.includes('inventory.cuts.use')
                "
                href="/inventory/cut-calculator"
                class="catalog-link"
                ><i class="pi pi-calculator" /> Calculadora de cortes</Link
            >
            <Link
                v-if="
                    page.props.auth.permissions.includes(
                        'inventory.catalogs.view',
                    )
                "
                href="/inventory/catalogs"
                class="catalog-link"
                ><i class="pi pi-cog" /> Catálogos maestros</Link
            >
        </section>
    </main>
</template>

<style scoped>
.dashboard {
    max-width: 1440px;
    margin: 0 auto;
    padding: clamp(1rem, 3vw, 2.5rem);
    font-family: system-ui, sans-serif;
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
    line-height: 1.6;
}
.summary-note {
    margin-top: 1.75rem;
    padding: 1rem;
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 0.75rem;
}
.summary-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}
.cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-top: 1.5rem;
}
.metric {
    margin: 0.5rem 0 0;
    font-size: clamp(1.25rem, 2.5vw, 2rem);
    font-weight: 700;
    overflow-wrap: anywhere;
    font-variant-numeric: tabular-nums;
}
.metric-note {
    color: var(--p-text-muted-color, #64748b);
    font-size: 0.875rem;
    line-height: 1.6;
}
.stock-panel {
    margin-top: 1.5rem;
    padding: 1.25rem;
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 1rem;
    background: var(--p-content-background, #fff);
}
.stock-panel h2 {
    margin: 0;
    font-size: 1.15rem;
}
.table-scroll {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th,
td {
    padding: 0.9rem;
    text-align: left;
    border-bottom: 1px solid var(--p-content-border-color, #cbd5e1);
}
th {
    white-space: nowrap;
}
.numeric {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}
td a {
    color: var(--p-primary-color, #0f766e);
}
.actions {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 2rem;
}
.catalog-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.7rem 1rem;
    border-radius: 0.5rem;
    background: var(--p-primary-color, #0f766e);
    color: var(--p-primary-contrast-color, #fff);
    font-weight: 600;
    text-decoration: none;
}
.catalog-link:hover {
    background: var(--p-primary-hover-color, #115e59);
}
.catalog-link:focus-visible,
.table-scroll:focus-visible {
    outline: 2px solid var(--p-primary-color, #0f766e);
    outline-offset: 3px;
}
@media (max-width: 1000px) {
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
    .actions {
        flex-direction: column;
        align-items: stretch;
    }
    .stock-panel {
        padding: 1rem;
    }
}
</style>
