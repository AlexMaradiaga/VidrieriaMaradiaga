<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useI18n } from 'vue-i18n';
import InventoryNav from '../../Components/Common/InventoryNav.vue';

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
    direction: string;
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
const { t } = useI18n();

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
        return t('inventory.history.movementTitle',{id:props.entry?.header.id ?? ''});
    }

    return props.mode === 'kardex'
        ? t('inventory.history.kardexTitle')
        : t('inventory.history.entriesTitle');
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
    )?.name ?? t('inventory.history.allLocations'),
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
        purchase: t('inventory.history.purchase'),
        initial_balance: t('inventory.history.initialBalance'),
        sale: t('inventory.history.sale'),
        work_order: t('inventory.history.workOrder'),
        production: t('inventory.history.production'),
        internal_consumption: t('inventory.history.consumption'),
        waste: t('inventory.history.waste'),
        adjustment: t('inventory.history.adjustment'),
        transfer: t('inventory.history.transfer'),
    };

    return labels[value] ?? value;
}

function status(value: string): string {
    const labels: Record<string, string> = {
        draft: t('inventory.history.draft'),
        posted: t('inventory.history.posted'),
        cancelled: t('inventory.history.cancelled'),
    };

    return labels[value] ?? value;
}

function direction(value: string): string {
    return t(value === 'inbound' ? 'inventory.history.inbound' : 'inventory.history.outbound');
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
    <Head :title="`${title} | ${t('common.appName')}`" />

    <main class="history">
        <InventoryNav />

        <h1>{{ title }}</h1>

        <p v-if="mode === 'entries'" class="hint">
            {{t('inventory.history.entriesHint')}}
        </p>

        <p v-if="mode === 'kardex'" class="hint">
            {{t('inventory.history.kardexHint')}}
        </p>

        <form
            v-if="mode !== 'detail' && options"
            class="panel"
            @submit.prevent="search"
        >
            <div class="filters">
                <label v-if="mode === 'entries'">
                    {{t('inventory.history.search')}}
                    <input
                        v-model="form.search"
                        maxlength="100"
                        :placeholder="t('inventory.history.searchPlaceholder')"
                    />
                </label>

                <label>
                    {{t('common.product')}}{{ mode === 'kardex' ? ' *' : '' }}

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
                                    ? t('inventory.history.chooseProduct')
                                    : t('common.all')
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
                    {{t('common.location')}}

                    <select v-model="form.location_id">
                        <option :value="null">
                            {{t('inventory.history.allLocations')}}
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
                            ? t('inventory.history.fromPosted')
                            : t('inventory.history.from')
                    }}
                    <input v-model="form.from" type="date" />
                </label>

                <label>
                    {{
                        mode === 'kardex'
                            ? t('inventory.history.toPosted')
                            : t('inventory.history.to')
                    }}
                    <input
                        v-model="form.to"
                        type="date"
                        :min="form.from || undefined"
                    />
                </label>

                <template v-if="mode === 'entries'">
                    <label>
                        {{t('inventory.history.supplier')}}

                        <select v-model="form.supplier_id">
                            <option :value="null">{{t('common.all')}}</option>

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
                        {{t('common.status')}}

                        <select v-model="form.status">
                            <option value="">{{t('common.all')}}</option>
                            <option value="posted">{{t('inventory.history.posted')}}</option>
                            <option value="draft">{{t('inventory.history.draft')}}</option>
                            <option value="cancelled">{{t('inventory.history.cancelled')}}</option>
                        </select>
                    </label>

                    <label>
                        {{t('inventory.history.reason')}}

                        <select v-model="form.reason">
                            <option value="">{{t('common.all')}}</option>
                            <option value="purchase">{{t('inventory.history.purchase')}}</option>
                            <option value="initial_balance">
                                {{t('inventory.history.initialBalance')}}
                            </option>
                            <option value="sale">{{t('inventory.history.sale')}}</option>
                            <option value="work_order">{{t('inventory.history.workOrder')}}</option>
                            <option value="production">{{t('inventory.history.production')}}</option>
                            <option value="internal_consumption">{{t('inventory.history.consumption')}}</option>
                            <option value="waste">{{t('inventory.history.waste')}}</option>
                            <option value="adjustment">{{t('inventory.history.adjustment')}}</option>
                            <option value="transfer">{{t('inventory.history.transfer')}}</option>
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
                    :label="t('inventory.history.consult')"
                    icon="pi pi-search"
                />

                <Link :href="listPath">{{t('inventory.history.clear')}}</Link>
            </div>
        </form>

        <!-- Historial de movimientos -->
        <section
            v-if="mode === 'entries' && entries"
            class="panel"
        >
            <h2>{{t('inventory.history.found',{total:entries.total})}}</h2>

            <p class="hint">
                {{t('inventory.history.filterHelp')}}
            </p>

            <div
                class="table-wrap"
                tabindex="0"
                role="region"
                :aria-label="t('inventory.history.entriesTitle')"
            >
                <table>
                    <thead>
                        <tr>
                            <th>{{t('inventory.history.movement')}}</th>
                            <th>{{t('inventory.history.documentDate')}}</th>
                            <th>{{t('inventory.history.reasonReference')}}</th>
                            <th>{{t('inventory.history.supplier')}}</th>
                            <th>{{t('common.status')}}</th>
                            <th class="number">{{t('inventory.history.amount')}}</th>
                            <th>{{t('inventory.history.registeredBy')}}</th>
                            <th>{{t('common.action')}}</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="item in entries.data"
                            :key="item.id"
                        >
                            <td>
                                #{{ item.id }}
                                <small>{{ direction(item.direction) }} · {{t('inventory.history.lines',{count:item.line_count})}}</small>
                            </td>

                            <td>{{ date(item.document_date) }}</td>

                            <td>
                                {{ reason(item.reason) }}
                                <small>
                                    {{ item.reference ?? t('inventory.history.noReference') }}
                                </small>
                            </td>

                            <td>{{ item.supplier ?? t('inventory.history.notApplicable') }}</td>

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
                                    {{t('inventory.history.viewDetail')}}
                                </Link>
                            </td>
                        </tr>

                        <tr v-if="entries.data.length === 0">
                            <td colspan="8">
                                {{t('inventory.history.emptyPage')}}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Detalle de un movimiento -->
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
                        <dt>{{t('inventory.history.documentDate')}}</dt>
                        <dd>{{ date(entry.header.document_date) }}</dd>
                    </div>

                    <div>
                        <dt>{{t('inventory.history.reason')}}</dt>
                        <dd>{{ reason(entry.header.reason) }}</dd>
                    </div>

                    <div>
                        <dt>{{t('common.reference')}}</dt>
                        <dd>
                            {{ entry.header.reference ?? t('inventory.history.noReference') }}
                        </dd>
                    </div>

                    <div>
                        <dt>{{t('inventory.history.supplier')}}</dt>
                        <dd>{{ entry.header.supplier ?? t('inventory.history.notApplicable') }}</dd>
                    </div>

                    <div>
                        <dt>{{t('inventory.history.registeredBy')}}</dt>
                        <dd>
                            {{ entry.header.creator }}
                            <small>
                                {{ date(entry.header.created_at, true) }}
                            </small>
                        </dd>
                    </div>

                    <div>
                        <dt>{{t('inventory.history.postedBy')}}</dt>
                        <dd>
                            {{ entry.header.poster ?? '—' }}
                            <small>
                                {{ date(entry.header.posted_at, true) }}
                            </small>
                        </dd>
                    </div>

                    <div>
                        <dt>{{t('inventory.history.totalDocument')}}</dt>
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
                    {{t('inventory.history.cancelledBy',{user:entry.header.canceller})}}
                    {{ date(entry.header.cancelled_at, true) }}.
                    {{ entry.header.cancellation_reason }}
                </p>
            </section>

            <section class="panel">
                <h2>{{t('inventory.history.materials')}}</h2>

                <p class="hint">
                    {{t('inventory.history.detailHelp')}}
                </p>

                <div
                    class="table-wrap"
                    tabindex="0"
                    role="region"
                    :aria-label="t('inventory.history.materials')"
                >
                    <table>
                        <thead>
                            <tr>
                                <th>{{t('common.product')}}</th>
                                <th>{{t('common.location')}}</th>
                                <th class="number">{{t('inventory.history.receivedQuantity')}}</th>
                                <th class="number">{{t('inventory.history.factor')}}</th>
                                <th class="number">{{t('inventory.history.baseQuantity')}}</th>
                                <th class="number">{{t('inventory.history.receivedCost')}}</th>
                                <th class="number">{{t('inventory.history.baseCost')}}</th>
                                <th class="number">{{t('inventory.history.amount')}}</th>
                                <th class="number">
                                    {{t('inventory.history.balanceAfter')}}
                                </th>
                                <th class="number">
                                    {{t('inventory.history.averageAfter')}}
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
                                            {{t('inventory.history.viewKardex')}}
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
                                    {{t('inventory.history.noLines')}}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="hint">
                    {{t('inventory.history.precision')}}
                </p>
            </section>
        </template>

        <!-- Kardex -->
        <template v-if="mode === 'kardex'">
            <p v-if="!ledger" class="panel">
                {{t('inventory.history.chooseForLedger')}}
            </p>

            <template v-else>
                <h2>
                    {{ ledger.product.sku }} —
                    {{ ledger.product.name }}
                </h2>

                <p class="hint">
                    {{t('inventory.history.ledgerScope',{unit:ledger.product.unit,location:locationName})}}
                </p>

                <div class="stats">
                    <div class="stat">
                        {{t('inventory.history.previousBalance')}}
                        <strong>
                            {{ quantity(ledger.summary.opening) }}
                        </strong>
                    </div>

                    <div class="stat">
                        {{t('inventory.history.inboundPeriod')}}
                        <strong>
                            {{ quantity(ledger.summary.incoming) }}
                        </strong>
                    </div>

                    <div class="stat">
                        {{t('inventory.history.outboundPeriod')}}
                        <strong>
                            {{ quantity(ledger.summary.outgoing) }}
                        </strong>
                    </div>

                    <div class="stat">
                        {{t('inventory.history.closingBalance')}}
                        <strong>
                            {{ quantity(ledger.summary.closing) }}
                        </strong>
                    </div>
                </div>

                <p class="hint">
                    {{t('inventory.history.currentScope',{quantity:quantity(ledger.summary.current),unit:ledger.product.unit})}}
                </p>

                <p
                    v-if="!/^[-+]?0(?:\.0+)?$/.test(ledger.summary.difference)"
                    class="warning"
                    role="status"
                >
                    {{t('inventory.history.differenceWarning',{quantity:quantity(ledger.summary.difference),unit:ledger.product.unit})}}
                </p>

                <section class="panel">
                    <p class="hint">
                        {{t('inventory.history.ledgerHelp')}}
                    </p>

                    <div
                        class="table-wrap"
                        tabindex="0"
                        role="region"
                        :aria-label="t('inventory.history.kardexTitle')"
                    >
                        <table>
                            <thead>
                                <tr>
                                    <th>{{t('inventory.history.confirmation')}}</th>
                                    <th>{{t('inventory.history.document')}}</th>
                                    <th>{{t('inventory.history.movement')}}</th>
                                    <th>{{t('common.location')}}</th>
                                    <th class="number">{{t('inventory.history.inbound')}}</th>
                                    <th class="number">{{t('inventory.history.outbound')}}</th>
                                    <th class="number">{{t('inventory.history.runningBalance')}}</th>
                                    <th class="number">{{t('inventory.history.movementAmount')}}</th>
                                    <th class="number">
                                        {{t('inventory.history.averageAfter')}}
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
                                            {{ row.reference ?? t('inventory.history.noReference') }}
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
                                        {{t('inventory.history.emptyLedger')}}
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
            :aria-label="t('inventory.history.pagination')"
        >
            <Button
                :label="t('common.previous')"
                icon="pi pi-angle-left"
                severity="info"
                :disabled="pagination.current_page <= 1"
                @click="changePage(pagination.current_page - 1)"
            />

            <span>
                {{t('common.page',{current:pagination.current_page,last:pagination.last_page})}} ·
                {{t('inventory.history.records',{count:pagination.total})}}
            </span>

            <Button
                :label="t('common.next')"
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
