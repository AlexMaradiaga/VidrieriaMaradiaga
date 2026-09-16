<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import axios from "axios";
import Button from "primevue/button";
import Toast from "primevue/toast";
import ConfirmDialog from "primevue/confirmdialog";
import { useToast } from "primevue/usetoast";
import { useConfirm } from "primevue/useconfirm";

const toast = useToast();
const confirm = useConfirm();
const confirmationOpen = ref(false);

interface Option {
    id: number;
    name: string;
}

interface UnitOption extends Option {
    symbol: string;
    factor: string;
}

interface ProductOption extends Option {
    sku: string;
    base_unit_symbol: string;
    units: UnitOption[];
}

interface EntryPayload {
    operation_key: string;
    product_id: number;
    location_id: number;
    unit_id: number;
    supplier_id: number | null;
    reason: string;
    document_date: string;
    reference: string | null;
    notes: string | null;
    quantity: string;
    unit_cost: string;
}

interface EntryResponse {
    message: string;
    data: {
        movement_id: number;
        repeated: boolean;
    };
}

interface ValidationResponse {
    errors?: Record<string, string[]>;
}

const props = defineProps<{
    products: ProductOption[];
    locations: Option[];
    suppliers: Option[];
    today: string;
}>();

const storageKey = "vidrieria.inventory.pending-entry";

const saving = ref(false);
const ready = ref(false);
const failure = ref("");
const success = ref("");
const movementId = ref<number | null>(null);
const errors = ref<Record<string, string[]>>({});
const pending = ref<EntryPayload | null>(null);

watch(success, (message) => {
    if (!message) return;
    toast.add({
        group: "inventory-entry",
        severity: "success",
        summary: "Entrada registrada",
        detail: message,
        life: 6000,
    });
});

watch(failure, (message) => {
    if (!message) return;
    const details = Object.values(errors.value).flat().join(" ");
    toast.add({
        group: "inventory-entry",
        severity: "error",
        summary: "Revisá la entrada",
        detail: details ? `${message} ${details}` : message,
    });
});

const form = reactive({
    product_id: "" as number | "",
    location_id: "" as number | "",
    unit_id: "" as number | "",
    supplier_id: "" as number | "",
    reason: "purchase",
    document_date: props.today,
    reference: "",
    notes: "",
    quantity: "",
    unit_cost: "",
});

const selectedProduct = computed(() =>
    props.products.find((product) => product.id === form.product_id),
);

const availableUnits = computed(() => selectedProduct.value?.units ?? []);

const selectedUnit = computed(() =>
    availableUnits.value.find((unit) => unit.id === form.unit_id),
);

const locked = computed(
    () =>
        confirmationOpen.value ||
        saving.value ||
        pending.value !== null ||
        movementId.value !== null,
);

const canRegister = computed(
    () =>
        props.products.length > 0 &&
        props.locations.length > 0 &&
        (form.reason !== "purchase" || props.suppliers.length > 0),
);

watch(
    () => form.product_id,
    () => {
        form.unit_id = selectedProduct.value?.units[0]?.id ?? "";
    },
);

watch(
    () => form.reason,
    (reason) => {
        if (reason !== "purchase") form.supplier_id = "";
    },
);

onMounted(() => {
    try {
        const saved = sessionStorage.getItem(storageKey);

        if (saved) {
            const candidate = JSON.parse(saved) as EntryPayload;

            if (
                typeof candidate.operation_key !== "string" ||
                typeof candidate.product_id !== "number" ||
                typeof candidate.quantity !== "string"
            ) {
                throw new Error("Solicitud pendiente inválida.");
            }

            pending.value = candidate;
            failure.value =
                "Existe una entrada pendiente de confirmar. Reintenta esa misma solicitud antes de registrar otra.";
        }

        ready.value = true;
    } catch {
        failure.value =
            "No se pudo recuperar el estado de la solicitud. Revisa los movimientos antes de iniciar otra entrada.";
    }
});

function createPayload(): EntryPayload {
    return {
        operation_key: crypto.randomUUID(),
        product_id: Number(form.product_id),
        location_id: Number(form.location_id),
        unit_id: Number(form.unit_id),
        supplier_id:
            form.reason === "purchase" ? Number(form.supplier_id) : null,
        reason: form.reason,
        document_date: form.document_date,
        reference: form.reference.trim() || null,
        notes: form.notes.trim() || null,
        quantity: form.quantity.trim(),
        unit_cost: form.unit_cost.trim(),
    };
}

