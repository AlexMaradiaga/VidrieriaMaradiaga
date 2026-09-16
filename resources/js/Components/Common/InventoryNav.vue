<script setup lang="ts">
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";

const page = usePage<{ auth: { permissions: string[] } }>();
const permissions = computed(() => page.props.auth.permissions ?? []);
const can = (permission: string): boolean =>
    permissions.value.includes(permission);
</script>

<template>
    <nav class="inventory-nav" aria-label="Navegación del inventario">
        <Link href="/">
            <i class="pi pi-home" aria-hidden="true"></i>
            Panel
        </Link>
        <Link v-if="can('inventory.products.view')" href="/inventory/products"
            >Productos</Link
        >
        <Link
            v-if="can('inventory.entries.create')"
            href="/inventory/entries/create"
            >Entrada</Link
        >
        <Link
            v-if="can('inventory.exits.create')"
            href="/inventory/exits/create"
            >Salida</Link
        >
        <Link
            v-if="can('inventory.transfers.create')"
            href="/inventory/transfers/create"
            >Traslado</Link
        >
        <Link v-if="can('inventory.entries.view')" href="/inventory/entries"
            >Movimientos</Link
        >
        <Link v-if="can('inventory.kardex.view')" href="/inventory/kardex"
            >Kardex</Link
        >
        <Link v-if="can('inventory.counts.view')" href="/inventory/counts"
            >Conteos</Link
        >
        <Link v-if="can('inventory.alerts.view')" href="/inventory/alerts"
            >Alertas</Link
        >
        <Link v-if="can('inventory.remnants.view')" href="/inventory/remnants"
            >Retazos</Link
        >
        <Link v-if="can('inventory.kits.view')" href="/inventory/kits"
            >Kits</Link
        >
        <Link v-if="can('inventory.cuts.use')" href="/inventory/cut-calculator"
            >Cortes</Link
        >
        <Link v-if="can('inventory.catalogs.view')" href="/inventory/catalogs"
            >Catálogos</Link
        >
    </nav>
</template>

<style scoped>
.inventory-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}
.inventory-nav a {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.55rem 0.75rem;
    border: 1px solid var(--p-content-border-color, #374151);
    border-radius: 0.5rem;
    color: inherit;
    text-decoration: none;
}
.inventory-nav a:hover {
    color: var(--p-primary-color, #34d399);
    border-color: var(--p-primary-color, #34d399);
}
</style>
