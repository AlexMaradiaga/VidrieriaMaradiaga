<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';

interface ProductRow {
    id: number;
    sku: string;
    name: string;
    category_name: string | null;
    unit_symbol: string | null;
    sale_price: string;
    active: boolean;
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
    filters: { search: string };
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

const search = ref(props.filters.search);
const showForm = ref(false);
const saving = ref(false);
const loading = ref(false);
const success = ref('');
const failure = ref('');
const errors = ref<Record<string, string[]>>({});

const decimalFields = [
    {
        key: 'minimum_stock',
        label: 'Existencia mínima',
        required: true,
    },
    {
        key: 'maximum_stock',
        label: 'Existencia máxima',
        required: false,
    },
    {
        key: 'reorder_point',
        label: 'Cantidad para solicitar reposición',
        required: true,
    },
    {
        key: 'sale_price',
        label: 'Precio de venta',
        required: true,
    },
] as const;

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
        sale_price: '0',
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
    () => form.track_stock,
    (enabled) => {
        if (!enabled) {
            form.track_lots = false;
            form.track_remnants = false;
            form.allow_negative_stock = false;
        }
    },
);

function formatPrice(value: string): string {
    const match = /^(\d+)(?:\.(\d+))?$/.exec(value.trim());

    if (!match) return '—';

    const integerPart = match[1] ?? '0';
    const decimals = (match[2] ?? '').padEnd(3, '0');

    // Redondear para mostrar dos decimales sin convertir a float.
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

function loadProducts(pageNumber = 1): void {
    if (loading.value) return;

    router.get(
        '/inventory/products',
        {
            search: search.value.trim(),
            page: pageNumber,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

function openForm(): void {
    Object.assign(form, emptyForm());
    errors.value = {};
    failure.value = '';
    success.value = '';
    showForm.value = true;
}

async function saveProduct(): Promise<void> {
    if (saving.value) return;

    saving.value = true;
    errors.value = {};
    failure.value = '';
    success.value = '';

    try {
        await axios.post(
            '/inventory/products',
            {
                ...form,
                sku: form.sku.trim().toUpperCase(),
                name: form.name.trim(),
                barcode: form.barcode.trim() || null,
                description: form.description.trim() || null,
                maximum_stock: form.maximum_stock.trim() || null,
            },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        showForm.value = false;
        success.value = 'Producto creado correctamente.';

        // Buscar el SKU recién creado para mostrar el registro guardado.
        search.value = form.sku.trim().toUpperCase();
        loadProducts();
    } catch (error: unknown) {
        if (axios.isAxiosError<ValidationResponse>(error)) {
            const status = error.response?.status;

            if (status === 422) {
                errors.value = error.response?.data.errors ?? {};
                failure.value = 'Revisa los datos del formulario.';
            } else if (status === 403) {
                failure.value = 'No tienes permiso para crear productos.';
            } else if (status === 401 || status === 419) {
                failure.value =
                    'Tu sesión venció. Vuelve a iniciar sesión antes de guardar.';
            } else if (!error.response) {
                failure.value =
                    'No se recibió respuesta. Busca el SKU antes de reintentar para comprobar si se guardó.';
            } else {
                failure.value =
                    'No fue posible completar la operación. Busca el SKU antes de reintentar.';
            }
        } else {
            failure.value = 'Ocurrió un error inesperado.';
        }
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Head title="Productos | Vidriería Maradiaga" />

    <main class="catalog">
        <header class="header">
            <div>
                <Link href="/" class="back">← Panel principal</Link>
                <h1>Catálogo de productos</h1>
                <p>{{ products.total }} productos encontrados</p>
            </div>

            <Button
                v-if="canCreate && !showForm"
                label="Nuevo producto"
                icon="pi pi-plus"
                :disabled="loading"
                @click="openForm"
            />
        </header>

        <p v-if="success" class="notice success" role="status">
            {{ success }}
        </p>

        <section v-if="showForm" class="panel">
            <h2>Nuevo producto</h2>

            <p
                v-if="categories.length === 0 || units.length === 0"
                class="notice"
                role="alert"
            >
                Necesitas al menos una categoría y una unidad activas
                para crear productos.
            </p>

            <div v-if="failure" class="notice failure" role="alert">
                <p>{{ failure }}</p>
                <ul v-if="Object.keys(errors).length">
                    <li v-for="(messages, field) in errors" :key="field">
                        {{ messages.join(' ') }}
                    </li>
                </ul>
            </div>

            <form @submit.prevent="saveProduct">
                <fieldset :disabled="saving">
                    <div class="form-grid">
                        <label>
                            Código único del producto *
                            <input
                                v-model="form.sku"
                                required
                                maxlength="50"
                                pattern="[A-Za-z0-9][A-Za-z0-9._-]*"
                                autocomplete="off"
                                placeholder="Ejemplo: VID-TEMP-06"
                                aria-describedby="product-code-help"
                            />
                            <small id="product-code-help" class="hint">
                                Identifica cada material o variante. No debe repetirse.
                            </small>
                        </label>

                        <label>
                            Nombre *
                            <input
                                v-model="form.name"
                                required
                                maxlength="180"
                            />
                        </label>

                        <label>
                            Categoría *
                            <select v-model="form.category_id" required>
                                <option disabled value="">Selecciona</option>
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
                            Unidad de control del inventario *
                            <select v-model="form.base_unit_id" required>
                                <option disabled value="">Selecciona</option>
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
                            Tipo de producto *
                            <select v-model="form.product_type" required>
                                <option value="material">Materia prima</option>
                                <option value="finished_product">
                                    Producto terminado
                                </option>
                                <option value="consumable">Consumible</option>
                            </select>
                        </label>

                        <label>
                            Código de barras
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
                                pattern="[0-9]{1,14}(\.[0-9]{1,4})?"
                                :required="field.required"
                                placeholder="0.0000"
                                title="Usa punto decimal y hasta cuatro decimales."
                            />
                        </label>

                        <label class="full-width">
                            Descripción
                            <textarea
                                v-model="form.description"
                                rows="3"
                                maxlength="1000"
                            />
                        </label>
                    </div>

                    <p class="hint">
                        Los niveles de inventario se expresan en la unidad
                        base. Usa punto decimal, por ejemplo 12.5000.
                        Crear el producto no registra existencias.
                    </p>

                    <div class="checks">
                        <label>
                            <input v-model="form.track_stock" type="checkbox" />
                            Controlar stock
                        </label>

                        <label>
                            <input
                                v-model="form.track_lots"
                                type="checkbox"
                                :disabled="!form.track_stock"
                            />
                            Controlar lotes
                        </label>

                        <label>
                            <input
                                v-model="form.track_remnants"
                                type="checkbox"
                                :disabled="!form.track_stock"
                            />
                            Controlar retazos
                        </label>

                        <label>
                            <input
                                v-model="form.allow_negative_stock"
                                type="checkbox"
                                :disabled="!form.track_stock"
                            />
                            Permitir stock negativo
                        </label>
                    </div>

                    <div class="actions">
                        <Button
                            type="submit"
                            label="Guardar producto"
                            icon="pi pi-check"
                            :loading="saving"
                            :disabled="
                                saving ||
                                categories.length === 0 ||
                                units.length === 0
                            "
                        />

                        <Button
                            type="button"
                            label="Cancelar"
                            severity="secondary"
                            outlined
                            :disabled="saving"
                            @click="showForm = false"
                        />
                    </div>
                </fieldset>
            </form>
        </section>

        <section class="panel" :aria-busy="loading">
            <form class="search-bar" @submit.prevent="loadProducts(1)">
                <label for="product-search">Buscar por código o nombre</label>

                <div class="search-controls">
                    <input
                        id="product-search"
                        v-model="search"
                        type="search"
                        maxlength="180"
                        placeholder="Ejemplo: perfil natural"
                    />

                    <Button
                        type="submit"
                        label="Buscar"
                        icon="pi pi-search"
                        :loading="loading"
                        :disabled="loading || saving"
                    />
                </div>
            </form>

            <div class="table-scroll">
                <table>
                    <caption class="table-caption">
                        Productos registrados
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col">Código</th>
                            <th scope="col">Producto</th>
                            <th scope="col">Categoría</th>
                            <th scope="col">Unidad base</th>
                            <th scope="col">Precio de venta</th>
                            <th scope="col">Estado</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="product in products.data" :key="product.id">
                            <td class="sku">{{ product.sku }}</td>
                            <td>{{ product.name }}</td>
                            <td>{{ product.category_name ?? '—' }}</td>
                            <td>{{ product.unit_symbol ?? '—' }}</td>
                            <td class="number">{{ formatPrice(product.sale_price) }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="{ inactive: !product.active }"
                                >
                                    {{ product.active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="products.data.length === 0">
                            <td colspan="6" class="empty">
                                No hay productos que coincidan con la búsqueda.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav class="pagination" aria-label="Paginación de productos">
                <Button
                    label="Anterior"
                    severity="secondary"
                    outlined
                    :disabled="
                        products.current_page <= 1 || loading || saving
                    "
                    @click="loadProducts(products.current_page - 1)"
                />

                <span>
                    Página {{ products.current_page }}
                    de {{ products.last_page }}
                </span>

                <Button
                    label="Siguiente"
                    severity="secondary"
                    outlined
                    :disabled="
                        products.current_page >= products.last_page ||
                        loading ||
                        saving
                    "
                    @click="loadProducts(products.current_page + 1)"
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

.back {
    color: var(--p-primary-color, #0f766e);
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
    border: 0;
    padding: 0;
    margin: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}

.form-grid label,
.search-bar > label {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    font-size: 0.9rem;
    font-weight: 600;
}

input:not([type='checkbox']),
select,
textarea {
    width: 100%;
    min-width: 0;
    padding: 0.7rem;
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
    margin-top: 1rem;
    color: var(--p-text-muted-color, #64748b);
    font-size: 0.85rem;
}

.notice {
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 0.5rem;
    line-height: 1.6;
    background: #fff7ed;
    color: #9a3412;
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
    padding-left: 1.25rem;
    list-style: disc;
}

.search-bar {
    margin-bottom: 1.5rem;
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
    text-align: left;
    padding-bottom: 0.75rem;
    font-weight: 600;
}

th,
td {
    padding: 0.9rem;
    border-bottom: 1px solid var(--p-content-border-color, #e2e8f0);
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

.badge {
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

    .panel {
        padding: 1rem;
    }

    .actions,
    .pagination {
        flex-wrap: wrap;
    }
}
</style>
