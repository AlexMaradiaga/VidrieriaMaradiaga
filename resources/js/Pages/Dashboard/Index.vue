<script setup lang="ts">
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
interface Alert {
    id: number;
    sku: string;
    name: string;
    unit_symbol: string;
    quantity: string;
    minimum_stock: string;
}
interface Inventory {
    inventory_value: string;
    products_total: number;
    products_active: number;
    pending_entries: number;
    critical_products: number;
    low_stock: Alert[];
}
interface Summary {
    sales_month: string;
    receivables: string;
    purchases_month: string;
    payables: string;
    expenses_month: string;
    cash_balance: string;
}
interface Chart {
    key: string;
    label: string;
    sales: string;
    purchases: string;
    expenses: string;
}
interface Due {
    type: string;
    number: string;
    party: string;
    due_date: string;
    balance: string;
}
interface Recent {
    type: string;
    number: string;
    date: string;
    amount: string;
}
const props = defineProps<{
    inventory: Inventory | null;
    summary: Summary;
    chart: Chart[];
    dueAlerts: Due[];
    recent: Recent[];
}>();
const page = usePage<{ auth: { permissions: string[] } }>();
const can = (p: string) => page.props.auth.permissions.includes(p);
const money = (v: string | number) =>
    new Intl.NumberFormat("es-HN", {
        style: "currency",
        currency: "HNL",
        maximumFractionDigits: 2,
    }).format(Number(v));
const maxChart = computed(() =>
    Math.max(
        1,
        ...props.chart.flatMap((x) => [
            Number(x.sales),
            Number(x.purchases),
            Number(x.expenses),
        ]),
    ),
);
const points = (field: "sales" | "purchases" | "expenses") =>
    props.chart
        .map(
            (row, i) =>
                `${8 + i * (84 / Math.max(1, props.chart.length - 1))},${88 - (Number(row[field]) / maxChart.value) * 72}`,
        )
        .join(" ");
