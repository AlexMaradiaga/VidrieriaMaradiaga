<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import axios from "axios";
import Button from "primevue/button";
import ConfirmDialog from "primevue/confirmdialog";
import Toast from "primevue/toast";
import { useConfirm } from "primevue/useconfirm";
import { useToast } from "primevue/usetoast";
import InventoryNav from "../../../Components/Common/InventoryNav.vue";

interface Product {
    id: number;
    sku: string;
    name: string;
    base_unit_symbol: string;
}
interface Option {
    id: number;
    name: string;
}
interface CountRow {
    id: number;
    document_date: string;
    status: string;
    location: string;
    creator: string;
    line_count: number;
    total_variance: string;
    notes: string | null;
}
const props = defineProps<{
    products: Product[];
    locations: Option[];
    today: string;
    counts: CountRow[];
}>();
const toast = useToast();
const confirm = useConfirm();
const saving = ref(false);
const showForm = ref(false);
const pending = ref<Record<string, unknown> | null>(null);
const storageKey = "vidrieria.inventory.pending-count";
const form = reactive({
    location_id: "" as number | "",
    document_date: props.today,
    notes: "",
    lines: [{ product_id: "" as number | "", counted_quantity: "" }],
});
onMounted(() => {
    const saved = sessionStorage.getItem(storageKey);
    if (saved) {
        try {
            pending.value = JSON.parse(saved) as Record<string, unknown>;
            showForm.value = true;
            toast.add({
                severity: "warn",
                summary: "Conteo pendiente",
                detail: "Reintenta la misma solicitud para confirmar su resultado.",
                life: 7000,
            });
        } catch {
            sessionStorage.removeItem(storageKey);
        }
    }
});
const availableProducts = (index: number) =>
    props.products.filter(
        (p) =>
            !form.lines.some(
                (line, i) => i !== index && line.product_id === p.id,
            ),
    );
