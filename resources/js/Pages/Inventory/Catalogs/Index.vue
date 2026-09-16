<script setup lang="ts">
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import Button from "primevue/button";
import Tag from "primevue/tag";
import InventoryNav from "../../../Components/Common/InventoryNav.vue";
type Row = Record<string, unknown> & { id: number; active: boolean };
const props = defineProps<{
    units: Row[];
    categories: Row[];
    suppliers: Row[];
    warehouses: Row[];
    locations: Row[];
    product_units: Row[];
    catalog_products: Row[];
    catalog_units: Row[];
}>();
const tab = ref<
    | "units"
    | "categories"
    | "suppliers"
    | "warehouses"
    | "locations"
    | "product-units"
>("categories");
const tabs = [
    "categories",
    "units",
    "product-units",
    "suppliers",
    "warehouses",
    "locations",
] as const;
const tabLabels: Record<(typeof tabs)[number], string> = {
    categories: "Categorías",
    units: "Unidades",
    "product-units": "Conversiones",
    suppliers: "Proveedores",
    warehouses: "Bodegas",
    locations: "Ubicaciones",
};
const form = useForm({
    code: "",
    name: "",
    symbol: "",
    dimension: "quantity",
    decimal_places: 0,
    description: "",
    sort_order: 0,
    parent_id: null as number | null,
    legal_name: "",
    trade_name: "",
    tax_id: "",
    contact_name: "",
    email: "",
    phone: "",
    address: "",
    payment_terms_days: 0,
    is_default: false,
    warehouse_id: "" as number | "",
    zone: "",
    aisle: "",
    rack: "",
    bin: "",
    product_id: "" as number | "",
    unit_id: "" as number | "",
    conversion_factor_to_base: "",
    is_purchase_unit: true,
    is_sale_unit: true,
    is_issue_unit: true,
});
const rows = computed(() =>
    tab.value === "product-units" ? props.product_units : props[tab.value],
);
const title = computed(() => tabLabels[tab.value]);
function submit() {
    form.post(`/inventory/catalogs/${tab.value}`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
function toggle(row: Row) {
    router.patch(
        `/inventory/catalogs/${tab.value}/${row.id}/active`,
        { active: !row.active },
        { preserveScroll: true },
    );
}
function label(row: Row) {
    if (tab.value === "suppliers") return String(row.legal_name);
    if (tab.value === "product-units")
        return `${String(row.sku)} — ${String(row.product_name)} / ${String(row.unit_name)}`;
    return String(row.name);
}
function selectTab(key: (typeof tabs)[number]) {
    tab.value = key;
    form.clearErrors();
}
</script>
<template>
    <Head title="Catálogos de inventario | Vidriería Maradiaga" />
    <main class="page">
        <InventoryNav />
        <h1>Catálogos maestros</h1>
        <p class="lead">
            Administra las opciones utilizadas por productos y movimientos.
        </p>
        <div class="tabs" role="tablist">
            <Button
                v-for="key in tabs"
                :key="key"
                :label="tabLabels[key]"
                :severity="tab === key ? 'primary' : 'secondary'"
                :outlined="tab !== key"
                @click="selectTab(key)"
            />
        </div>
        <section class="panel">
            <h2>Nueva {{ title.toLowerCase().replace(/s$/, "") }}</h2>
            <div class="grid">
                <template v-if="tab === 'units'"
                    ><label>Código *<input v-model.trim="form.code" /></label
                    ><label>Nombre *<input v-model.trim="form.name" /></label
                    ><label>Símbolo *<input v-model.trim="form.symbol" /></label
                    ><label
                        >Dimensión *<select v-model="form.dimension">
                            <option value="quantity">Cantidad</option>
                            <option value="length">Longitud</option>
                            <option value="area">Área</option>
                            <option value="weight">Peso</option>
                            <option value="volume">Volumen</option>
                        </select></label
                    ><label
                        >Decimales *<input
                            v-model.number="form.decimal_places"
                            type="number"
                            min="0"
                            max="4" /></label
                ></template>
                <template v-else-if="tab === 'categories'"
                    ><label>Código *<input v-model.trim="form.code" /></label
                    ><label>Nombre *<input v-model.trim="form.name" /></label
                    ><label
                        >Categoría superior<select v-model="form.parent_id">
                            <option :value="null">Ninguna</option>
                            <option
                                v-for="c in categories"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select></label
                    ><label
                        >Orden<input
                            v-model.number="form.sort_order"
                            type="number"
                            min="0" /></label
                    ><label class="wide"
                        >Descripción<textarea
                            v-model.trim="form.description"
                        /></label
                ></template>
                <template v-else-if="tab === 'product-units'"
                    ><label
                        >Producto *<select v-model="form.product_id">
                            <option value="">Selecciona</option>
                            <option
                                v-for="p in catalog_products"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.sku }} — {{ p.name }}
                            </option>
                        </select></label
                    ><label
                        >Unidad alterna *<select v-model="form.unit_id">
                            <option value="">Selecciona</option>
                            <option
                                v-for="u in catalog_units"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }} ({{ u.symbol }})
                            </option>
                        </select></label
                    ><label
                        >Factor hacia unidad base *<input
                            v-model.trim="form.conversion_factor_to_base"
                            inputmode="decimal"
                            placeholder="Ejemplo: 12" /></label
                    ><label class="check"
                        ><input
                            v-model="form.is_purchase_unit"
                            type="checkbox"
                        />
                        Disponible para compras</label
                    ><label class="check"
                        ><input v-model="form.is_sale_unit" type="checkbox" />
                        Disponible para ventas</label
                    ><label class="check"
                        ><input v-model="form.is_issue_unit" type="checkbox" />
                        Disponible para salidas</label
                    ></template
                >
                <template v-else-if="tab === 'suppliers'"
                    ><label>Código *<input v-model.trim="form.code" /></label
                    ><label
                        >Razón social *<input
                            v-model.trim="form.legal_name" /></label
                    ><label
                        >Nombre comercial<input
                            v-model.trim="form.trade_name" /></label
                    ><label
                        >RTN/identificación<input
                            v-model.trim="form.tax_id" /></label
                    ><label
                        >Contacto<input
                            v-model.trim="form.contact_name" /></label
                    ><label
                        >Correo<input
                            v-model.trim="form.email"
                            type="email" /></label
                    ><label>Teléfono<input v-model.trim="form.phone" /></label
                    ><label
                        >Días de crédito<input
                            v-model.number="form.payment_terms_days"
                            type="number"
                            min="0" /></label
                    ><label class="wide"
                        >Dirección<textarea
                            v-model.trim="form.address"
                        /></label
                ></template>
                <template v-else-if="tab === 'warehouses'"
                    ><label>Código *<input v-model.trim="form.code" /></label
                    ><label>Nombre *<input v-model.trim="form.name" /></label
                    ><label>Teléfono<input v-model.trim="form.phone" /></label
                    ><label class="check"
                        ><input v-model="form.is_default" type="checkbox" />
                        Bodega predeterminada</label
                    ><label class="wide"
                        >Dirección<textarea
                            v-model.trim="form.address"
                        /></label
                ></template>
                <template v-else
                    ><label
                        >Bodega *<select v-model="form.warehouse_id">
                            <option value="">Selecciona</option>
                            <option
                                v-for="w in warehouses"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.name }}
                            </option>
                        </select></label
                    ><label>Código *<input v-model.trim="form.code" /></label
                    ><label>Nombre *<input v-model.trim="form.name" /></label
                    ><label>Zona<input v-model.trim="form.zone" /></label
                    ><label>Pasillo<input v-model.trim="form.aisle" /></label
                    ><label>Estante<input v-model.trim="form.rack" /></label
                    ><label
                        >Compartimento<input v-model.trim="form.bin" /></label
                ></template>
            </div>
            <ul v-if="Object.keys(form.errors).length" class="errors">
                <li v-for="(e, k) in form.errors" :key="k">{{ e }}</li>
            </ul>
            <Button
                label="Guardar"
                icon="pi pi-save"
                :loading="form.processing"
                @click="submit"
            />
        </section>
        <section class="panel">
            <h2>{{ title }} registradas</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>
                                {{
                                    tab === "product-units"
                                        ? "Factor"
                                        : "Código"
                                }}
                            </th>
                            <th>Nombre</th>
                            <th v-if="tab === 'locations'">Bodega</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.id">
                            <td>
                                {{
                                    tab === "product-units"
                                        ? row.conversion_factor_to_base
                                        : row.code
                                }}
                            </td>
                            <td>{{ label(row) }}</td>
                            <td v-if="tab === 'locations'">
                                {{ row.warehouse_name }}
                            </td>
                            <td>
                                <Tag
                                    :severity="
                                        row.active ? 'success' : 'secondary'
                                    "
                                    :value="row.active ? 'Activo' : 'Inactivo'"
                                />
                            </td>
                            <td>
                                <Button
                                    :label="
                                        row.active ? 'Desactivar' : 'Activar'
                                    "
                                    :severity="
                                        row.active ? 'danger' : 'success'
                                    "
                                    size="small"
                                    @click="toggle(row)"
                                />
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
    max-width: 1200px;
    margin: auto;
    padding: clamp(1rem, 3vw, 2.5rem);
    font-family: system-ui, sans-serif;
}
.lead {
    color: var(--p-text-muted-color);
}
.tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin: 1.5rem 0;
}
.panel {
    margin-top: 1rem;
    padding: 1.4rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 1rem;
    background: var(--p-content-background);
}
.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}
.wide {
    grid-column: 1/-1;
}
label {
    display: grid;
    gap: 0.4rem;
    font-weight: 650;
}
.check {
    display: flex;
    align-items: center;
}
.check input {
    width: auto;
}
input,
select,
textarea {
    padding: 0.7rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 0.5rem;
    background: var(--p-form-field-background);
    color: var(--p-text-color);
}
textarea {
    min-height: 4rem;
}
.errors {
    color: var(--p-red-400);
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
    padding: 0.75rem;
    border-bottom: 1px solid var(--p-content-border-color);
    text-align: left;
}
@media (max-width: 700px) {
    .grid {
        grid-template-columns: 1fr;
    }
    .wide {
        grid-column: auto;
    }
}
</style>