const stats = computed(() =>
    [
        {
            label: "Valor del inventario",
            value: money(props.inventory?.inventory_value ?? 0),
            note: `${props.inventory?.products_total ?? 0} productos registrados`,
            icon: "pi-box",
            tone: "blue",
            show: !!props.inventory,
        },
        {
            label: "Ventas del mes",
            value: money(props.summary.sales_month),
            note: `Por cobrar: ${money(props.summary.receivables)}`,
            icon: "pi-shopping-cart",
            tone: "green",
            show: can("sales.view"),
        },
        {
            label: "Compras del mes",
            value: money(props.summary.purchases_month),
            note: `Por pagar: ${money(props.summary.payables)}`,
            icon: "pi-shopping-bag",
            tone: "violet",
            show: can("purchases.view"),
        },
        {
            label: "Caja y bancos",
            value: money(props.summary.cash_balance),
            note: `Gastos del mes: ${money(props.summary.expenses_month)}`,
            icon: "pi-wallet",
            tone: "amber",
            show: can("accounting.reports.view"),
        },
    ].filter((x) => x.show),
);
</script>
<template>
    <Head title="Dashboard empresarial | Vidriería Maradiaga" />
    <main class="content">
            <section class="welcome">
                <div>
                    <p class="eyebrow">RESUMEN GENERAL</p>
                    <h1>Dashboard empresarial</h1>
                    <p>
                        Inventario, ventas, compras, cuentas pendientes y
                        efectivo en un solo lugar.
                    </p>
                </div>
                <div class="quick">
                    <Link v-if="can('sales.create')" href="/sales/create"
                        ><i class="pi pi-plus" /> Nueva venta</Link
                    ><Link
                        v-if="can('purchases.create')"
                        href="/purchases/create"
                        class="secondary"
                        ><i class="pi pi-shopping-bag" /> Nueva compra</Link
                    >
                </div>
            </section>
            <section class="stats">
                <article
                    v-for="s in stats"
                    :key="s.label"
                    :class="['stat', s.tone]"
                >
                    <div>
                        <p>{{ s.label }}</p>
                        <strong>{{ s.value }}</strong
                        ><small>{{ s.note }}</small>
                    </div>
                    <span><i :class="['pi', s.icon]" /></span>
                </article>
            </section>
            <section class="dashboard-grid">
                <article v-if="inventory" class="panel alerts">
                    <div class="panel-title">
                        <div>
                            <p class="eyebrow red">ATENCIÓN</p>
                            <h2>Alertas de inventario</h2>
                        </div>
                        <span class="counter">{{
                            inventory.critical_products
                        }}</span>
                    </div>
                    <div
                        v-for="item in inventory.low_stock.slice(0, 6)"
                        :key="item.id"
                        class="alert-row"
                    >
                        <div>
                            <strong>{{ item.name }}</strong
                            ><small
                                >{{ item.sku }} · mínimo
                                {{ item.minimum_stock }}
                                {{ item.unit_symbol }}</small
                            >
                        </div>
                        <span>{{ item.quantity }} {{ item.unit_symbol }}</span>
                    </div>
                    <p v-if="!inventory.low_stock.length" class="empty">
                        No hay existencias críticas.
                    </p>
                    <Link
                        v-if="can('inventory.alerts.view')"
                        href="/inventory/alerts"
                        class="more"
                        >Ver todas las alertas</Link
                    >
                </article>
                <article class="panel chart">
                    <div class="panel-title">
                        <div>
                            <p class="eyebrow">ÚLTIMOS 6 MESES</p>
                            <h2>Actividad comercial y operativa</h2>
                        </div>
                        <div class="legend">
                            <span class="sale">Ventas</span
                            ><span class="purchase">Compras</span
                            ><span class="expense">Gastos</span>
                        </div>
                    </div>
                    <svg
                        viewBox="0 0 100 100"
                        preserveAspectRatio="none"
                        role="img"
                        aria-label="Comparación mensual"
                    >
                        <line
                            v-for="y in [16, 34, 52, 70, 88]"
                            :key="y"
                            x1="8"
                            :y1="y"
                            x2="92"
                            :y2="y"
                            class="gridline"
                        />
                        <polyline
                            :points="points('sales')"
                            class="series sales"
                        />
                        <polyline
                            :points="points('purchases')"
                            class="series purchases"
                        />
                        <polyline
                            :points="points('expenses')"
                            class="series expenses"
                        />
                    </svg>
                    <div class="months">
                        <span v-for="m in chart" :key="m.key">{{
                            m.label
                        }}</span>
                    </div>
                    <div class="chart-totals">
                        <div>
                            <small>Ventas</small
                            ><strong>{{ money(summary.sales_month) }}</strong>
                        </div>
                        <div>
                            <small>Compras</small
                            ><strong>{{
                                money(summary.purchases_month)
                            }}</strong>
                        </div>
                        <div>
                            <small>Gastos</small
                            ><strong>{{
                                money(summary.expenses_month)
                            }}</strong>
                        </div>
                    </div>
                </article>
            </section>
            <section class="lower-grid">
                <article class="panel">
                    <div class="panel-title">
                        <div>
                            <p class="eyebrow amber-text">PRÓXIMOS 7 DÍAS</p>
                            <h2>Pagos vencidos o próximos</h2>
                        </div>
                        <Link
                            v-if="can('purchases.view')"
                            href="/purchases"
                            class="more"
                            >Ver cuentas</Link
                        >
                    </div>
                    <div
                        v-for="a in dueAlerts"
                        :key="`${a.type}-${a.number}`"
                        class="due-row"
                    >
                        <span :class="['due-icon', a.type]"
                            ><i
                                :class="[
                                    'pi',
                                    a.type === 'purchase'
                                        ? 'pi-truck'
                                        : 'pi-receipt',
                                ]"
                        /></span>
                        <div>
                            <strong>{{ a.party }}</strong
                            ><small
                                >{{ a.number }} · vence {{ a.due_date }}</small
                            >
                        </div>
                        <b>{{ money(a.balance) }}</b>
                    </div>
                    <p v-if="!dueAlerts.length" class="empty">
                        No hay pagos próximos o vencidos.
                    </p>
                </article>
                <article class="panel">
                    <div class="panel-title">
                        <div>
                            <p class="eyebrow">ACTIVIDAD</p>
                            <h2>Movimientos recientes</h2>
                        </div>
                    </div>
                    <div class="recent-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Documento</th>
                                    <th>Fecha</th>
                                    <th>Importe</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="r in recent"
                                    :key="`${r.type}-${r.number}`"
                                >
                                    <td>
                                        <span class="type">{{ r.type }}</span>
                                    </td>
                                    <td>{{ r.number }}</td>
                                    <td>{{ r.date }}</td>
                                    <td>{{ money(r.amount) }}</td>
                                </tr>
                                <tr v-if="!recent.length">
                                    <td colspan="4">
                                        Todavía no hay movimientos
                                        empresariales.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>
            </section>
    </main>
