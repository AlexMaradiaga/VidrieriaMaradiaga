<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import { useI18n } from 'vue-i18n';

interface ProductRow {
    id: number;
    sku: string;
    name: string;
    category_name: string | null;
    unit_symbol: string | null;
    sale_price: string;
    active: boolean;
    stock_quantity: string;
    barcode: string | null;
    description: string | null;
    minimum_stock: string;
    maximum_stock: string | null;
    reorder_point: string;
}

interface Option {
    id: number;
    name: string;
}

interface UnitOption extends Option {
    symbol: string;
}

interface ProductForm {
    sku: string;
    name: string;
    category_id: number | '';
    base_unit_id: number | '';
    product_type: string;
    barcode: string;
    description: string;
    minimum_stock: string;
    maximum_stock: string;
    reorder_point: string;
    sale_price: string;
    track_stock: boolean;
    track_lots: boolean;
    track_remnants: boolean;
    allow_negative_stock: boolean;
}

interface ValidationResponse {
    errors?: Record<string, string[]>;
}

const props = defineProps<{
    products: {
        data: ProductRow[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search: string;
        category_id: number | null;
        status: 'all' | 'active' | 'inactive';
        sort: 'sku_asc' | 'sku_desc' | 'name_asc' | 'name_desc' | 'category_asc';
        per_page: 15 | 30 | 50 | 100;
    };
    categories: Option[];
    units: UnitOption[];
}>();

const page = usePage<{
    auth: {
        permissions: string[];
    };
}>();

const canCreate = computed(() =>
    page.props.auth.permissions.includes('inventory.products.create'),
);

const canUpdate = computed(() =>
    page.props.auth.permissions.includes('inventory.products.update'),
);

const canChangeStatus = computed(() =>
    page.props.auth.permissions.includes('inventory.products.toggle-active'),
);

const search = ref(props.filters.search);
const categoryId = ref<number | ''>(props.filters.category_id ?? '');
const status = ref(props.filters.status);
const sort = ref(props.filters.sort);
const perPage = ref(props.filters.per_page);
const showEditor = ref(false);
const editingId = ref<number | null>(null);
const editingCategory = ref('');
const editingUnit = ref('');
const originalSalePrice = ref<string | null>(null);
const salePriceEdited = ref(false);

const saving = ref(false);
const loading = ref(false);
const changingStatus = ref<number | null>(null);

const success = ref('');
const failure = ref('');
const errors = ref<Record<string, string[]>>({});
const toast = useToast();
const confirm = useConfirm();
const { t } = useI18n();

watch(success, (message) => {
    if (message) toast.add({ severity: 'success', summary: t('inventory.products.toast'), detail: message, life: 5000 });
});
watch(failure, (message) => {
    if (message) toast.add({ severity: 'error', summary: t('inventory.products.errorTitle'), detail: message, life: 7000 });
});

const editorHeading = ref<HTMLHeadingElement | null>(null);

const isEditing = computed(() => editingId.value !== null);

const busy = computed(
    () => saving.value || loading.value || changingStatus.value !== null,
);

const hasCreationOptions = computed(
    () => props.categories.length > 0 && props.units.length > 0,
);

const canSubmit = computed(() =>
    isEditing.value
        ? canUpdate.value
        : canCreate.value && hasCreationOptions.value,
);

type DecimalFieldKey = 'minimum_stock' | 'maximum_stock' | 'reorder_point' | 'sale_price';
const decimalFields = computed(() => [
    {
        key: 'minimum_stock',
        label: t('inventory.products.minimum'),
        required: true,
    },
    {
        key: 'maximum_stock',
        label: t('inventory.products.maximum'),
        required: false,
    },
    {
        key: 'reorder_point',
        label: t('inventory.products.reorder'),
        required: true,
    },
    {
        key: 'sale_price',
        label: t('inventory.products.salePrice'),
        required: true,
    },
] as const);

function emptyForm(): ProductForm {
    return {
        sku: '',
        name: '',
        category_id: '',
        base_unit_id: '',
        product_type: 'material',
        barcode: '',
        description: '',
        minimum_stock: '0',
        maximum_stock: '',
        reorder_point: '0',
        sale_price: '0.00',
        track_stock: true,
        track_lots: false,
        track_remnants: false,
        allow_negative_stock: false,
    };
}

const form = reactive<ProductForm>(emptyForm());

watch(
    () => props.filters.search,
    (value) => {
        search.value = value;
    },
);

watch(
    () => props.filters,
    (filters) => {
        categoryId.value = filters.category_id ?? '';
        status.value = filters.status;
        sort.value = filters.sort;
        perPage.value = filters.per_page;
    },
    { deep: true },
);

const hasFilters = computed(() =>
    search.value.trim() !== '' ||
    categoryId.value !== '' ||
    status.value !== 'all' ||
    sort.value !== 'sku_asc' ||
    perPage.value !== 30,
);

watch(
    () => form.track_stock,
    (enabled) => {
        if (!enabled) {
            form.track_lots = false;
            form.track_remnants = false;
            form.allow_negative_stock = false;
        }
    },
);
function formatQuantity(value: string | null): string {
    if (value === null || value === '') return '';

    const [integerPart = '0', decimalPart = ''] = value.split('.');
    const fraction = decimalPart.replace(/0+$/, '');

    return fraction ? `${integerPart}.${fraction}` : integerPart;
}

function priceForInput(value: string): string {
    // En el formulario no usamos separadores de miles.
    return formatPrice(value).replace(/,/g, '');
}

function normalizeNumericField(key: DecimalFieldKey): void {
    const value = form[key].trim();

    if (key === 'sale_price') {
        if (/^\d{1,14}(?:\.\d{1,2})?$/.test(value)) {
            form.sale_price = priceForInput(value);
        }
    } else if (/^\d{1,14}(?:\.\d{1,4})?$/.test(value)) {
        form[key] = formatQuantity(value);
    }
}

function formatPrice(value: string): string {
    const match = /^(\d+)(?:\.(\d+))?$/.exec(value.trim());

    if (!match) return '—';

    const integerPart = match[1] ?? '0';
    const decimals = (match[2] ?? '').padEnd(3, '0');

    let cents =
        BigInt(integerPart) * BigInt(100) +
        BigInt(decimals.slice(0, 2));

    if (Number(decimals.charAt(2)) >= 5) {
        cents += BigInt(1);
    }

    const whole = (cents / BigInt(100))
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    const fraction = (cents % BigInt(100))
        .toString()
        .padStart(2, '0');

    return `${whole}.${fraction}`;
}

function clearFeedback(): void {
    success.value = '';
    failure.value = '';
    errors.value = {};
}

async function focusEditor(): Promise<void> {
    await nextTick();
    editorHeading.value?.focus();
}

function openCreate(): void {
    if (busy.value || !canCreate.value) return;

    clearFeedback();
    editingId.value = null;
    editingCategory.value = '';
    editingUnit.value = '';
    originalSalePrice.value = null;
    salePriceEdited.value = false;
    Object.assign(form, emptyForm());
    showEditor.value = true;

    void focusEditor();
}

function openEdit(product: ProductRow): void {
    if (busy.value || !canUpdate.value) return;

    clearFeedback();

    // Estos campos deben venir incluidos en la consulta del backend.
    if (
        typeof product.minimum_stock !== 'string' ||
        typeof product.reorder_point !== 'string' ||
        !('maximum_stock' in product) ||
        !('barcode' in product) ||
        !('description' in product)
    ) {
        failure.value = t('inventory.products.incomplete');
        return;
    }

    editingId.value = product.id;
    editingCategory.value = product.category_name ?? t('inventory.products.noCategory');
    editingUnit.value = product.unit_symbol ?? t('inventory.products.noUnit');
    originalSalePrice.value = product.sale_price;
    salePriceEdited.value = false;

    Object.assign(form, emptyForm(), {
        sku: product.sku,
        name: product.name,
        barcode: product.barcode ?? '',
        description: product.description ?? '',
        minimum_stock: formatQuantity(product.minimum_stock),
        maximum_stock: formatQuantity(product.maximum_stock),
        reorder_point: formatQuantity(product.reorder_point),
        sale_price: priceForInput(product.sale_price),
    });

    showEditor.value = true;

    void focusEditor();
}

function closeEditor(): void {
    if (busy.value) return;

    showEditor.value = false;
    editingId.value = null;
    failure.value = '';
    errors.value = {};
}

function refreshProducts(pageNumber = 1): void {
    if (loading.value) return;

    loading.value = true;

    router.get(
        '/inventory/products',
        {
            search: search.value.trim(),
            category_id: categoryId.value || undefined,
            status: status.value,
            sort: sort.value,
            per_page: perPage.value,
            page: pageNumber,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onError: () => {
                failure.value = t('inventory.products.refreshError');
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

function searchProducts(): void {
    if (busy.value) return;

    clearFeedback();
    refreshProducts(1);
}

function clearSearch(): void {
    if (busy.value) return;

    search.value = '';
    categoryId.value = '';
    status.value = 'all';
    sort.value = 'sku_asc';
    perPage.value = 30;
    searchProducts();
}

function goToPage(pageNumber: number): void {
    if (busy.value) return;

    refreshProducts(pageNumber);
}

function handleMutationError(error: unknown): void {
    errors.value = {};

    if (axios.isAxiosError<ValidationResponse>(error)) {
        const status = error.response?.status;

        if (status === 422) {
            errors.value = error.response?.data?.errors ?? {};
            failure.value = t('inventory.products.invalidData');
            return;
        }

        if (status === 403) {
            failure.value = t('inventory.products.forbidden');
            return;
        }

        if (status === 404) {
            failure.value = t('inventory.products.notFound');
            return;
        }

        if (status === 401 || status === 419) {
            failure.value = t('inventory.products.sessionExpired');
            return;
        }
    }

    failure.value = t('inventory.products.uncertain');
}

async function submitProduct(): Promise<void> {
    if (busy.value || !canSubmit.value) return;

    clearFeedback();
    saving.value = true;

    for (const field of decimalFields.value) {
        normalizeNumericField(field.key);
    }

    const productId = editingId.value;
    const productCode = form.sku.trim().toUpperCase();

    // Campos permitidos tanto para crear como para editar.
    const editableData = {
        name: form.name.trim(),
        barcode: form.barcode.trim() || null,
        description: form.description.trim() || null,
        minimum_stock: form.minimum_stock.trim(),
        maximum_stock: form.maximum_stock.trim() || null,
        reorder_point: form.reorder_point.trim(),
        // Conservar la precisión original si el usuario no editó el precio.
        sale_price: originalSalePrice.value !== null && !salePriceEdited.value
            ? originalSalePrice.value
            : form.sale_price.trim(),
    };

    const config = {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    try {
        if (productId !== null) {
            // No enviar código, unidad, costos ni estado al endpoint de edición.
            await axios.put(
                `/inventory/products/${productId}`,
                editableData,
                config,
            );
        } else {
            await axios.post(
                '/inventory/products',
                {
                    ...editableData,
                    sku: productCode,
                    category_id: form.category_id,
                    base_unit_id: form.base_unit_id,
                    product_type: form.product_type,
                    track_stock: form.track_stock,
                    track_lots: form.track_lots,
                    track_remnants: form.track_remnants,
                    allow_negative_stock: form.allow_negative_stock,
                },
                config,
            );
        }

        showEditor.value = false;
        editingId.value = null;

        success.value = t(productId !== null ? 'inventory.products.updated' : 'inventory.products.created');

        // Mostrar el registro incluso si el nombre editado ya no coincide
        // con la búsqueda anterior.
        search.value = productCode;
        refreshProducts(1);
    } catch (error: unknown) {
        handleMutationError(error);
    } finally {
        saving.value = false;
    }
}

async function changeStatus(product: ProductRow): Promise<void> {
    if (busy.value || !canChangeStatus.value || showEditor.value) return;

    const targetStatus = !product.active;
    const action = t(targetStatus ? 'common.activate' : 'common.deactivate');

    confirm.require({
        header: t('inventory.products.toggleHeader', {action}),
        message: t('inventory.products.confirmToggle', {action,sku:product.sku,name:product.name}),
        icon: 'pi pi-question-circle',
        rejectProps: { label: t('common.cancel'), severity: 'secondary', outlined: true },
        acceptProps: { label: action, severity: targetStatus ? 'success' : 'danger' },
        accept: () => void performStatusChange(product, targetStatus),
    });
}

async function performStatusChange(product: ProductRow, targetStatus: boolean): Promise<void> {
    if (busy.value) return;

    clearFeedback();
    changingStatus.value = product.id;

    try {
        await axios.patch(
            `/inventory/products/${product.id}/active`,
            { active: targetStatus },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        success.value = t(targetStatus ? 'inventory.products.activated' : 'inventory.products.deactivated');

        refreshProducts(props.products.current_page);
    } catch (error: unknown) {
        handleMutationError(error);
    } finally {
        changingStatus.value = null;
    }
}
</script>

<template>
    <Head :title="`${t('inventory.products.pageTitle')} | ${t('common.appName')}`" />
    <Toast />
    <ConfirmDialog />

    <main class="catalog">
        <header class="header">
            <div>
                <Link href="/" class="back">← {{t('nav.panel')}}</Link>
                <h1>{{t('inventory.products.title')}}</h1>
                <p class="subtitle">
                    {{t('inventory.products.found',{count:products.total})}}
                </p>
            </div>

            <Button
                v-if="canCreate && !showEditor"
                type="button"
                :label="t('inventory.products.new')"
                icon="pi pi-plus"
                :disabled="busy"
                @click="openCreate"
            />
        </header>

        <div v-if="failure" class="notice failure" role="alert">
            <p>{{ failure }}</p>

            <ul v-if="Object.keys(errors).length">
                <li v-for="(messages, field) in errors" :key="field">
                    {{ messages.join(' ') }}
                </li>
            </ul>
        </div>

        <section
            v-if="showEditor"
            class="panel"
            aria-labelledby="editor-heading"
        >
            <h2
                id="editor-heading"
                ref="editorHeading"
                tabindex="-1"
            >
                {{ isEditing ? t('inventory.products.editTitle',{sku:form.sku}) : t('inventory.products.createTitle') }}
            </h2>

            <p v-if="isEditing" class="hint">
                {{t('inventory.products.categoryHint',{category:editingCategory,unit:editingUnit})}}
            </p>

            <p
                v-if="!isEditing && !hasCreationOptions"
                class="notice"
                role="alert"
            >
                {{t('inventory.products.missingCatalogs')}}
            </p>

            <form @submit.prevent="submitProduct">
                <fieldset :disabled="busy">
                    <div class="form-grid">
                        <label>
                            {{t('inventory.products.uniqueCode')}} *
                            <input
                                v-model="form.sku"
                                required
                                maxlength="50"
                                pattern="[A-Za-z0-9][A-Za-z0-9._-]*"
                                autocomplete="off"
                                :readonly="isEditing"
                                :placeholder="t('inventory.products.codeExample')"
                                aria-describedby="product-code-help"
                            />

                            <small id="product-code-help" class="hint">
                                {{
                                    isEditing
                                        ? t('inventory.products.codeKept')
                                        : t('inventory.products.codeHelp')
                                }}
                            </small>
                        </label>

                        <label>
                            {{t('common.name')}} *
                            <input
                                v-model="form.name"
                                required
                                maxlength="180"
                                :placeholder="t('inventory.products.nameExample')"
                            />
                        </label>

                        <template v-if="!isEditing">
                            <label>
                                {{t('inventory.products.category')}} *
                                <select v-model="form.category_id" required>
                                    <option disabled value="">{{t('common.select')}}</option>

                                    <option
                                        v-for="category in categories"
                                        :key="category.id"
                                        :value="category.id"
                                    >
                                        {{ category.name }}
                                    </option>
                                </select>
                            </label>

                            <label>
                                {{t('inventory.products.controlUnit')}} *
                                <select v-model="form.base_unit_id" required>
                                    <option disabled value="">{{t('common.select')}}</option>

                                    <option
                                        v-for="unit in units"
                                        :key="unit.id"
                                        :value="unit.id"
                                    >
                                        {{ unit.name }} ({{ unit.symbol }})
                                    </option>
                                </select>
                            </label>

                            <label>
                                {{t('inventory.products.type')}} *
                                <select v-model="form.product_type" required>
                                    <option value="material">{{t('inventory.products.rawMaterial')}}</option>
                                    <option value="finished_product">
                                        {{t('inventory.products.finishedProduct')}}
                                    </option>
                                    <option value="consumable">{{t('inventory.products.consumable')}}</option>
                                </select>
                            </label>
                        </template>

                        <label>
                            {{t('inventory.products.barcode')}}
                            <input v-model="form.barcode" maxlength="80" />
                        </label>

                        <label
                            v-for="field in decimalFields"
                            :key="field.key"
                        >
                            {{ field.label }}{{ field.required ? ' *' : '' }}

                            <input
                                v-model="form[field.key]"
                                type="text"
                                inputmode="decimal"
                                :pattern="field.key === 'sale_price'
                                    ? '[0-9]{1,14}(\\.[0-9]{1,2})?'
                                    : '[0-9]{1,14}(\\.[0-9]{1,4})?'"
                                :required="field.required"
                                :placeholder="field.key === 'sale_price' ? '0.00' : '0'"
                                :title="field.key === 'sale_price'
                                    ? t('inventory.products.decimalPrice')
                                    : t('inventory.products.decimalQuantity')"
                                @input="field.key === 'sale_price' && (salePriceEdited = true)"
                                @blur="normalizeNumericField(field.key)"
                            />

                            <small
                                v-if="field.key === 'reorder_point'"
                                class="hint"
                            >
                                {{t('inventory.products.reorderHelp')}}
                            </small>

                            <small
                                v-if="field.key === 'maximum_stock'"
                                class="hint"
                            >
                                {{t('inventory.products.maximumHelp')}}
                            </small>
                        </label>

                        <label class="full-width">
                            {{t('common.description')}}
                            <textarea
                                v-model="form.description"
                                rows="3"
                                maxlength="1000"
                            />
                        </label>
                    </div>

                    <p class="hint form-help">
                        {{t('inventory.products.formHelp')}}
                    </p>

                    <div v-if="!isEditing" class="checks">
                        <label>
                            <input v-model="form.track_stock" type="checkbox" />
                            {{t('inventory.products.trackStock')}}
                        </label>

                        <label>
                            <input
                                v-model="form.track_lots"
                                type="checkbox"
                                :disabled="!form.track_stock"
                            />
                            {{t('inventory.products.trackLots')}}
                        </label>

                        <label>
                            <input
                                v-model="form.track_remnants"
                                type="checkbox"
                                :disabled="!form.track_stock"
                            />
                            {{t('inventory.products.trackRemnants')}}
                        </label>

                        <label>
                            <input
                                v-model="form.allow_negative_stock"
                                type="checkbox"
                                :disabled="!form.track_stock"
                            />
                            {{t('inventory.products.allowNegative')}}
                        </label>
                    </div>

                    <div class="actions">
                        <Button
                            type="submit"
                            :label="isEditing ? t('inventory.products.saveChanges') : t('inventory.products.saveProduct')"
                            icon="pi pi-check"
                            :loading="saving"
                            :disabled="busy || !canSubmit"
                        />

                        <Button
                            type="button"
                            :label="t('common.cancel')"
                            severity="secondary"
                            outlined
                            :disabled="busy"
                            @click="closeEditor"
                        />
                    </div>
                </fieldset>
            </form>
        </section>

        <section class="panel" :aria-busy="loading">
            <form class="search-bar" @submit.prevent="searchProducts">
                <div class="filter-grid">
                    <label for="product-search">
                        {{t('inventory.products.search')}}
                    <input
                        id="product-search"
                        v-model="search"
                        type="search"
                        maxlength="180"
                        :placeholder="t('inventory.products.searchExample')"
                        :disabled="busy"
                    />
                    </label>

                    <label>
                        {{t('inventory.products.filterCategory')}}
                        <select v-model="categoryId" :disabled="busy">
                            <option value="">{{t('inventory.products.allCategories')}}</option>

                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </label>

                    <label>
                        {{t('inventory.products.filterStatus')}}
                        <select v-model="status" :disabled="busy">
                            <option value="all">{{t('common.all')}}</option>
                            <option value="active">{{t('common.active')}}</option>
                            <option value="inactive">{{t('common.inactive')}}</option>
                        </select>
                    </label>

                    <label>
                        {{t('inventory.products.orderBy')}}
                        <select v-model="sort" :disabled="busy">
                            <option value="sku_asc">{{t('inventory.products.skuAscending')}}</option>
                            <option value="sku_desc">{{t('inventory.products.skuDescending')}}</option>
                            <option value="name_asc">{{t('inventory.products.nameAscending')}}</option>
                            <option value="name_desc">{{t('inventory.products.nameDescending')}}</option>
                            <option value="category_asc">{{t('inventory.products.categoryAscending')}}</option>
                        </select>
                    </label>

                    <label>
                        {{t('inventory.products.rowsPerPage')}}
                        <select v-model="perPage" :disabled="busy">
                            <option :value="15">15</option>
                            <option :value="30">30</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </label>
                </div>

                <div class="search-controls">

                    <Button
                        type="submit"
                        :label="t('inventory.products.searchButton')"
                        icon="pi pi-search"
                        :loading="loading"
                        :disabled="busy"
                    />

                    <Button
                        v-if="hasFilters"
                        type="button"
                        :label="t('inventory.products.viewAll')"
                        severity="secondary"
                        outlined
                        :disabled="busy"
                        @click="clearSearch"
                    />
                </div>
            </form>

            <div class="table-scroll">
                <table>
                    <caption class="table-caption">
                        {{t('inventory.products.registered')}}
                    </caption>

                    <thead>
                        <tr>
                            <th scope="col">{{t('common.code')}}</th>
                            <th scope="col">{{t('common.product')}}</th>
                            <th scope="col">{{t('inventory.products.category')}}</th>
                            <th scope="col">{{t('inventory.products.controlUnit')}}</th>
                            <th scope="col">{{t('inventory.products.totalStock')}}</th>
                            <th scope="col">{{t('inventory.products.salePriceColumn')}}</th>
                            <th scope="col">{{t('common.status')}}</th>
                            <th scope="col">{{t('common.actions')}}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="product in products.data" :key="product.id">
                            <td class="sku">{{ product.sku }}</td>
                            <td>{{ product.name }}</td>
                            <td>{{ product.category_name ?? '—' }}</td>
                            <td>{{ product.unit_symbol ?? '—' }}</td>
                            <td class="number">
                                {{ formatQuantity(product.stock_quantity ?? null) || '—' }}
                            </td>

                            <td class="number">
                                {{ formatPrice(product.sale_price) }}
                            </td>

                            <td>
                                <span
                                    class="badge"
                                    :class="{ inactive: !product.active }"
                                >
                                    {{ product.active ? t('common.active') : t('common.inactive') }}
                                </span>
                            </td>

                            <td>
                                <div class="row-actions">
                                    <Button
                                        v-if="canUpdate"
                                        type="button"
                                        :label="t('inventory.products.edit')"
                                        icon="pi pi-pencil"
                                        severity="info"
                                        :aria-label="`${t('inventory.products.edit')} ${product.sku}`"
                                        :disabled="busy || showEditor"
                                        @click="openEdit(product)"
                                    />

                                    <Button
                                        v-if="canChangeStatus"
                                        type="button"
                                        :label="product.active ? t('common.deactivate') : t('common.activate')"
                                        :icon="product.active ? 'pi pi-ban' : 'pi pi-check'"
                                        :severity="product.active ? 'danger' : 'success'"
                                        :aria-label="`${product.active ? t('common.deactivate') : t('common.activate')} ${product.sku}`"
                                        :loading="changingStatus === product.id"
                                        :disabled="busy || showEditor"
                                        @click="changeStatus(product)"
                                    />

                                    <span
                                        v-if="!canUpdate && !canChangeStatus"
                                        class="hint"
                                    >
                                        {{t('inventory.products.viewOnly')}}
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="products.data.length === 0">
                            <td colspan="8" class="empty">
                                {{t('inventory.products.empty')}}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav class="pagination" :aria-label="t('inventory.history.pagination')">
                <Button
                    type="button"
                    :label="t('common.previous')"
                    severity="secondary"
                    outlined
                    :disabled="products.current_page <= 1 || busy"
                    @click="goToPage(products.current_page - 1)"
                />

                <span>
                    {{t('common.page',{current:products.current_page,last:products.last_page})}}
                </span>

                <Button
                    type="button"
                    :label="t('common.next')"
                    severity="secondary"
                    outlined
                    :disabled="
                        products.current_page >= products.last_page || busy
                    "
                    @click="goToPage(products.current_page + 1)"
                />
            </nav>
        </section>
    </main>
</template>

<style scoped>
.catalog {
    max-width: 1440px;
    margin: 0 auto;
    padding: clamp(1rem, 3vw, 2.5rem);
    font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.header,
.actions,
.pagination,
.search-controls {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.header {
    justify-content: space-between;
    margin-bottom: 1.5rem;
}

h1 {
    margin: 0.75rem 0 0.5rem;
    font-size: clamp(1.5rem, 3vw, 2rem);
    font-weight: 700;
}

h2 {
    margin-bottom: 1.25rem;
    font-size: 1.25rem;
    font-weight: 700;
}

.subtitle,
.hint {
    color: var(--p-text-muted-color, #64748b);
}

.back {
    color: var(--p-primary-color, #0f766e);
    text-decoration: none;
}

.back:hover {
    text-decoration: underline;
}

.panel {
    margin-bottom: 1.5rem;
    padding: 1.5rem;
    border: 1px solid var(--p-content-border-color, #dce2e9);
    border-radius: 1rem;
    background: var(--p-content-background, #fff);
}

fieldset {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.form-grid label,
.filter-grid label {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    font-size: 0.9rem;
    font-weight: 600;
}

input:not([type='checkbox']),
select,
textarea {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    padding: 0.7rem;
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 0.5rem;
    background: var(--p-form-field-background, #fff);
    color: var(--p-text-color, #172338);
    font: inherit;
}

input[readonly] {
    background: var(--p-content-hover-background, #f1f5f9);
}

input:disabled,
select:disabled,
textarea:disabled {
    opacity: 0.65;
    cursor: not-allowed;
}

input:focus-visible,
select:focus-visible,
textarea:focus-visible,
.back:focus-visible {
    outline: 2px solid var(--p-primary-color, #0f766e);
    outline-offset: 2px;
}

.full-width {
    grid-column: 1 / -1;
}

.checks {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin: 1.25rem 0;
}

.checks label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.hint {
    font-size: 0.85rem;
    font-weight: 400;
    line-height: 1.5;
}

.form-help {
    margin: 1rem 0;
}

.actions {
    margin-top: 1rem;
    flex-wrap: wrap;
}

.notice {
    margin-bottom: 1rem;
    padding: 1rem;
    border-radius: 0.5rem;
    background: #fff7ed;
    color: #9a3412;
    line-height: 1.6;
}

.notice p {
    margin: 0;
}

.success {
    background: #ecfdf5;
    color: #065f46;
}

.failure {
    background: #fef2f2;
    color: #991b1b;
}

.notice ul {
    margin-top: 0.5rem;
    padding-left: 1.25rem;
    list-style: disc;
}

.search-bar {
    margin-bottom: 1.5rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: minmax(16rem, 2fr) repeat(4, minmax(9rem, 1fr));
    gap: 1rem;
}

.search-controls {
    margin-top: 0.5rem;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.table-caption {
    padding-bottom: 0.75rem;
    text-align: left;
    font-weight: 600;
}

th,
td {
    padding: 0.9rem;
    border-bottom: 1px solid var(--p-content-border-color, #e2e8f0);
    vertical-align: middle;
}

th {
    white-space: nowrap;
    font-size: 0.85rem;
}

.sku,
.number {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.number {
    text-align: right;
}

.badge {
    display: inline-block;
    padding: 0.25rem 0.6rem;
    border-radius: 1rem;
    background: #dcfce7;
    color: #166534;
    font-size: 0.8rem;
}

.inactive {
    background: #f1f5f9;
    color: #475569;
}

.row-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    min-width: 190px;
}

.empty {
    padding: 2rem;
    text-align: center;
}

.pagination {
    justify-content: space-between;
    margin-top: 1.25rem;
}

@media (max-width: 640px) {
    .header,
    .search-controls {
        align-items: stretch;
        flex-direction: column;
    }

    .form-grid {
        grid-template-columns: 1fr;
    }

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .panel {
        padding: 1rem;
    }

    .pagination {
        flex-wrap: wrap;
    }
}

@media (min-width: 641px) and (max-width: 1100px) {
    .filter-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
