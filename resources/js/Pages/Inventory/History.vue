<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

interface Option {
    id: number;
    name: string;
}

interface Options {
    products: Option[];
    locations: Option[];
    suppliers: Option[];
}

interface Pagination<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filters {
    from: string | null;
    to: string | null;
    product_id: number | null;
    location_id: number | null;
    page: number;
    search?: string;
    status?: string | null;
    reason?: string | null;
    supplier_id?: number | null;
}

interface EntryRow {
    id: number;
    document_date: string;
    posted_at: string | null;
    reason: string;
    status: string;
    reference: string | null;
    supplier: string | null;
    creator: string | null;
    total_cost: string;
    line_count: number;
}

interface EntryLine {
    id: number;
    line_number: number;
    product_id: number;
    sku: string;
    product: string;
    warehouse: string;
    location: string;
    unit: string;
    base_unit: string;
    quantity: string;
    conversion_factor: string;
    base_quantity: string;
    unit_cost: string;
    base_unit_cost: string;
    total_cost: string;
    balance_after: string | null;
    average_cost_after: string | null;
    notes: string | null;
}

interface EntryDetail {
    header: Omit<EntryRow, 'total_cost' | 'line_count'> & {
        notes: string | null;
        created_at: string;
        poster: string | null;
        cancelled_at: string | null;
        canceller: string | null;
        cancellation_reason: string | null;
    };
    total_cost: string;
    lines: Pagination<EntryLine>;
}

interface LedgerRow {
    id: number;
    movement_id: number;
    document_date: string;
    posted_at: string;
    direction: string;
    reason: string;
    reference: string | null;
    warehouse: string;
    location: string;
    unit: string;
    base_quantity: string;
    total_cost: string;
    average_cost_after: string | null;
    running_balance: string;
}

interface Ledger {
    product: {
        id: number;
        sku: string;
        name: string;
        unit: string;
    };
    summary: {
        opening: string;
        incoming: string;
        outgoing: string;
        closing: string;
        current: string;
        ledger_total: string;
        difference: string;
    };
    rows: Pagination<LedgerRow>;
}

const props = defineProps<{
    mode: 'entries' | 'detail' | 'kardex';
    filters: Filters | null;
    options: Options | null;
    entries: Pagination<EntryRow> | null;
    entry: EntryDetail | null;
    ledger: Ledger | null;
}>();

const page = usePage<{
    auth: { permissions: string[] };
    errors: Record<string, string>;
}>();

const emptyFilters = (): Filters => ({
    from: null,
    to: null,
    product_id: null,
    location_id: null,
    supplier_id: null,
    search: '',
    status: '',
    reason: '',
    page: 1,
});

const form = reactive<Filters>({
    ...emptyFilters(),
    ...props.filters,
});

watch(
    () => props.filters,
    (filters) => {
        Object.assign(form, emptyFilters(), filters ?? {});
    },
);

const title = computed(() => {
    if (props.mode === 'detail') {
        return `Entrada #${props.entry?.header.id ?? ''}`;
    }

    return props.mode === 'kardex'
        ? 'Kardex por producto'
        : 'Historial de entradas';
});

const listPath = computed(() =>
    props.mode === 'kardex'
        ? '/inventory/kardex'
        : '/inventory/entries',
);

const pagination = computed(() => {
    if (props.mode === 'detail') return props.entry?.lines ?? null;
    if (props.mode === 'kardex') return props.ledger?.rows ?? null;
    return props.entries;
});

const locationName = computed(() =>
    props.options?.locations.find(
        (location) => location.id === props.filters?.location_id,
    )?.name ?? 'Todas las ubicaciones',
);

function allowed(permission: string): boolean {
    return page.props.auth.permissions.includes(permission);
}

function money(value: string | null): string {
    if (value === null) return '—';

    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(value);

    if (!match) return '—';

    const fraction = (match[3] ?? '').padEnd(3, '0');

    let cents =
        BigInt(match[2]!) * 100n +
        BigInt(fraction.slice(0, 2));

    if (Number(fraction[2]) >= 5) cents += 1n;

    const whole = (cents / 100n)
        .toString()
        .replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    const decimals = (cents % 100n)
        .toString()
        .padStart(2, '0');

    return `${match[1] && cents !== 0n ? '-' : ''}${whole}.${decimals}`;
}

