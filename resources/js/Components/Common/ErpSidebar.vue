<script setup lang="ts">
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import brandLogo from "../../assets/vidrieria-maradiaga-logo.png";

interface NavItem {
    label: string;
    icon: string;
    href: string;
    permission?: string;
    aliases?: string[];
}

interface NavModule extends NavItem {
    key: string;
    items: NavItem[];
}

defineProps<{ open: boolean }>();
defineEmits<{ close: [] }>();

const page = usePage<{
    auth?: {
        permissions?: string[];
        user?: { name: string; email: string } | null;
    };
}>();

const permissions = computed(() => page.props.auth?.permissions ?? []);
const can = (permission?: string): boolean =>
    !permission || permissions.value.includes(permission);

const rawModules: NavModule[] = [
    {
        key: "inventory",
        label: "Inventario",
        icon: "pi-box",
        href: "/inventory/products",
        permission: "inventory.products.view",
        aliases: ["/inventory"],
        items: [
            {
                label: "Productos",
                icon: "pi-box",
                href: "/inventory/products",
                permission: "inventory.products.view",
            },
            {
                label: "Registrar entrada",
                icon: "pi-arrow-down",
                href: "/inventory/entries/create",
                permission: "inventory.entries.create",
            },
            {
                label: "Registrar salida",
                icon: "pi-arrow-up",
                href: "/inventory/exits/create",
                permission: "inventory.exits.create",
            },
            {
                label: "Trasladar existencias",
                icon: "pi-arrow-right-arrow-left",
                href: "/inventory/transfers/create",
                permission: "inventory.transfers.create",
            },
            {
                label: "Movimientos",
                icon: "pi-history",
                href: "/inventory/entries",
                permission: "inventory.entries.view",
            },
            {
                label: "Kardex por producto",
                icon: "pi-list",
                href: "/inventory/kardex",
                permission: "inventory.kardex.view",
            },
            {
                label: "Conteos físicos",
                icon: "pi-clipboard",
                href: "/inventory/counts",
                permission: "inventory.counts.view",
            },
            {
                label: "Alertas de reposición",
                icon: "pi-bell",
                href: "/inventory/alerts",
                permission: "inventory.alerts.view",
            },
            {
                label: "Retazos",
                icon: "pi-th-large",
                href: "/inventory/remnants",
                permission: "inventory.remnants.view",
            },
            {
                label: "Kits",
                icon: "pi-objects-column",
                href: "/inventory/kits",
                permission: "inventory.kits.view",
            },
            {
                label: "Calculadora de cortes",
                icon: "pi-calculator",
                href: "/inventory/cut-calculator",
                permission: "inventory.cuts.use",
            },
            {
                label: "Catálogos maestros",
                icon: "pi-cog",
                href: "/inventory/catalogs",
                permission: "inventory.catalogs.view",
            },
        ],
    },
    {
        key: "sales",
        label: "Ventas",
        icon: "pi-shopping-cart",
        href: "/sales",
        permission: "sales.view",
        aliases: ["/sales"],
        items: [
            {
                label: "Resumen de ventas",
                icon: "pi-chart-bar",
                href: "/sales",
                permission: "sales.view",
            },
            {
                label: "Nueva venta",
                icon: "pi-plus-circle",
                href: "/sales/create",
                permission: "sales.create",
            },
            {
                label: "Clientes",
                icon: "pi-users",
                href: "/sales/customers",
                permission: "sales.customers.manage",
            },
        ],
    },
    {
        key: "purchases",
        label: "Compras",
        icon: "pi-shopping-bag",
        href: "/purchases",
        permission: "purchases.view",
        aliases: ["/purchases", "/suppliers"],
        items: [
            {
                label: "Resumen de compras",
                icon: "pi-chart-bar",
                href: "/purchases",
                permission: "purchases.view",
            },
            {
                label: "Nueva compra",
                icon: "pi-plus-circle",
                href: "/purchases/create",
                permission: "purchases.create",
            },
            {
                label: "Proveedores",
                icon: "pi-truck",
                href: "/suppliers",
                permission: "suppliers.view",
            },
        ],
    },
    {
        key: "accounting",
        label: "Contabilidad",
        icon: "pi-calculator",
        href: "/accounting",
        permission: "accounting.entries.view",
        aliases: ["/accounting"],
        items: [
            {
                label: "Resumen contable",
                icon: "pi-book",
                href: "/accounting",
                permission: "accounting.entries.view",
            },
            {
                label: "Caja y bancos",
                icon: "pi-wallet",
                href: "/accounting/treasury",
                permission: "accounting.treasury.view",
            },
            {
                label: "Gastos y servicios",
                icon: "pi-receipt",
                href: "/accounting/expenses",
                permission: "accounting.expenses.view",
            },
            {
                label: "Préstamos y vehículos",
                icon: "pi-car",
                href: "/accounting/loans",
                permission: "accounting.loans.view",
            },
        ],
    },
    {
        key: "access",
        label: "Administración",
        icon: "pi-shield",
        href: "/access",
        permission: "access.users.view",
        aliases: ["/access"],
        items: [
            {
                label: "Usuarios, roles y empleados",
                icon: "pi-users",
                href: "/access",
                permission: "access.users.view",
            },
        ],
    },
];

const modules = computed(() =>
    rawModules
        .map((module) => ({
            ...module,
            items: module.items.filter((item) => can(item.permission)),
        }))
        .filter((module) => can(module.permission) || module.items.length > 0),
);

const currentPath = computed(() => page.url.split("?")[0]);
const moduleIsActive = (module: NavModule): boolean =>
    (module.aliases ?? [module.href]).some(
        (prefix) =>
            currentPath.value === prefix ||
            currentPath.value.startsWith(`${prefix}/`),
    );

