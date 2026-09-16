<script setup lang="ts">
import { computed, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import Button from "primevue/button";
import Tag from "primevue/tag";
import Toast from "primevue/toast";
import { useToast } from "primevue/usetoast";
import InventoryNav from "../../../Components/Common/InventoryNav.vue";
interface Product {
    id: number;
    sku: string;
    name: string;
}
interface Option {
    id: number;
    name: string;
}
interface Remnant {
    id: number;
    code: string;
    sku: string;
    product: string;
    warehouse: string;
    location: string;
    width_mm: string;
    height_mm: string;
    thickness_mm: string | null;
    quantity: number;
    area_m2: string;
    status: string;
    notes: string | null;
}
const props = defineProps<{
    products: Product[];
    locations: Option[];
    remnants: Remnant[];
}>();
const page = usePage<{ flash: { success?: string; error?: string } }>();
const toast = useToast();
const form = useForm({
    code: "",
    product_id: "" as number | "",
    location_id: "" as number | "",
    width_mm: "",
    height_mm: "",
    thickness_mm: "",
    quantity: 1,
    notes: "",
});
const area = computed(() => {
    const value =
        (Number(form.width_mm) *
            Number(form.height_mm) *
            Number(form.quantity)) /
        1_000_000;
    return Number.isFinite(value) ? value.toFixed(4) : "0.0000";
});
watch(
    () => page.props.flash.success,
    (message) => {
        if (message)
            toast.add({
                severity: "success",
                summary: "Retazos",
                detail: message,
                life: 5000,
            });
    },
    { immediate: true },
);
function submit() {
    form.post("/inventory/remnants", {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
function status(row: Remnant, value: string) {
    router.patch(
        `/inventory/remnants/${row.id}/status`,
        { status: value },
        { preserveScroll: true },
    );
}
const statusLabel = (value: string) =>
    ({
        available: "Disponible",
        reserved: "Reservado",
        consumed: "Consumido",
        discarded: "Descartado",
    })[value] ?? value;
const severity = (value: string) =>
    value === "available"
        ? "success"
        : value === "reserved"
          ? "warn"
          : "secondary";
</script>
<template>
    <Head title="Retazos | Vidriería Maradiaga" /><Toast />
    <main class="page">
        <InventoryNav />
        <h1>Control de retazos</h1>
        <p class="lead">
            Identifica piezas reutilizables por medida, ubicación y estado.
        </p>
        <section class="panel">
            <h2>Registrar retazo</h2>
            <div class="grid">
                <label
                    >Código *<input
                        v-model.trim="form.code"
                        maxlength="50"
                        placeholder="RET-0001" /></label
                ><label
                    >Producto *<select v-model="form.product_id">
                        <option value="">Selecciona</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">
                            {{ p.sku }} — {{ p.name }}
                        </option>
                    </select></label
                ><label
                    >Ubicación *<select v-model="form.location_id">
                        <option value="">Selecciona</option>
                        <option
                            v-for="l in locations"
                            :key="l.id"
                            :value="l.id"
                        >
                            {{ l.name }}
                        </option>
                    </select></label
                ><label
                    >Ancho (mm) *<input
                        v-model.trim="form.width_mm"
                        inputmode="decimal" /></label
                ><label
                    >Alto (mm) *<input
                        v-model.trim="form.height_mm"
                        inputmode="decimal" /></label
                ><label
                    >Espesor (mm)<input
                        v-model.trim="form.thickness_mm"
                        inputmode="decimal" /></label
                ><label
                    >Cantidad *<input
                        v-model.number="form.quantity"
                        type="number"
                        min="1" /></label
                ><label
                    >Área total calculada<input
                        :value="`${area} m²`"
                        disabled /></label
                ><label class="wide"
                    >Notas<textarea v-model.trim="form.notes" />
                </label>
            </div>
            <ul v-if="Object.keys(form.errors).length" class="errors">
                <li v-for="(message, key) in form.errors" :key="key">
                    {{ message }}
                </li>
            </ul>
            <Button
                label="Guardar retazo"
                icon="pi pi-save"
                :loading="form.processing"
                @click="submit"
            />
        </section>
        <section class="panel">
            <h2>Retazos registrados</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Ubicación</th>
                            <th>Medidas</th>
                            <th class="num">Área</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in remnants" :key="r.id">
                            <td>{{ r.code }}</td>
                            <td>{{ r.sku }} — {{ r.product }}</td>
                            <td>{{ r.warehouse }} / {{ r.location }}</td>
                            <td>
                                {{ r.width_mm }} × {{ r.height_mm }} mm<span
                                    v-if="r.thickness_mm"
                                >
                                    × {{ r.thickness_mm }} mm</span
                                >
                            </td>
                            <td class="num">{{ r.area_m2 }} m²</td>
                            <td>
                                <Tag
                                    :severity="severity(r.status)"
                                    :value="statusLabel(r.status)"
                                />
                            </td>
                            <td class="buttons">
                                <Button
                                    v-if="r.status === 'available'"
                                    label="Reservar"
                                    size="small"
                                    severity="warn"
                                    @click="status(r, 'reserved')"
                                /><Button
                                    v-if="r.status === 'reserved'"
                                    label="Liberar"
                                    size="small"
                                    severity="success"
                                    @click="status(r, 'available')"
                                /><Button
                                    v-if="
                                        r.status === 'available' ||
                                        r.status === 'reserved'
                                    "
                                    label="Consumir"
                                    size="small"
                                    severity="danger"
                                    @click="status(r, 'consumed')"
                                />
                            </td>
                        </tr>
                        <tr v-if="!remnants.length">
                            <td colspan="7">No hay retazos registrados.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
<style scoped>
.page {
    max-width: 1350px;
    margin: auto;
    padding: clamp(1rem, 3vw, 2.5rem);
    font-family: system-ui, sans-serif;
}
.lead {
    color: var(--p-text-muted-color);
}
.panel {
    margin-top: 1.5rem;
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
    gap: 0.35rem;
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
textarea {
    min-height: 4rem;
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
.num {
    text-align: right;
}
.buttons {
    display: flex;
    gap: 0.4rem;
    flex-wrap: wrap;
}
.errors {
    color: var(--p-red-400);
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
