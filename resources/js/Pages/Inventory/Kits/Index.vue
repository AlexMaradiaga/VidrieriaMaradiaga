<script setup lang="ts">
import { reactive, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import InventoryNav from "../../../Components/Common/InventoryNav.vue";
interface Product {
    id: number;
    sku: string;
    name: string;
    base_unit_symbol: string;
}
interface Item {
    product_id: number;
    sku: string;
    name: string;
    unit: string;
    quantity: string;
    stock: string;
}
interface Kit {
    id: number;
    code: string;
    name: string;
    description: string | null;
    active: boolean;
    availability: number;
    items: Item[];
}
const props = defineProps<{ products: Product[]; kits: Kit[] }>();
const toast = useToast();
const page = usePage<{ flash: { success?: string } }>();
const saving = ref(false);
const errors = ref<Record<string, string>>({});
const form = reactive({
    code: "",
    name: "",
    description: "",
    items: [{ product_id: "" as number | "", quantity: "" }],
});
if (page.props.flash.success)
    toast.add({
        severity: "success",
        summary: "Kits",
        detail: page.props.flash.success,
        life: 5000,
    });
function add() {
    form.items.push({ product_id: "", quantity: "" });
}
function remove(i: number) {
    if (form.items.length > 1) form.items.splice(i, 1);
}
function submit() {
    saving.value = true;
    router.post(
        "/inventory/kits",
        {
            ...form,
            items: form.items.map((i) => ({
                product_id: Number(i.product_id),
                quantity: i.quantity,
            })),
        },
        {
            preserveScroll: true,
            onError: (e) => {
                errors.value = e;
            },
            onSuccess: () => {
                form.code = "";
                form.name = "";
                form.description = "";
                form.items = [{ product_id: "", quantity: "" }];
            },
            onFinish: () => (saving.value = false),
        },
    );
}
function toggle(kit: Kit) {
    router.patch(
        `/inventory/kits/${kit.id}/active`,
        { active: !kit.active },
        { preserveScroll: true },
    );
}
</script>
<template>
    <Head title="Kits de productos | Vidriería Maradiaga" /><Toast />
    <main class="page">
        <InventoryNav />
        <h1>Kits y composiciones</h1>
        <p class="lead">
            Agrupa materiales recurrentes y consulta cuántos kits pueden
            prepararse con la existencia actual.
        </p>
        <section class="panel">
            <h2>Nuevo kit</h2>
            <div class="grid">
                <label
                    >Código *<input
                        v-model.trim="form.code"
                        placeholder="KIT-VENTANA-01" /></label
                ><label>Nombre *<input v-model.trim="form.name" /></label
                ><label class="wide"
                    >Descripción<textarea v-model.trim="form.description" />
                </label>
            </div>
            <h3>Componentes</h3>
            <div v-for="(item, index) in form.items" :key="index" class="line">
                <select v-model="item.product_id">
                    <option value="">Selecciona un producto</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">
                        {{ p.sku }} — {{ p.name }} ({{ p.base_unit_symbol }})
                    </option></select
                ><input
                    v-model.trim="item.quantity"
                    inputmode="decimal"
                    placeholder="Cantidad por kit"
                /><Button
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    @click="remove(index)"
                />
            </div>
            <ul v-if="Object.keys(errors).length" class="errors">
                <li v-for="(e, k) in errors" :key="k">{{ e }}</li>
            </ul>
            <div class="actions">
                <Button
                    label="Agregar componente"
                    icon="pi pi-plus"
                    severity="secondary"
                    outlined
                    @click="add"
                /><Button
                    label="Guardar kit"
                    icon="pi pi-save"
                    :loading="saving"
                    @click="submit"
                />
            </div>
        </section>
        <section class="cards">
            <article v-for="kit in kits" :key="kit.id" class="kit">
                <header>
                    <div>
                        <h2>{{ kit.code }} — {{ kit.name }}</h2>
                        <p>{{ kit.description || "Sin descripción" }}</p>
                    </div>
                    <Tag
                        :severity="kit.active ? 'success' : 'secondary'"
                        :value="kit.active ? 'Activo' : 'Inactivo'"
                    />
                </header>
                <p class="availability">
                    Disponibles para preparar:
                    <strong>{{ kit.availability }}</strong>
                </p>
                <table>
                    <thead>
                        <tr>
                            <th>Componente</th>
                            <th class="num">Por kit</th>
                            <th class="num">Existencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in kit.items" :key="item.product_id">
                            <td>{{ item.sku }} — {{ item.name }}</td>
                            <td class="num">
                                {{ item.quantity }} {{ item.unit }}
                            </td>
                            <td class="num">
                                {{ item.stock }} {{ item.unit }}
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Button
                    :label="kit.active ? 'Desactivar' : 'Activar'"
                    :severity="kit.active ? 'danger' : 'success'"
                    size="small"
                    @click="toggle(kit)"
                />
            </article>
            <p v-if="!kits.length">No hay kits registrados.</p>
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
.lead,
.kit header p {
    color: var(--p-text-muted-color);
}
.panel,
.kit {
    margin-top: 1.5rem;
    padding: 1.4rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 1rem;
    background: var(--p-content-background);
}
.grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.wide {
    grid-column: 1/-1;
}
label {
    display: grid;
    gap: 0.4rem;
    font-weight: 650;
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
.line {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 0.7rem;
    margin: 0.7rem 0;
}
.actions,
.kit header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.cards {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
.kit table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}
.kit th,
.kit td {
    padding: 0.6rem;
    border-bottom: 1px solid var(--p-content-border-color);
    text-align: left;
}
.num {
    text-align: right !important;
}
.availability {
    font-size: 1.1rem;
}
.errors {
    color: var(--p-red-400);
}
@media (max-width: 800px) {
    .cards,
    .grid,
    .line {
        grid-template-columns: 1fr;
    }
    .wide {
        grid-column: auto;
    }
}
</style>
