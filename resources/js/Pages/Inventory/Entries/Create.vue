<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { useI18n } from 'vue-i18n';

const toast = useToast();
const confirm = useConfirm();
const { t } = useI18n();
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
    category_id: number;
    category_name: string;
    base_unit_symbol: string;
    units: UnitOption[];
}

interface ProductSelectOption extends ProductOption {
    label: string;
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

const storageKey = 'vidrieria.inventory.pending-entry';

const saving = ref(false);
const ready = ref(false);
const failure = ref('');
const success = ref('');
const movementId = ref<number | null>(null);
const errors = ref<Record<string, string[]>>({});
const pending = ref<EntryPayload | null>(null);
const selectedCategoryId = ref<number | null>(null);

watch(success, (message) => {
    if (!message) return;
    toast.add({
        group: 'inventory-entry',
        severity: 'success',
        summary: t('inventory.entries.successTitle'),
        detail: message,
        life: 6000,
    });
});

watch(failure, (message) => {
    if (!message) return;
    const details = Object.values(errors.value).flat().join(' ');
    toast.add({
        group: 'inventory-entry',
        severity: 'error',
        summary: t('inventory.entries.errorTitle'),
        detail: details ? `${message} ${details}` : message,
    });
});

const form = reactive({
    product_id: '' as number | '',
    location_id: '' as number | '',
    unit_id: '' as number | '',
    supplier_id: '' as number | '',
    reason: 'purchase',
    document_date: props.today,
    reference: '',
    notes: '',
    quantity: '',
    unit_cost: '',
});

const selectedProduct = computed(() =>
    props.products.find((product) => product.id === form.product_id),
);

const categoryOptions = computed(() => {
    const categories = new Map<number, string>();

    for (const product of props.products) {
        categories.set(product.category_id, product.category_name);
    }

    return Array.from(categories, ([id, name]) => ({ id, name }))
        .sort((left, right) => left.name.localeCompare(right.name, 'es'));
});

const selectableProducts = computed<ProductSelectOption[]>(() =>
    props.products
        .filter((product) =>
            selectedCategoryId.value === null ||
            product.category_id === selectedCategoryId.value,
        )
        .map((product) => ({
            ...product,
            label: `${product.sku} — ${product.name}`,
        })),
);

const availableUnits = computed(() => selectedProduct.value?.units ?? []);

const selectedUnit = computed(() =>
    availableUnits.value.find((unit) => unit.id === form.unit_id),
);

const locked = computed(
    () => confirmationOpen.value || saving.value || pending.value !== null || movementId.value !== null,
);

const canRegister = computed(
    () =>
        props.products.length > 0 &&
        props.locations.length > 0 &&
        (form.reason !== 'purchase' || props.suppliers.length > 0),
);

watch(
    () => form.product_id,
    () => {
        form.unit_id = selectedProduct.value?.units[0]?.id ?? '';
    },
);

watch(selectedCategoryId, () => {
    if (
        selectedProduct.value &&
        selectedCategoryId.value !== null &&
        selectedProduct.value.category_id !== selectedCategoryId.value
    ) {
        form.product_id = '';
    }
});

watch(
    () => form.reason,
    (reason) => {
        if (reason !== 'purchase') form.supplier_id = '';
    },
);

onMounted(() => {
    try {
        const saved = sessionStorage.getItem(storageKey);

        if (saved) {
            const candidate = JSON.parse(saved) as EntryPayload;

            if (
                typeof candidate.operation_key !== 'string' ||
                typeof candidate.product_id !== 'number' ||
                typeof candidate.quantity !== 'string'
            ) {
                throw new Error(t('inventory.entries.invalidPending'));
            }

            pending.value = candidate;
            failure.value = t('inventory.entries.pendingExists');
        }

        ready.value = true;
    } catch {
        failure.value = t('inventory.entries.recoverError');
    }
});

function createPayload(): EntryPayload {
    return {
        operation_key: crypto.randomUUID(),
        product_id: Number(form.product_id),
        location_id: Number(form.location_id),
        unit_id: Number(form.unit_id),
        supplier_id: form.reason === 'purchase'
            ? Number(form.supplier_id)
            : null,
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

    failure.value = '';
    errors.value = {};
    saving.value = true;

    try {
        const payload = pending.value ?? createPayload();

        // Guardar antes de enviar para conservar la misma clave tras una recarga.
        sessionStorage.setItem(storageKey, JSON.stringify(payload));
        pending.value = payload;

        const response = await axios.post<EntryResponse>(
            '/inventory/entries',
            payload,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
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
                failure.value = t('inventory.entries.rejected');

                // Una primera petición rechazada puede corregirse.
                // Si era un reintento incierto, conservar su identidad.
                if (!isRetry) {
                    sessionStorage.removeItem(storageKey);
                    pending.value = null;
                }
            } else if (status === 401 || status === 419) {
                failure.value = t('inventory.entries.sessionExpired');
            } else if (status === 403) {
                failure.value = t('inventory.entries.forbidden');
            } else {
                failure.value = t('inventory.entries.uncertain');
            }
        } else {
            failure.value = t('inventory.entries.cannotComplete');
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
        group: 'inventory-entry',
        header: t('inventory.entries.confirmTitle'),
        message: t('inventory.entries.confirmMessage', {
            quantity: form.quantity,
            unit: selectedUnit.value?.symbol ?? '',
            product: selectedProduct.value?.name ?? t('common.product').toLowerCase(),
        }),
        icon: 'pi pi-question-circle',
        rejectProps: {
            label: t('common.cancel'),
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: t('inventory.entries.accept'),
            icon: 'pi pi-check',
            severity: 'success',
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
    success.value = '';
    failure.value = '';
    errors.value = {};

    form.reference = '';
    form.notes = '';
    form.quantity = '';
    form.unit_cost = '';
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

    <Head :title="`${t('inventory.entries.pageTitle')} | ${t('common.appName')}`" />

    <main class="entry-page">
        <Link href="/inventory/products" class="back">
            ← {{t('inventory.entries.back')}}
        </Link>

        <h1>{{t('inventory.entries.title')}}</h1>

        <p class="subtitle">
            {{t('inventory.entries.subtitle')}}
        </p>

        <div v-if="success" class="notice success" role="status">
            <p>{{ success }}</p>
            <p>{{t('inventory.entries.movement',{id:movementId})}}</p>

            <Button
                type="button"
                :label="t('inventory.entries.another')"
                severity="secondary"
                @click="newEntry"
            />
        </div>

        <div v-if="failure" class="notice failure" role="alert">
            <p>{{ failure }}</p>

            <ul>
                <li v-for="(messages, field) in errors" :key="field">
                    {{ messages.join(' ') }}
                </li>
            </ul>
        </div>

        <div v-if="pending && movementId === null" class="notice pending">
            <p>
                {{t('inventory.entries.pending',{product:pending.product_id,quantity:pending.quantity,reference:pending.reference ?? t('inventory.entries.noReference')})}}
            </p>

            <Button
                type="button"
                :label="t('inventory.entries.retrySame')"
                icon="pi pi-refresh"
                :loading="saving"
                :disabled="saving || !ready"
                @click="submit"
            />
        </div>

        <section class="panel">
            <p class="hint">
                {{t('inventory.entries.compatibleHint')}}
            </p>

            <p v-if="products.length === 0 || locations.length === 0" role="alert">
                {{t('inventory.entries.noOptions')}}
            </p>

            <form @submit.prevent="submit">
                <fieldset :disabled="locked || !ready">
                    <div class="grid">
                        <label>
                            {{t('inventory.entries.category')}}
                            <select v-model="selectedCategoryId">
                                <option :value="null">{{t('inventory.entries.allCategories')}}</option>

                                <option
                                    v-for="category in categoryOptions"
                                    :key="category.id"
                                    :value="category.id"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                        </label>

                        <label class="wide product-field">
                            {{t('inventory.entries.product')}} *
                            <Select
                                v-model="form.product_id"
                                :options="selectableProducts"
                                option-label="label"
                                option-value="id"
                                :placeholder="t('inventory.entries.chooseProduct')"
                                :filter-placeholder="t('inventory.entries.searchProduct')"
                                :filter-fields="['sku', 'name', 'category_name']"
                                filter
                                show-clear
                                class="product-select"
                                required
                            >
                                <template #option="{ option }">
                                    <div class="product-option">
                                        <strong>{{ option.sku }}</strong>
                                        <span>{{ option.name }}</span>
                                        <small>{{ option.category_name }}</small>
                                    </div>
                                </template>
                            </Select>
                        </label>

                        <label>
                            {{t('inventory.entries.reason')}} *
                            <select v-model="form.reason" required>
                                <option value="purchase">{{t('inventory.entries.purchase')}}</option>
                                <option value="initial_balance">
                                    {{t('inventory.entries.initialBalance')}}
                                </option>
                            </select>
                        </label>

                        <label>
                            {{t('inventory.entries.documentDate')}} *
                            <input
                                v-model="form.document_date"
                                type="date"
                                :max="today"
                                required
                            />
                        </label>

                        <label v-if="form.reason === 'purchase'" class="wide">
                            {{t('inventory.entries.supplier')}} *
                            <select v-model="form.supplier_id" required>
                                <option disabled value="">{{t('inventory.entries.chooseSupplier')}}</option>

                                <option
                                    v-for="supplier in suppliers"
                                    :key="supplier.id"
                                    :value="supplier.id"
                                >
                                    {{ supplier.name }}
                                </option>
                            </select>

                            <small v-if="suppliers.length === 0">
                                {{t('inventory.entries.supplierRequired')}}
                            </small>
                        </label>

                        <label class="wide">
                            {{t('inventory.entries.receiveLocation')}} *
                            <select v-model="form.location_id" required>
                                <option disabled value="">{{t('inventory.entries.chooseLocation')}}</option>

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
                            {{t('inventory.entries.receivedUnit')}} *
                            <select v-model="form.unit_id" required>
                                <option disabled value="">{{t('inventory.entries.chooseUnit')}}</option>

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
                            {{t('inventory.entries.receivedQuantity')}} *
                            <input
                                v-model="form.quantity"
                                type="text"
                                inputmode="decimal"
                                pattern="[0-9]{1,14}(\.[0-9]{1,4})?"
                                :placeholder="t('inventory.entries.quantityExample')"
                                required
                            />
                        </label>

                        <label>
                            {{t('inventory.entries.unitCost')}} *
                            <input
                                v-model="form.unit_cost"
                                type="text"
                                inputmode="decimal"
                                pattern="[0-9]{1,14}(\.[0-9]{1,4})?"
                                :placeholder="t('inventory.entries.costExample')"
                                required
                            />
                        </label>

                        <label>
                            {{t('inventory.entries.reference')}}
                            <input v-model="form.reference" maxlength="100" />
                        </label>

                        <label class="wide">
                            {{t('inventory.entries.notes')}}
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                maxlength="1000"
                            />
                        </label>
                    </div>

                    <p v-if="selectedUnit && selectedProduct" class="hint">
                        {{t('inventory.entries.conversion',{unit:selectedUnit.symbol,factor:selectedUnit.factor,base:selectedProduct.base_unit_symbol})}}
                        {{t('inventory.entries.conversionCheck')}}
                    </p>

                    <p class="hint">
                        {{t('inventory.entries.numericHelp')}}
                    </p>

                    <Button
                        type="submit"
                        :label="t('inventory.entries.confirm')"
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

.product-field {
    grid-column: 1 / -1;
}

.product-select {
    width: 100%;
}

.product-option {
    display: grid;
    grid-template-columns: minmax(5.5rem, auto) minmax(0, 1fr) auto;
    gap: 0.75rem;
    align-items: center;
    width: 100%;
}

.product-option small {
    color: var(--p-text-muted-color, #64748b);
    font-weight: 400;
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

    .product-option {
        grid-template-columns: 1fr;
        gap: 0.15rem;
    }

    .panel {
        padding: 1rem;
    }
}
</style>