const itemIsActive = (item: NavItem, module: NavModule): boolean => {
    const matches = module.items
        .filter(
            (candidate) =>
                currentPath.value === candidate.href ||
                currentPath.value.startsWith(`${candidate.href}/`),
        )
        .sort((a, b) => b.href.length - a.href.length);

    return matches[0]?.href === item.href;
};
</script>

<template>
    <aside :class="['sidebar', { open }]">
        <div class="brand">
            <img
                class="brand-logo"
                :src="brandLogo"
                alt="Vidriería Maradiaga"
            />
            <button
                class="close"
                type="button"
                aria-label="Cerrar menú"
                @click="$emit('close')"
            >
                <i class="pi pi-times" />
            </button>
        </div>
        <nav aria-label="Navegación principal">
            <p class="nav-caption">Menú principal</p>
            <Link
                href="/"
                :class="['module-link', { active: currentPath === '/' }]"
                @click="$emit('close')"
            >
                <i class="pi pi-home" /><span>Dashboard</span>
            </Link>
            <section
                v-for="module in modules"
                :key="module.key"
                class="module-section"
            >
                <Link
                    :href="module.href"
                    :class="['module-link', { active: moduleIsActive(module) }]"
                    @click="$emit('close')"
                >
                    <i :class="['pi', module.icon]" /><span>{{
                        module.label
                    }}</span>
                    <i
                        :class="[
                            'pi chevron',
                            moduleIsActive(module)
                                ? 'pi-chevron-down'
                                : 'pi-chevron-right',
                        ]"
                    />
                </Link>
                <div v-if="moduleIsActive(module)" class="submenu">
                    <p>{{ module.label }}</p>
                    <Link
                        v-for="item in module.items"
                        :key="item.href"
                        :href="item.href"
                        :class="{ active: itemIsActive(item, module) }"
                        @click="$emit('close')"
                    >
                        <i :class="['pi', item.icon]" /><span>{{
                            item.label
                        }}</span>
                    </Link>
                </div>
            </section>
        </nav>
        <div class="user">
            <span class="avatar">{{
                page.props.auth?.user?.name?.charAt(0).toUpperCase() || "U"
            }}</span>
            <div>
                <strong>{{ page.props.auth?.user?.name }}</strong
                ><small>{{ page.props.auth?.user?.email }}</small>
            </div>
        </div>
    </aside>
    <div v-if="open" class="backdrop" @click="$emit('close')" />
</template>

<style scoped>
.sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    width: 17rem;
    background: #0b1220;
    color: #dbe4f0;
    z-index: 40;
    display: flex;
    flex-direction: column;
    box-shadow: 10px 0 30px #0f172a1a;
}
.brand {
    min-height: 7rem;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #ffffff16;
}
.brand-logo {
    display: block;
    width: 10rem;
    max-width: calc(100% - 2rem);
    height: auto;
    border-radius: 0.6rem;
    box-shadow: 0 8px 22px #00000030;
}
.user strong,
.user small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.user small {
    font-size: 0.72rem;
    color: #8fa2ba;
}
.close {
    display: none;
    position: absolute;
    top: 0.55rem;
    right: 0.55rem;
    background: none;
    border: 0;
    color: white;
    cursor: pointer;
}
nav {
    padding: 0.8rem;
    overflow: auto;
    flex: 1;
}
.nav-caption,
.submenu p {
    margin: 0.4rem 0.7rem;
    color: #71849d;
    font-size: 0.67rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-weight: 800;
}
.module-section {
    margin-top: 0.15rem;
}
nav a {
    display: flex;
    align-items: center;
    gap: 0.72rem;
    color: #c6d2e1;
    text-decoration: none;
    border-radius: 0.58rem;
    transition:
        background-color 0.16s,
        color 0.16s;
}
.module-link {
    min-height: 2.75rem;
    padding: 0.65rem 0.75rem;
    margin: 0.1rem 0;
    font-size: 0.92rem;
    font-weight: 650;
}
.module-link:hover,
.module-link.active {
    background: #17243a;
    color: white;
}
.module-link.active {
    box-shadow: inset 3px 0 #3b82f6;
}
.chevron {
    margin-left: auto;
    font-size: 0.72rem;
    color: #8294ac;
}
.submenu {
    margin: 0.2rem 0 0.55rem 1rem;
    padding: 0.25rem 0 0.25rem 0.55rem;
    border-left: 1px solid #2a3850;
}
.submenu a {
    min-height: 2.3rem;
    padding: 0.48rem 0.65rem;
    margin: 0.08rem 0;
    font-size: 0.82rem;
    color: #9fb0c5;
}
.submenu a i {
    font-size: 0.78rem;
}
.submenu a:hover,
.submenu a.active {
    background: #2563eb;
    color: white;
}
.user {
    display: flex;
    align-items: center;
    gap: 0.7rem;
    padding: 1rem;
    border-top: 1px solid #ffffff16;
    min-width: 0;
}
.user div {
    min-width: 0;
}
.avatar {
    flex: 0 0 auto;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: grid;
    place-items: center;
    background: #1e3a5f;
    color: #93c5fd;
    font-weight: 800;
}
.backdrop {
    display: none;
}
@media (max-width: 950px) {
    .sidebar {
        transform: translateX(-105%);
        transition: transform 0.2s;
    }
    .sidebar.open {
        transform: translateX(0);
    }
    .close {
        display: block;
    }
    .brand-logo {
        width: 9rem;
    }
    .backdrop {
        display: block;
        position: fixed;
        inset: 0;
        background: #0008;
        z-index: 35;
    }
}
</style>