function quantity(value: string | null): string {
    if (value === null) return '—';

    const match = /^(-?)(\d+)(?:\.(\d+))?$/.exec(value);

    if (!match) return '—';

    const whole = match[2]!
        .replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    const fraction = (match[3] ?? '').replace(/0+$/, '');

    return `${match[1]}${whole}${fraction ? `.${fraction}` : ''}`;
}

function date(value: string | null, withTime = false): string {
    if (!value) return '—';

    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) return value;

    const time =
        withTime && value.length >= 19
            ? ` ${value.slice(11, 19)}`
            : '';

    return `${match[3]}/${match[2]}/${match[1]}${time}`;
}

function reason(value: string): string {
    const labels: Record<string, string> = {
        purchase: 'Compra',
        initial_balance: 'Saldo inicial',
        sale: 'Venta',
        work_order: 'Orden de trabajo',
        production: 'Producción',
        internal_consumption: 'Consumo interno',
        waste: 'Merma',
        adjustment: 'Ajuste',
    };

    return labels[value] ?? value;
}

function status(value: string): string {
    const labels: Record<string, string> = {
        draft: 'Borrador',
        posted: 'Confirmada',
        cancelled: 'Cancelada',
    };

    return labels[value] ?? value;
}

function navigate(filters: Filters, targetPage = 1): void {
    const data: Record<string, string | number> = {
        page: targetPage,
    };

    for (const [key, value] of Object.entries(filters)) {
        if (
            key !== 'page' &&
            value !== null &&
            value !== undefined &&
            value !== ''
        ) {
            data[key] = value;
        }
    }

    router.get(listPath.value, data, {
        preserveScroll: true,
        preserveState: false,
    });
}

function search(): void {
    navigate(form);
}

function changePage(targetPage: number): void {
    if (props.mode === 'detail' && props.entry) {
        router.get(
            `/inventory/entries/${props.entry.header.id}`,
            { page: targetPage },
            { preserveScroll: true },
        );
        return;
    }

    if (props.filters) {
        navigate(props.filters, targetPage);
    }
}
</script>

