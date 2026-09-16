<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import Tag from "primevue/tag";
import InventoryNav from "../../../Components/Common/InventoryNav.vue";
interface Row {
    id: number;
    sku: string;
    name: string;
    unit: string;
    quantity: string;
    minimum_stock: string;
    reorder_point: string;
    maximum_stock: string | null;
}
defineProps<{ rows: Row[]; out_of_stock: number; total: number }>();
const qty = (value: string) =>
    Number(value).toLocaleString("en-US", { maximumFractionDigits: 4 });
</script>
<template>
    <Head title="Alertas de inventario | Vidriería Maradiaga" />
    <main class="page">
        <InventoryNav />
        <h1>Alertas de reabastecimiento</h1>
        <p class="lead">
            Los productos aparecen cuando la existencia total llega al nivel
            configurado para solicitar reposición.
        </p>
        <section class="metrics">
            <article>
                <span>Productos para revisar</span><strong>{{ total }}</strong>
            </article>
            <article>
                <span>Sin existencia</span><strong>{{ out_of_stock }}</strong>
            </article>
        </section>
        <section class="panel">
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th class="num">Existencia</th>
                            <th>Unidad</th>
                            <th class="num">Mínimo</th>
                            <th class="num">Solicitar en</th>
                            <th>Situación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td>
                                <Link
                                    :href="`/inventory/products?search=${encodeURIComponent(row.sku)}`"
                                    >{{ row.sku }}</Link
                                >
                            </td>
                            <td>{{ row.name }}</td>
                            <td class="num">{{ qty(row.quantity) }}</td>
                            <td>{{ row.unit }}</td>
                            <td class="num">{{ qty(row.minimum_stock) }}</td>
                            <td class="num">{{ qty(row.reorder_point) }}</td>
                            <td>
                                <Tag
                                    :severity="
                                        Number(row.quantity) <= 0
                                            ? 'danger'
                                            : 'warn'
                                    "
                                    :value="
                                        Number(row.quantity) <= 0
                                            ? 'Agotado'
                                            : 'Reponer'
                                    "
                                />
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="7">
                                No hay alertas activas. Las existencias están
                                por encima del punto de reposición.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
<style scoped>
.page {
    max-width: 1250px;
    margin: auto;
    padding: clamp(1rem, 3vw, 2.5rem);
    font-family: system-ui, sans-serif;
}
.lead {
    color: var(--p-text-muted-color);
}
.metrics {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 250px));
    gap: 1rem;
    margin: 1.5rem 0;
}
.metrics article,
.panel {
    padding: 1.25rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 1rem;
    background: var(--p-content-background);
}
.metrics span {
    display: block;
    color: var(--p-text-muted-color);
}
.metrics strong {
    display: block;
    margin-top: 0.5rem;
    font-size: 2rem;
}
.table {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th,
td {
    padding: 0.85rem;
    border-bottom: 1px solid var(--p-content-border-color);
    text-align: left;
}
.num {
    text-align: right;
}
a {
    color: var(--p-primary-color);
}
</style>