function addLine() {
    form.lines.push({ product_id: "", counted_quantity: "" });
}
function removeLine(i: number) {
    if (form.lines.length > 1) form.lines.splice(i, 1);
}
function submit() {
    confirm.require({
        header: "Confirmar conteo físico",
        message:
            "Las diferencias reemplazarán la existencia de la ubicación y generarán ajustes en el Kardex.",
        icon: "pi pi-exclamation-triangle",
        rejectProps: {
            label: "Cancelar",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: { label: "Aplicar conteo", severity: "warning" },
        accept: () => void save(),
    });
}
async function save() {
    saving.value = true;
    try {
        const body = pending.value ?? {
            operation_key: crypto.randomUUID(),
            location_id: Number(form.location_id),
            document_date: form.document_date,
            notes: form.notes.trim() || null,
            lines: form.lines.map((line) => ({
                product_id: Number(line.product_id),
                counted_quantity: line.counted_quantity.trim(),
            })),
        };
        pending.value = body;
        sessionStorage.setItem(storageKey, JSON.stringify(body));
        const response = await axios.post("/inventory/counts", body, {
            headers: { Accept: "application/json" },
        });
        pending.value = null;
        sessionStorage.removeItem(storageKey);
        toast.add({
            severity: "success",
            summary: "Conteo registrado",
            detail: response.data.message,
            life: 5000,
        });
        window.location.reload();
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            pending.value = null;
            sessionStorage.removeItem(storageKey);
        }
        const detail =
            axios.isAxiosError(error) && error.response?.status === 422
                ? Object.values(
                      (
                          error.response.data as {
                              errors?: Record<string, string[]>;
                          }
                      ).errors ?? {},
                  )
                      .flat()
                      .join(" ")
                : "No se pudo confirmar el resultado. Reintenta la misma solicitud.";
        toast.add({
            severity: "error",
            summary: "Revisa el conteo",
            detail,
            life: 8000,
        });
    } finally {
        saving.value = false;
    }
}
function qty(value: string) {
    return Number(value).toLocaleString("en-US", { maximumFractionDigits: 4 });
}
</script>
<template>
    <Head
        title="Conteos físicos | Vidriería Maradiaga"
    /><Toast /><ConfirmDialog />
    <main class="page">
        <InventoryNav />
        <header>
            <div>
                <h1>Conteos físicos</h1>
                <p>
                    Registra lo observado en una ubicación; el sistema calcula y
                    documenta cada diferencia.
                </p>
            </div>
            <Button
                label="Nuevo conteo"
                icon="pi pi-plus"
                @click="showForm = !showForm"
            />
        </header>
        <section v-if="showForm" class="panel">
            <h2>Nuevo conteo ciego</h2>
            <div class="form-grid">
                <label
                    >Ubicación *<select v-model="form.location_id">
                        <option value="">Selecciona</option>
                        <option
                            v-for="item in locations"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.name }}
                        </option>
                    </select></label
                ><label
                    >Fecha *<input
                        v-model="form.document_date"
                        type="date"
                        :max="today" /></label
                ><label class="wide"
                    >Notas<textarea v-model="form.notes" />
                </label>
            </div>
            <h3>Productos contados</h3>
            <div v-for="(line, index) in form.lines" :key="index" class="line">
                <select v-model="line.product_id">
                    <option value="">Producto</option>
                    <option
                        v-for="product in availableProducts(index)"
                        :key="product.id"
                        :value="product.id"
                    >
                        {{ product.sku }} — {{ product.name }} ({{
                            product.base_unit_symbol
                        }})
                    </option></select
                ><input
                    v-model.trim="line.counted_quantity"
                    inputmode="decimal"
                    placeholder="Cantidad observada"
                /><Button
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    aria-label="Eliminar línea"
                    @click="removeLine(index)"
                />
            </div>
            <div class="actions">
                <Button
                    label="Agregar producto"
                    icon="pi pi-plus"
                    severity="secondary"
                    outlined
                    @click="addLine"
                /><Button
                    label="Confirmar conteo"
                    icon="pi pi-check"
                    severity="warning"
                    :loading="saving"
                    @click="submit"
                />
            </div>
        </section>
        <section class="panel">
            <h2>Conteos registrados</h2>
            <div class="table">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Fecha</th>
                            <th>Ubicación</th>
                            <th>Responsable</th>
                            <th class="num">Productos</th>
                            <th class="num">Variación absoluta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in counts" :key="row.id">
                            <td>{{ row.id }}</td>
                            <td>{{ row.document_date }}</td>
                            <td>{{ row.location }}</td>
                            <td>{{ row.creator }}</td>
                            <td class="num">{{ row.line_count }}</td>
                            <td class="num">{{ qty(row.total_variance) }}</td>
                        </tr>
                        <tr v-if="!counts.length">
                            <td colspan="6">
                                Todavía no hay conteos registrados.
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
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
header p {
    color: var(--p-text-muted-color);
}
.panel {
    margin-top: 1.5rem;
    padding: 1.4rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 1rem;
    background: var(--p-content-background);
}
.form-grid {
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
    width: 100%;
    padding: 0.7rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 0.5rem;
    background: var(--p-form-field-background);
    color: var(--p-text-color);
}
textarea {
    min-height: 4rem;
}
.line {
    display: grid;
    grid-template-columns: 2fr 1fr auto;
    gap: 0.75rem;
    margin: 0.7rem 0;
}
.actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
    margin-top: 1rem;
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
    padding: 0.8rem;
    border-bottom: 1px solid var(--p-content-border-color);
    text-align: left;
}
.num {
    text-align: right;
}
@media (max-width: 700px) {
    header {
        align-items: flex-start;
        flex-direction: column;
    }
    .form-grid,
    .line {
        grid-template-columns: 1fr;
    }
    .wide {
        grid-column: auto;
    }
}
</style>