<template>
    <Head :title="`${title} | Vidriería Maradiaga`" />

    <main class="history">
        <nav class="navigation" aria-label="Navegación de inventario">
            <Link href="/">Panel principal</Link>

            <Link
                v-if="allowed('inventory.products.view')"
                href="/inventory/products"
            >
                Productos
            </Link>

            <Link
                v-if="allowed('inventory.entries.view')"
                href="/inventory/entries"
            >
                Historial de entradas
            </Link>

            <Link
                v-if="allowed('inventory.kardex.view')"
                href="/inventory/kardex"
            >
                Kardex por producto
            </Link>

            <Link
                v-if="
                    allowed('inventory.entries.create') &&
                    allowed('inventory.entries.post')
                "
                href="/inventory/entries/create"
                class="primary"
            >
                Registrar entrada
            </Link>
        </nav>

        <h1>{{ title }}</h1>

        <p v-if="mode === 'entries'" class="hint">
            Consultá compras y saldos iniciales. Las fechas del filtro
            corresponden al documento.
        </p>

        <p v-if="mode === 'kardex'" class="hint">
            Movimientos confirmados en orden de confirmación.
            Las fechas del filtro corresponden a cuándo se afectó el
            inventario; la fecha del documento se muestra por separado.
        </p>

        <form
            v-if="mode !== 'detail' && options"
            class="panel"
            @submit.prevent="search"
        >
            <div class="filters">
                <label v-if="mode === 'entries'">
                    Referencia, código o producto
                    <input
                        v-model="form.search"
                        maxlength="100"
                        placeholder="Factura o código del producto"
                    />
                </label>

                <label>
                    {{ mode === 'kardex' ? 'Producto *' : 'Producto' }}

                    <select
                        v-model="form.product_id"
                        :required="mode === 'kardex'"
                    >
                        <option
                            :value="null"
                            :disabled="mode === 'kardex'"
                        >
                            {{
                                mode === 'kardex'
                                    ? 'Seleccioná un producto'
                                    : 'Todos'
                            }}
                        </option>

                        <option
                            v-for="product in options.products"
                            :key="product.id"
                            :value="product.id"
                        >
                            {{ product.name }}
                        </option>
                    </select>
                </label>

                <label>
                    Ubicación

                    <select v-model="form.location_id">
                        <option :value="null">
                            Todas las ubicaciones
                        </option>

                        <option
                            v-for="location in options.locations"
                            :key="location.id"
                            :value="location.id"
                        >
                            {{ location.name }}
                        </option>
                    </select>
                </label>

                <label>
                    {{
                        mode === 'kardex'
                            ? 'Desde la confirmación'
                            : 'Desde el documento'
                    }}
                    <input v-model="form.from" type="date" />
                </label>

                <label>
                    {{
                        mode === 'kardex'
                            ? 'Hasta la confirmación'
                            : 'Hasta el documento'
                    }}
                    <input
                        v-model="form.to"
                        type="date"
                        :min="form.from || undefined"
                    />
                </label>

                <template v-if="mode === 'entries'">
                    <label>
                        Proveedor

                        <select v-model="form.supplier_id">
                            <option :value="null">Todos</option>

                            <option
                                v-for="supplier in options.suppliers"
                                :key="supplier.id"
                                :value="supplier.id"
                            >
                                {{ supplier.name }}
                            </option>
                        </select>
                    </label>

                    <label>
                        Estado

                        <select v-model="form.status">
                            <option value="">Todos</option>
                            <option value="posted">Confirmada</option>
                            <option value="draft">Borrador</option>
                            <option value="cancelled">Cancelada</option>
                        </select>
                    </label>

                    <label>
                        Motivo

                        <select v-model="form.reason">
                            <option value="">Todos</option>
                            <option value="purchase">Compra</option>
                            <option value="initial_balance">
                                Saldo inicial
                            </option>
                        </select>
                    </label>
                </template>
            </div>

            <ul
                v-if="Object.keys(page.props.errors).length"
                class="errors"
                role="alert"
            >
                <li
                    v-for="(error, key) in page.props.errors"
                    :key="key"
                >
                    {{ error }}
                </li>
            </ul>

            <div class="actions">
                <Button
                    type="submit"
                    label="Consultar"
                    icon="pi pi-search"
                />

                <Link :href="listPath">Limpiar filtros</Link>
            </div>
        </form>

        <!-- Historial de entradas -->
        <section
            v-if="mode === 'entries' && entries"
            class="panel"
        >
            <h2>{{ entries.total }} entradas encontradas</h2>

            <p class="hint">
                El importe corresponde al documento completo, incluso
                al filtrar por un producto o ubicación.
            </p>

            <div
                class="table-wrap"
                tabindex="0"
                role="region"
                aria-label="Historial de entradas"
            >
                <table>
                    <thead>
                        <tr>
                            <th>Entrada</th>
                            <th>Fecha documento</th>
                            <th>Motivo / referencia</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th class="number">Importe</th>
                            <th>Registrada por</th>
                            <th>Acción</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in entries.data"
                            :key="item.id"
                        >
                            <td>
                                #{{ item.id }}
                                <small>{{ item.line_count }} líneas</small>
                            </td>

                            <td>{{ date(item.document_date) }}</td>

                            <td>
                                {{ reason(item.reason) }}
                                <small>
                                    {{ item.reference ?? 'Sin referencia' }}
                                </small>
                            </td>

                            <td>{{ item.supplier ?? 'No aplica' }}</td>

                            <td>
                                <Tag
                                    :value="status(item.status)"
                                    :severity="
                                        item.status === 'posted'
                                            ? 'success'
                                            : item.status === 'cancelled'
                                              ? 'danger'
                                              : 'warn'
                                    "
                                />
                            </td>

                            <td class="number">
                                {{ money(item.total_cost) }}
                            </td>

                            <td>{{ item.creator ?? '—' }}</td>

                            <td>
                                <Link
                                    :href="`/inventory/entries/${item.id}`"
                                >
                                    Ver detalle
                                </Link>
                            </td>
                        </tr>

                        <tr v-if="entries.data.length === 0">
                            <td colspan="8">
                                No hay entradas en esta página con los
                                filtros aplicados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Detalle de una entrada -->
        <template v-if="mode === 'detail' && entry">
            <Tag
                :value="status(entry.header.status)"
                :severity="
                    entry.header.status === 'posted'
                        ? 'success'
                        : entry.header.status === 'cancelled'
                          ? 'danger'
                          : 'warn'
                "
            />

            <section class="panel">
                <dl class="details">
                    <div>
                        <dt>Fecha del documento</dt>
                        <dd>{{ date(entry.header.document_date) }}</dd>
                    </div>

                    <div>
                        <dt>Motivo</dt>
                        <dd>{{ reason(entry.header.reason) }}</dd>
                    </div>

                    <div>
                        <dt>Referencia</dt>
                        <dd>
                            {{ entry.header.reference ?? 'Sin referencia' }}
                        </dd>
                    </div>

                    <div>
                        <dt>Proveedor</dt>
                        <dd>{{ entry.header.supplier ?? 'No aplica' }}</dd>
                    </div>

                    <div>
                        <dt>Registrada por</dt>
                        <dd>
                            {{ entry.header.creator }}
                            <small>
                                {{ date(entry.header.created_at, true) }}
                            </small>
                        </dd>
                    </div>

                    <div>
                        <dt>Confirmada por</dt>
                        <dd>
                            {{ entry.header.poster ?? '—' }}
                            <small>
                                {{ date(entry.header.posted_at, true) }}
                            </small>
                        </dd>
                    </div>

                    <div>
                        <dt>Importe total del documento</dt>
                        <dd>
                            <strong>{{ money(entry.total_cost) }}</strong>
                        </dd>
                    </div>
                </dl>

                <p v-if="entry.header.notes" class="notes">
                    {{ entry.header.notes }}
                </p>

                <p
                    v-if="entry.header.status === 'cancelled'"
                    class="warning notes"
                >
                    Cancelada por {{ entry.header.canceller }}.
                    {{ date(entry.header.cancelled_at, true) }}.
                    {{ entry.header.cancellation_reason }}
                </p>
            </section>

            <section class="panel">
                <h2>Materiales registrados</h2>

                <p class="hint">
                    Cantidad y factor son los guardados al registrar
                    la entrada. El saldo posterior pertenece a la
                    ubicación; el costo promedio posterior es global
                    por producto. Los nombres corresponden al catálogo
                    actual.
                </p>

                <div
                    class="table-wrap"
                    tabindex="0"
                    role="region"
                    aria-label="Detalle de materiales"
                >
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Ubicación</th>
                                <th class="number">Cantidad recibida</th>
                                <th class="number">Factor</th>
                                <th class="number">Cantidad base</th>
                                <th class="number">Costo recibido</th>
                                <th class="number">Costo base</th>
                                <th class="number">Importe</th>
                                <th class="number">
                                    Saldo ubicación posterior
                                </th>
                                <th class="number">
                                    Promedio global posterior
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="line in entry.lines.data"
                                :key="line.id"
                            >
                                <td>
                                    {{ line.sku }} — {{ line.product }}

                                    <small
                                        v-if="allowed('inventory.kardex.view')"
                                    >
                                        <Link
                                            :href="`/inventory/kardex?product_id=${line.product_id}`"
                                        >
                                            Ver Kardex
                                        </Link>
                                    </small>

                                    <small
                                        v-if="line.notes"
                                        class="notes"
                                    >
                                        {{ line.notes }}
                                    </small>
                                </td>

                                <td>
                                    {{ line.warehouse }} /
                                    {{ line.location }}
                                </td>

                                <td class="number">
                                    {{ quantity(line.quantity) }}
                                    {{ line.unit }}
                                </td>

                                <td class="number">
                                    {{ quantity(line.conversion_factor) }}
                                </td>

                                <td class="number">
                                    {{ quantity(line.base_quantity) }}
                                    {{ line.base_unit }}
                                </td>

                                <td class="number">
                                    {{ money(line.unit_cost) }}
                                </td>

                                <td class="number">
                                    {{ money(line.base_unit_cost) }}
                                </td>

                                <td class="number">
                                    {{ money(line.total_cost) }}
                                </td>

                                <td class="number">
                                    {{ quantity(line.balance_after) }}
                                </td>

                                <td class="number">
                                    {{ money(line.average_cost_after) }}
                                </td>
                            </tr>

                            <tr v-if="entry.lines.data.length === 0">
                                <td colspan="10">
                                    No hay líneas en esta página.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="hint">
                    Importes mostrados con dos decimales. Los cálculos
                    conservan la precisión registrada.
                </p>
            </section>
        </template>

        <!-- Kardex -->
        <template v-if="mode === 'kardex'">
            <p v-if="!ledger" class="panel">
                Seleccioná un producto para consultar su historia.
            </p>

            <template v-else>
                <h2>
                    {{ ledger.product.sku }} —
                    {{ ledger.product.name }}
                </h2>

                <p class="hint">
                    Unidad de control: {{ ledger.product.unit }}.
                    Alcance aplicado: {{ locationName }}.
                </p>

                <div class="stats">
                    <div class="stat">
                        Saldo anterior al período
                        <strong>
                            {{ quantity(ledger.summary.opening) }}
                        </strong>
                    </div>

                    <div class="stat">
                        Entradas del período
                        <strong>
                            {{ quantity(ledger.summary.incoming) }}
                        </strong>
                    </div>

                    <div class="stat">
                        Salidas del período
                        <strong>
                            {{ quantity(ledger.summary.outgoing) }}
                        </strong>
                    </div>

                    <div class="stat">
                        Saldo al cierre del período
                        <strong>
                            {{ quantity(ledger.summary.closing) }}
                        </strong>
                    </div>
                </div>

                <p class="hint">
                    Existencia actual del alcance:
                    {{ quantity(ledger.summary.current) }}
                    {{ ledger.product.unit }}.
                    Puede diferir del cierre si filtraste fechas anteriores.
                </p>

                <p
                    v-if="!/^[-+]?0(?:\.0+)?$/.test(ledger.summary.difference)"
                    class="warning"
                    role="status"
                >
                    La existencia actual difiere de los movimientos
                    confirmados por
                    {{ quantity(ledger.summary.difference) }}
                    {{ ledger.product.unit }}.
                    Actualizá la consulta; si persiste, revisá los
                    registros antes de ajustar inventario.
                </p>

                <section class="panel">
                    <p class="hint">
                        El saldo acumulado incluye movimientos anteriores
                        al filtro y a la página. El costo promedio mostrado
                        es global y corresponde al momento del movimiento.
                    </p>

                    <div
                        class="table-wrap"
                        tabindex="0"
                        role="region"
                        aria-label="Movimientos del Kardex"
                    >
                        <table>
                            <thead>
                                <tr>
                                    <th>Confirmación</th>
                                    <th>Documento</th>
                                    <th>Movimiento</th>
                                    <th>Ubicación</th>
                                    <th class="number">Entrada</th>
                                    <th class="number">Salida</th>
                                    <th class="number">Saldo acumulado</th>
                                    <th class="number">Importe movimiento</th>
                                    <th class="number">
                                        Promedio global posterior
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="row in ledger.rows.data"
                                    :key="row.id"
                                >
                                    <td>{{ date(row.posted_at, true) }}</td>

                                    <td>
                                        {{ date(row.document_date) }}
                                        <small>
                                            {{ row.reference ?? 'Sin referencia' }}
                                        </small>
                                    </td>

                                    <td>
                                        <Link
                                            v-if="
                                                row.direction === 'inbound' &&
                                                allowed('inventory.entries.view')
                                            "
                                            :href="`/inventory/entries/${row.movement_id}`"
                                        >
                                            #{{ row.movement_id }}
                                        </Link>

                                        <span v-else>
                                            #{{ row.movement_id }}
                                        </span>

                                        <small>{{ reason(row.reason) }}</small>
                                    </td>

                                    <td>
                                        {{ row.warehouse }} /
                                        {{ row.location }}
                                    </td>

                                    <td class="number">
                                        {{
                                            row.direction === 'inbound'
                                                ? quantity(row.base_quantity)
                                                : '—'
                                        }}
                                        <small v-if="row.direction === 'inbound'">
                                            {{ row.unit }}
                                        </small>
                                    </td>

                                    <td class="number">
                                        {{
                                            row.direction === 'outbound'
                                                ? quantity(row.base_quantity)
                                                : '—'
                                        }}
                                        <small v-if="row.direction === 'outbound'">
                                            {{ row.unit }}
                                        </small>
                                    </td>

                                    <td class="number">
                                        {{ quantity(row.running_balance) }}
                                    </td>

                                    <td class="number">
                                        {{ money(row.total_cost) }}
                                    </td>

                                    <td class="number">
                                        {{ money(row.average_cost_after) }}
                                    </td>
                                </tr>

                                <tr v-if="ledger.rows.data.length === 0">
                                    <td colspan="9">
                                        No hay movimientos en esta página
                                        del período. El saldo anterior
                                        se conserva.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </template>

        <nav
            v-if="pagination"
            class="pagination"
            aria-label="Paginación"
        >
            <Button
                label="Anterior"
                icon="pi pi-angle-left"
                severity="info"
                :disabled="pagination.current_page <= 1"
                @click="changePage(pagination.current_page - 1)"
            />

            <span>
                Página {{ pagination.current_page }}
                de {{ pagination.last_page }} ·
                {{ pagination.total }} registros
            </span>

            <Button
                label="Siguiente"
                icon="pi pi-angle-right"
                iconPos="right"
                severity="info"
                :disabled="pagination.current_page >= pagination.last_page"
                @click="changePage(pagination.current_page + 1)"
            />
        </nav>
    </main>