async function saveEntry(): Promise<void> {
    if (!ready.value || saving.value || movementId.value !== null) return;

    const isRetry = pending.value !== null;

    if (!isRetry && !canRegister.value) return;

    failure.value = "";
    errors.value = {};
    saving.value = true;

    try {
        const payload = pending.value ?? createPayload();

        // Guardar antes de enviar para conservar la misma clave tras una recarga.
        sessionStorage.setItem(storageKey, JSON.stringify(payload));
        pending.value = payload;

        const response = await axios.post<EntryResponse>(
            "/inventory/entries",
            payload,
            {
                headers: {
                    Accept: "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
            },
        );

        movementId.value = response.data.data.movement_id;
        success.value = response.data.message;

        sessionStorage.removeItem(storageKey);
        pending.value = null;
    } catch (error: unknown) {
        if (axios.isAxiosError<ValidationResponse>(error)) {
            const status = error.response?.status;

            if (status === 422) {
                errors.value = error.response?.data.errors ?? {};
                failure.value = "La entrada fue rechazada. Revisa los datos.";

                // Una primera petición rechazada puede corregirse.
                // Si era un reintento incierto, conservar su identidad.
                if (!isRetry) {
                    sessionStorage.removeItem(storageKey);
                    pending.value = null;
                }
            } else if (status === 401 || status === 419) {
                failure.value =
                    "La sesión venció. Inicia sesión nuevamente y vuelve a esta pantalla para reintentar.";
            } else if (status === 403) {
                failure.value =
                    "No tienes permiso para confirmar entradas. La solicitud pendiente se conserva.";
            } else {
                failure.value =
                    "No se pudo confirmar el resultado. Reintenta la misma solicitud; no registres otra entrada equivalente.";
            }
        } else {
            failure.value =
                "No fue posible completar la operación o conservar su estado. Verifica el resultado antes de registrar otra entrada.";
        }
    } finally {
        saving.value = false;
    }
}

function submit(): void {
    if (
        !ready.value ||
        saving.value ||
        confirmationOpen.value ||
        movementId.value !== null
    ) {
        return;
    }

    if (pending.value !== null) {
        void saveEntry();
        return;
    }

    if (!canRegister.value) return;

    confirmationOpen.value = true;

    confirm.require({
        group: "inventory-entry",
        header: "Confirmar entrada de inventario",
        message:
            `Se registrarán ${form.quantity} ` +
            `${selectedUnit.value?.symbol ?? ""} de ` +
            `${selectedProduct.value?.name ?? "este producto"}. ` +
            "Esta operación aumentará las existencias.",
        icon: "pi pi-question-circle",
        rejectProps: {
            label: "Cancelar",
            severity: "secondary",
            outlined: true,
        },
        acceptProps: {
            label: "Sí, registrar entrada",
            icon: "pi pi-check",
            severity: "success",
        },
        accept: () => {
            confirmationOpen.value = false;
            void saveEntry();
        },
        reject: () => {
            confirmationOpen.value = false;
        },
        onHide: () => {
            confirmationOpen.value = false;
        },
    });
}

function newEntry(): void {
    if (saving.value || pending.value !== null) return;

    movementId.value = null;
    success.value = "";
    failure.value = "";
    errors.value = {};

    form.reference = "";
    form.notes = "";
    form.quantity = "";
    form.unit_cost = "";
}
</script>

<template>
    <Toast
        group="inventory-entry"
        position="top-right"
        :breakpoints="{
            '640px': {
                width: 'calc(100% - 2rem)',
                right: '1rem',
                left: '1rem',
            },
        }"
    />

    <ConfirmDialog
        group="inventory-entry"
        :style="{ width: '30rem', maxWidth: 'calc(100vw - 2rem)' }"
    />

    <Head title="Registrar entrada | Vidriería Maradiaga" />

    <main class="entry-page">
        <Link href="/inventory/products" class="back">
            ← Catálogo de productos
        </Link>

        <h1>Registrar entrada de inventario</h1>

        <p class="subtitle">
            Registra material recibido y actualiza las existencias de su
            ubicación.
        </p>

        <div v-if="success" class="notice success" role="status">
            <p>{{ success }}</p>
            <p>Movimiento registrado: {{ movementId }}</p>

            <Button
                type="button"
                label="Registrar otra entrada"
                severity="secondary"
                @click="newEntry"
            />
        </div>

        <div v-if="failure" class="notice failure" role="alert">
            <p>{{ failure }}</p>

            <ul>
                <li v-for="(messages, field) in errors" :key="field">
                    {{ messages.join(" ") }}
                </li>
            </ul>
        </div>

        <div v-if="pending && movementId === null" class="notice pending">
            <p>
                Solicitud pendiente: producto #{{ pending.product_id }},
                cantidad {{ pending.quantity }}, referencia
                {{ pending.reference ?? "sin referencia" }}.
            </p>

            <Button
                type="button"
                label="Reintentar la misma solicitud"
                icon="pi pi-refresh"
                :loading="saving"
                :disabled="saving || !ready"
                @click="submit"
            />
        </div>

        <section class="panel">
            <p class="hint">
                Solo aparecen productos activos con control de existencias que
                no requieren detalle de lotes o retazos.
            </p>

            <p
                v-if="products.length === 0 || locations.length === 0"
                role="alert"
            >
                No hay productos compatibles o ubicaciones activas disponibles.
            </p>

            <form @submit.prevent="submit">
                <fieldset :disabled="locked || !ready">
                    <div class="grid">
                        <label class="wide">
                            Producto *
                            <select v-model="form.product_id" required>
                                <option disabled value="">
                                    Selecciona un producto
                                </option>

                                <option
                                    v-for="product in products"
                                    :key="product.id"
                                    :value="product.id"
                                >
                                    {{ product.sku }} — {{ product.name }}
                                </option>
                            </select>
                        </label>

                        <label>
                            Motivo *
                            <select v-model="form.reason" required>
                                <option value="purchase">
                                    Compra a proveedor
                                </option>
                                <option value="initial_balance">
                                    Existencias al iniciar el sistema
                                </option>
                            </select>
                        </label>

                        <label>
                            Fecha del documento *
                            <input
                                v-model="form.document_date"
                                type="date"
                                :max="today"
                                required
                            />
                        </label>

                        <label v-if="form.reason === 'purchase'" class="wide">
                            Proveedor *
                            <select v-model="form.supplier_id" required>
                                <option disabled value="">
                                    Selecciona un proveedor
                                </option>

                                <option
                                    v-for="supplier in suppliers"
                                    :key="supplier.id"
                                    :value="supplier.id"
                                >
                                    {{ supplier.name }}
                                </option>
                            </select>

                            <small v-if="suppliers.length === 0">
                                Debes registrar un proveedor antes de capturar
                                una compra.
                            </small>
                        </label>

                        <label class="wide">
                            Ubicación donde se recibe *
                            <select v-model="form.location_id" required>
                                <option disabled value="">
                                    Selecciona una ubicación
                                </option>

                                <option
                                    v-for="location in locations"
                                    :key="location.id"
                                    :value="location.id"
                                >
                                    {{ location.name }}
                                </option>
                            </select>
                        </label>

                        <label>
                            Unidad recibida *
                            <select v-model="form.unit_id" required>
                                <option disabled value="">
                                    Selecciona una unidad
                                </option>

                                <option
                                    v-for="unit in availableUnits"
                                    :key="unit.id"
                                    :value="unit.id"
                                >
                                    {{ unit.name }} ({{ unit.symbol }})
                                </option>
                            </select>
                        </label>

                        <label>
                            Cantidad recibida *
                            <input
                                v-model="form.quantity"
                                type="text"
                                inputmode="decimal"
                                pattern="[0-9]{1,14}(\.[0-9]{1,4})?"
                                placeholder="Ejemplo: 10"
                                required
                            />
                        </label>

                        <label>
                            Costo por unidad recibida *
                            <input
                                v-model="form.unit_cost"
                                type="text"
                                inputmode="decimal"
                                pattern="[0-9]{1,14}(\.[0-9]{1,4})?"
                                placeholder="Ejemplo: 180.00"
                                required
                            />
                        </label>

                        <label>
                            Factura o referencia
                            <input v-model="form.reference" maxlength="100" />
                        </label>

                        <label class="wide">
                            Observaciones
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                maxlength="1000"
                            />
                        </label>
                    </div>

                    <p v-if="selectedUnit && selectedProduct" class="hint">
                        Cada {{ selectedUnit.symbol }} equivale a
                        {{ selectedUnit.factor }}
                        {{ selectedProduct.base_unit_symbol }}. La conversión se
                        verifica nuevamente al confirmar.
                    </p>

                    <p class="hint">
                        Escribe cantidades y costos sin separadores de miles,
                        usando punto decimal. El costo es por la unidad
                        recibida, no el precio de venta.
                    </p>

                    <Button
                        type="submit"
                        label="Confirmar entrada"
                        icon="pi pi-check"
                        :loading="saving"
                        :disabled="locked || !ready || !canRegister"
                    />
                </fieldset>
            </form>
        </section>
    </main>
