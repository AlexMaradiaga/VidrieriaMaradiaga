<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { useI18n } from 'vue-i18n';
import InventoryNav from '../../../Components/Common/InventoryNav.vue';

interface Unit { id: number; name: string; symbol: string; factor: string }
interface Product { id: number; sku: string; name: string; base_unit_symbol: string; average_cost: string; units: Unit[] }
interface Option { id: number; name: string }
const props = defineProps<{ mode: 'exit' | 'transfer'; products: Product[]; locations: Option[]; today: string }>();
const toast = useToast();
const confirm = useConfirm();
const { t } = useI18n();
const saving = ref(false);
const resultId = ref<number | null>(null);
const errors = ref<Record<string, string[]>>({});
const pending = ref<Record<string, unknown> | null>(null);
const storageKey = computed(() => `vidrieria.inventory.pending-${props.mode}`);
const form = reactive({
    product_id: '' as number | '', location_id: '' as number | '', unit_id: '' as number | '',
    source_location_id: '' as number | '', destination_location_id: '' as number | '',
    reason: 'sale', document_date: props.today, reference: '', notes: '', quantity: '',
});
const product = computed(() => props.products.find((item) => item.id === form.product_id));
const title = computed(() => t(props.mode === 'exit' ? 'inventory.operations.exitTitle' : 'inventory.operations.transferTitle'));
const selectedUnit = computed(() => product.value?.units.find((unit) => unit.id === form.unit_id));
onMounted(() => {
    const saved = sessionStorage.getItem(storageKey.value);
    if (saved) {
        try {
            pending.value = JSON.parse(saved) as Record<string, unknown>;
            toast.add({ severity: 'warn', summary: t('inventory.operations.pendingTitle'), detail: t('inventory.operations.pendingDetail'), life: 7000 });
        } catch { sessionStorage.removeItem(storageKey.value); }
    }
});

function chooseProduct(): void {
    form.unit_id = product.value?.units[0]?.id ?? '';
}
function payload(): Record<string, unknown> {
    const common = {
        operation_key: crypto.randomUUID(), product_id: Number(form.product_id),
        document_date: form.document_date, reference: form.reference.trim() || null,
        notes: form.notes.trim() || null, quantity: form.quantity.trim(),
    };
    return props.mode === 'exit'
        ? { ...common, location_id: Number(form.location_id), unit_id: Number(form.unit_id), reason: form.reason }
        : { ...common, source_location_id: Number(form.source_location_id), destination_location_id: Number(form.destination_location_id) };
}
function submit(): void {
    if (saving.value || resultId.value !== null) return;
    if (pending.value !== null) {
        void save();
        return;
    }
    confirm.require({
        header: t(props.mode === 'exit' ? 'inventory.operations.confirmExit' : 'inventory.operations.confirmTransfer'),
        message: props.mode === 'exit'
            ? t('inventory.operations.exitQuestion', { quantity: form.quantity || '0', unit: selectedUnit.value?.symbol ?? '', product: product.value?.name ?? t('common.product').toLowerCase() })
            : t('inventory.operations.transferQuestion', { quantity: form.quantity || '0', unit: product.value?.base_unit_symbol ?? '' }),
        icon: 'pi pi-exclamation-triangle',
        rejectProps: { label: t('common.cancel'), severity: 'secondary', outlined: true },
        acceptProps: { label: t('inventory.operations.confirm'), severity: props.mode === 'exit' ? 'danger' : 'success' },
        accept: () => void save(),
    });
}
async function save(): Promise<void> {
    saving.value = true; errors.value = {};
    try {
        const body = pending.value ?? payload();
        pending.value = body;
        sessionStorage.setItem(storageKey.value, JSON.stringify(body));
        const response = await axios.post(`/inventory/${props.mode === 'exit' ? 'exits' : 'transfers'}`, body, { headers: { Accept: 'application/json' } });
        resultId.value = Number(response.data.data.movement_id ?? response.data.data.transfer_id);
        pending.value = null;
        sessionStorage.removeItem(storageKey.value);
        toast.add({ severity: 'success', summary: t('inventory.operations.success'), detail: response.data.message, life: 6000 });
    } catch (error: unknown) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            errors.value = (error.response.data as { errors?: Record<string, string[]> }).errors ?? {};
            pending.value = null;
            sessionStorage.removeItem(storageKey.value);
        }
        const detail = Object.values(errors.value).flat().join(' ') || t('common.operationFailed');
        toast.add({ severity: 'error', summary: t('inventory.operations.errorTitle'), detail, life: 8000 });
    } finally { saving.value = false; }
}
function reset(): void {
    resultId.value = null; errors.value = {}; pending.value = null; sessionStorage.removeItem(storageKey.value); form.reference = ''; form.notes = ''; form.quantity = '';
}
</script>