</template>

<style scoped>
.history {
    max-width: 1500px;
    margin: auto;
    padding: clamp(1rem, 3vw, 2.5rem);
    font-family: system-ui, sans-serif;
}

.navigation,
.actions,
.pagination {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.navigation {
    margin-bottom: 1.5rem;
}

.navigation a {
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 0.5rem;
    padding: 0.65rem 0.9rem;
    text-decoration: none;
}

.navigation .primary {
    background: var(--p-primary-color, #047857);
    color: var(--p-primary-contrast-color, #fff);
}

h1 {
    font-size: clamp(1.5rem, 3vw, 2rem);
    margin: 0 0 0.75rem;
}

h2 {
    font-size: 1.2rem;
}

.hint,
dt {
    color: var(--p-text-muted-color, #64748b);
    line-height: 1.6;
}

.panel {
    background: var(--p-content-background, #fff);
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 1rem;
    padding: 1.25rem;
    margin: 1.25rem 0;
}

.filters,
.details {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

label {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    font-weight: 600;
}

input,
select {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 0.75rem;
    border-radius: 0.5rem;
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    color: var(--p-text-color, #172338);
    background: var(--p-form-field-background, #fff);
    font: inherit;
}

input:focus-visible,
select:focus-visible,
a:focus-visible,
.table-wrap:focus-visible {
    outline: 2px solid var(--p-primary-color, #047857);
    outline-offset: 3px;
}

.actions {
    margin-top: 1rem;
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 0.85rem;
    border-bottom: 1px solid var(--p-content-border-color, #cbd5e1);
    text-align: left;
    vertical-align: top;
}

th {
    white-space: nowrap;
}

.number {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

a {
    color: var(--p-primary-color, #047857);
}

small {
    display: block;
    margin-top: 0.3rem;
}

.pagination {
    justify-content: space-between;
    margin-top: 1.25rem;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
}

.stat {
    border: 1px solid var(--p-content-border-color, #cbd5e1);
    border-radius: 0.75rem;
    padding: 1rem;
}

.stat strong {
    display: block;
    margin-top: 0.6rem;
    font-size: 1.4rem;
    overflow-wrap: anywhere;
    font-variant-numeric: tabular-nums;
}

dt {
    margin-bottom: 0.35rem;
}

dd {
    margin: 0;
    overflow-wrap: anywhere;
}

.notes {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.errors {
    color: var(--p-red-400, #dc2626);
}

.warning {
    border-left: 4px solid #d97706;
    padding: 1rem;
    line-height: 1.6;
}

@media (max-width: 900px) {
    .filters,
    .details,
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .filters,
    .details,
    .stats {
        grid-template-columns: 1fr;
    }

    .pagination {
        justify-content: center;
    }
}
</style>