</template>

<style scoped>
.entry-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: clamp(1rem, 3vw, 2.5rem);
    font-family: system-ui, sans-serif;
}

.back {
    color: var(--p-primary-color, #0f766e);
}

h1 {
    margin: 1rem 0 0.5rem;
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 700;
}

.subtitle,
.hint {
    color: var(--p-text-muted-color, #64748b);
    line-height: 1.6;
}

.subtitle {
    margin-bottom: 1.5rem;
}

.panel {
    padding: 1.5rem;
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 1rem;
    background: var(--p-content-background, #fff);
}

fieldset {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

label {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-size: 0.9rem;
    font-weight: 600;
}

input,
select,
textarea {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    padding: 0.75rem;
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 0.5rem;
    background: var(--p-form-field-background, #fff);
    color: var(--p-text-color, #172338);
    font: inherit;
}

input:focus-visible,
select:focus-visible,
textarea:focus-visible {
    outline: 2px solid var(--p-primary-color, #0f766e);
    outline-offset: 2px;
}

fieldset:disabled {
    opacity: 0.7;
}

.wide {
    grid-column: 1 / -1;
}

.hint {
    margin: 1rem 0;
    font-size: 0.85rem;
}

.notice {
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 0.75rem;
    line-height: 1.6;
}

.notice p {
    margin-bottom: 0.75rem;
}

.notice ul {
    list-style: disc;
    padding-left: 1.25rem;
}

.success {
    background: #ecfdf5;
    color: #065f46;
}

.failure {
    background: #fef2f2;
    color: #991b1b;
}

.pending {
    background: #fff7ed;
    color: #9a3412;
}

@media (max-width: 640px) {
    .grid {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 1rem;
    }
}
</style>