</template>
<style scoped>
.workspace {
    min-height: 100vh;
    margin-left: 17rem;
    background: #f4f7fb;
    color: #162033;
}
.topbar {
    height: 4.5rem;
    background: #fff;
    border-bottom: 1px solid #e6ebf2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 1.5rem;
    position: sticky;
    top: 0;
    z-index: 20;
}
.menu {
    display: none;
}
.topbar button {
    border: 0;
    background: transparent;
    color: #526173;
    padding: 0.55rem;
    border-radius: 0.5rem;
}
.search {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    color: #718096;
}
.top-actions {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.profile {
    display: flex;
    gap: 0.5rem;
    align-items: center !important;
}
.profile span {
    width: 1.8rem;
    height: 1.8rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #dbeafe;
    color: #1d4ed8;
    font-weight: 800;
}
.spin {
    animation: spin 0.7s linear infinite;
}
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
.content {
    max-width: 1600px;
    margin: auto;
    padding: 1.75rem;
}
.welcome {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.welcome h1 {
    font-size: 2rem;
    margin: 0.2rem 0;
}
.welcome p {
    color: #66758a;
}
.eyebrow {
    margin: 0;
    color: #2563eb !important;
    font-size: 0.68rem !important;
    font-weight: 900;
    letter-spacing: 0.14em;
}
.red {
    color: #dc2626 !important;
}
.amber-text {
    color: #d97706 !important;
}
.quick {
    display: flex;
    gap: 0.6rem;
}
.quick a,
.more {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    text-decoration: none;
}
.quick a {
    padding: 0.7rem 0.9rem;
    border-radius: 0.55rem;
    background: #2563eb;
    color: white;
    font-weight: 700;
}
.quick a.secondary {
    background: white;
    color: #334155;
    border: 1px solid #dbe3ed;
}
.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin: 1.5rem 0;
}
.stat {
    background: #fff;
    border: 1px solid #e6ebf2;
    border-radius: 1rem;
    padding: 1.15rem;
    display: flex;
    justify-content: space-between;
    gap: 0.7rem;
    box-shadow: 0 3px 12px #16203308;
}
.stat p,
.stat small {
    display: block;
    margin: 0;
    color: #718096;
}
.stat strong {
    display: block;
    font-size: 1.55rem;
    margin: 0.4rem 0;
}
.stat > span {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    display: grid;
    place-items: center;
}
.blue > span {
    background: #dbeafe;
    color: #2563eb;
}
.green > span {
    background: #dcfce7;
    color: #16a34a;
}
.violet > span {
    background: #ede9fe;
    color: #7c3aed;
}
.amber > span {
    background: #fef3c7;
    color: #d97706;
}
.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(19rem, 0.68fr) minmax(0, 1.32fr);
    gap: 1rem;
}
.lower-grid {
    display: grid;
    grid-template-columns: 1fr 1.35fr;
    gap: 1rem;
    margin-top: 1rem;
}
.panel {
    background: #fff;
    border: 1px solid #e6ebf2;
    border-radius: 1rem;
    padding: 1.2rem;
    box-shadow: 0 3px 12px #16203308;
}
.panel-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}
.panel h2 {
    font-size: 1rem;
    margin: 0.25rem 0;
}
.counter {
    background: #fee2e2;
    color: #b91c1c;
    border-radius: 999px;
    padding: 0.25rem 0.6rem;
    font-weight: 800;
}
.alert-row,
.due-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.72rem 0;
    border-bottom: 1px solid #edf1f6;
}
.alert-row > div,
.due-row > div {
    flex: 1;
}
.alert-row small,
.due-row small {
    display: block;
    color: #718096;
    margin-top: 0.2rem;
}
.alert-row > span {
    color: #b91c1c;
    font-weight: 800;
}
.more {
    color: #2563eb;
    font-size: 0.82rem;
    font-weight: 700;
    margin-top: 0.8rem;
}
.chart svg {
    width: 100%;
    height: 15rem;
    overflow: visible;
}
.gridline {
    stroke: #e9eef5;
    stroke-width: 0.35;
}
.series {
    fill: none;
    stroke-width: 1.25;
    vector-effect: non-scaling-stroke;
}
.sales {
    stroke: #2563eb;
}
.purchases {
    stroke: #7c3aed;
}
.expenses {
    stroke: #f59e0b;
}
.legend {
    display: flex;
    gap: 1rem;
    font-size: 0.72rem;
}
.legend span:before {
    content: "";
    display: inline-block;
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: currentColor;
    margin-right: 0.3rem;
}
.legend .sale {
    color: #2563eb;
}
.legend .purchase {
    color: #7c3aed;
}
.legend .expense {
    color: #f59e0b;
}
.months {
    display: flex;
    justify-content: space-between;
    color: #718096;
    font-size: 0.72rem;
    padding: 0 4%;
}
.chart-totals {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.6rem;
    margin-top: 1rem;
}
.chart-totals div {
    background: #f8fafc;
    border-radius: 0.7rem;
    padding: 0.7rem;
}
.chart-totals small,
.chart-totals strong {
    display: block;
}
.due-icon {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.65rem;
    display: grid;
    place-items: center;
}
.due-icon.purchase {
    background: #ede9fe;
    color: #7c3aed;
}
.due-icon.expense {
    background: #fef3c7;
    color: #d97706;
}
.due-row b {
    white-space: nowrap;
}
.recent-table {
    overflow: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th,
td {
    padding: 0.72rem;
    text-align: left;
    border-bottom: 1px solid #edf1f6;
    white-space: nowrap;
}
.type {
    background: #eff6ff;
    color: #1d4ed8;
    border-radius: 999px;
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
}
.empty {
    color: #718096;
    text-align: center;
    padding: 1rem;
}
.workspace :deep(*) {
    box-sizing: border-box;
}
@media (max-width: 1200px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
    .dashboard-grid,
    .lower-grid {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 950px) {
    .workspace {
        margin-left: 0;
    }
    .menu {
        display: block !important;
    }
    .profile {
        font-size: 0;
    }
    .content {
        padding: 1rem;
    }
}
@media (max-width: 650px) {
    .welcome {
        align-items: flex-start;
        flex-direction: column;
    }
    .quick {
        width: 100%;
        flex-wrap: wrap;
    }
    .stats {
        grid-template-columns: 1fr;
    }
    .topbar {
        padding: 0 0.75rem;
    }
    .search span {
        display: none;
    }
    .chart-totals {
        grid-template-columns: 1fr;
    }
    .legend {
        display: none;
    }
}
</style>