<template>
    <Head :title="`${title} | ${t('common.appName')}`" />
    <Toast /><ConfirmDialog />
    <main class="operation-page">
        <InventoryNav />
        <header><h1>{{ title }}</h1><p>{{t('inventory.operations.lead')}}</p></header>
        <section class="panel">
            <div class="grid">
                <label>{{t('common.product')}} *
                    <select v-model="form.product_id" :disabled="saving || pending !== null || resultId !== null" @change="chooseProduct">
                        <option value="">{{t('common.select')}}</option><option v-for="item in products" :key="item.id" :value="item.id">{{ item.sku }} — {{ item.name }}</option>
                    </select>
                </label>
                <label v-if="mode === 'exit'">{{t('common.location')}} *
                    <select v-model="form.location_id" :disabled="saving || pending !== null || resultId !== null"><option value="">{{t('common.select')}}</option><option v-for="item in locations" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                </label>
                <template v-else>
                    <label>{{t('inventory.operations.source')}} *
                        <select v-model="form.source_location_id" :disabled="saving || pending !== null || resultId !== null"><option value="">{{t('common.select')}}</option><option v-for="item in locations" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    </label>
                    <label>{{t('inventory.operations.destination')}} *
                        <select v-model="form.destination_location_id" :disabled="saving || pending !== null || resultId !== null"><option value="">{{t('common.select')}}</option><option v-for="item in locations" :key="item.id" :value="item.id">{{ item.name }}</option></select>
                    </label>
                </template>
                <label v-if="mode === 'exit'">{{t('common.unit')}} *
                    <select v-model="form.unit_id" :disabled="saving || pending !== null || resultId !== null"><option value="">{{t('common.select')}}</option><option v-for="item in product?.units ?? []" :key="item.id" :value="item.id">{{ item.name }} ({{ item.symbol }})</option></select>
                </label>
                <label v-if="mode === 'exit'">{{t('inventory.operations.reason')}} *
                    <select v-model="form.reason" :disabled="saving || pending !== null || resultId !== null"><option value="sale">{{t('inventory.operations.sale')}}</option><option value="work_order">{{t('inventory.operations.workOrder')}}</option><option value="production">{{t('inventory.operations.production')}}</option><option value="internal_consumption">{{t('inventory.operations.consumption')}}</option><option value="waste">{{t('inventory.operations.waste')}}</option><option value="adjustment">{{t('inventory.operations.adjustment')}}</option></select>
                </label>
                <label>{{t('common.quantity')}} {{ mode === 'transfer' ? `(${product?.base_unit_symbol ?? t('inventory.operations.baseUnit')})` : '' }} *
                    <input v-model.trim="form.quantity" inputmode="decimal" placeholder="0" :disabled="saving || pending !== null || resultId !== null" />
                </label>
                <label>{{t('common.date')}} *<input v-model="form.document_date" type="date" :max="today" :disabled="saving || pending !== null || resultId !== null" /></label>
                <label>{{t('common.reference')}}<input v-model.trim="form.reference" maxlength="100" :placeholder="t('inventory.operations.referencePlaceholder')" :disabled="saving || pending !== null || resultId !== null" /></label>
                <label class="wide">{{t('common.notes')}}<textarea v-model.trim="form.notes" maxlength="1000" :disabled="saving || pending !== null || resultId !== null" /></label>
            </div>
            <ul v-if="Object.keys(errors).length" class="errors" role="alert"><li v-for="(messages, field) in errors" :key="field">{{ messages.join(' ') }}</li></ul>
            <div class="buttons">
                <Button v-if="resultId === null" :label="t(mode === 'exit' ? 'inventory.operations.confirmExit' : 'inventory.operations.confirmTransfer')" icon="pi pi-check" :severity="mode === 'exit' ? 'danger' : 'success'" :loading="saving" @click="submit" />
                <Button v-else :label="t('inventory.operations.another')" icon="pi pi-plus" @click="reset" />
                <Link href="/">{{t('common.cancel')}}</Link>
            </div>
        </section>
    </main>
</template>

<style scoped>
.operation-page { max-width: 1050px; margin: auto; padding: clamp(1rem,3vw,2.5rem); font-family: system-ui,sans-serif; }
header p { color: var(--p-text-muted-color); }.panel { margin-top: 1.5rem; padding: 1.5rem; border: 1px solid var(--p-content-border-color); border-radius: 1rem; background: var(--p-content-background); }
.grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 1rem; }.wide { grid-column: 1/-1; } label { display: grid; gap: .4rem; font-weight: 650; }
input,select,textarea { width: 100%; padding: .75rem; border: 1px solid var(--p-content-border-color); border-radius: .5rem; background: var(--p-form-field-background); color: var(--p-text-color); } textarea { min-height: 6rem; resize: vertical; }
.buttons { display:flex;align-items:center;gap:1rem;margin-top:1.5rem;flex-wrap:wrap}.buttons a{color:var(--p-primary-color)}.errors{color:var(--p-red-400);line-height:1.6}
@media(max-width:700px){.grid{grid-template-columns:1fr}.wide{grid-column:auto}}
</style>
