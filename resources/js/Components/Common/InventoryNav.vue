<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const page = usePage<{ auth: { permissions: string[] } }>();
const permissions = computed(() => page.props.auth.permissions ?? []);
const can = (permission: string): boolean =>
    permissions.value.includes(permission);
const { t } = useI18n();
</script>

<template>
    <nav class="inventory-nav" :aria-label="t('nav.catalogs')">
        <Link href="/"><i class="pi pi-home" /> {{ t('nav.panel') }}</Link>
        <Link v-if="can('inventory.products.view')" href="/inventory/products">{{ t('nav.products') }}</Link>
        <Link v-if="can('inventory.entries.create')" href="/inventory/entries/create">{{ t('nav.entry') }}</Link>
        <Link v-if="can('inventory.exits.create')" href="/inventory/exits/create">{{ t('nav.exit') }}</Link>
        <Link v-if="can('inventory.transfers.create')" href="/inventory/transfers/create">{{ t('nav.transfer') }}</Link>
        <Link v-if="can('inventory.entries.view')" href="/inventory/entries">{{ t('nav.movements') }}</Link>
        <Link v-if="can('inventory.kardex.view')" href="/inventory/kardex">{{ t('nav.kardex') }}</Link>
        <Link v-if="can('inventory.counts.view')" href="/inventory/counts">{{ t('nav.counts') }}</Link>
        <Link v-if="can('inventory.alerts.view')" href="/inventory/alerts">{{ t('nav.alerts') }}</Link>
        <Link v-if="can('inventory.remnants.view')" href="/inventory/remnants">{{ t('nav.remnants') }}</Link>
        <Link v-if="can('inventory.kits.view')" href="/inventory/kits">{{ t('nav.kits') }}</Link>
        <Link v-if="can('inventory.cuts.use')" href="/inventory/cut-calculator">{{ t('nav.cuts') }}</Link>
        <Link v-if="can('inventory.catalogs.view')" href="/inventory/catalogs">{{ t('nav.catalogs') }}</Link>
        <Link v-if="can('sales.view')" href="/sales"><i class="pi pi-shopping-cart" /> Ventas</Link>
        <Link v-if="can('sales.customers.manage')" href="/sales/customers">Clientes</Link>
        <Link v-if="can('suppliers.view')" href="/suppliers">Proveedores</Link>
        <Link v-if="can('purchases.view')" href="/purchases"><i class="pi pi-shopping-bag" /> Compras</Link>
        <Link v-if="can('accounting.entries.view')" href="/accounting"><i class="pi pi-calculator" /> Contabilidad</Link>
        <Link v-if="can('accounting.treasury.view')" href="/accounting/treasury">Caja y bancos</Link>
        <Link v-if="can('accounting.expenses.view')" href="/accounting/expenses">Gastos</Link>
        <Link v-if="can('accounting.loans.view')" href="/accounting/loans">Préstamos</Link>
        <Link v-if="can('access.users.view')" href="/access"><i class="pi pi-users" /> Usuarios</Link>
    </nav>
</template>

<style scoped>
.inventory-nav { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.5rem; }
.inventory-nav a { display: inline-flex; align-items: center; gap: .35rem; padding: .55rem .75rem; border: 1px solid var(--p-content-border-color, #374151); border-radius: .5rem; color: inherit; text-decoration: none; }
.inventory-nav a:hover { color: var(--p-primary-color, #34d399); border-color: var(--p-primary-color, #34d399); }
</style>
