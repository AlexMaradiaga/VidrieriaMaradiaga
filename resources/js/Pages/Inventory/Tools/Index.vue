<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import InventoryNav from '../../../Components/Common/InventoryNav.vue';

interface Option { id: number; name: string }
interface Employee extends Option { code: string }
interface Assignment { id: number; employee: string; employee_code: string; assigned_at: string; expected_return_at: string | null; condition_out: string; notes: string | null }
interface Tool { id: number; code: string; name: string; brand: string | null; model: string | null; serial_number: string | null; condition: string; status: string; location: string | null; active: boolean; assignment: Assignment | null }
interface HistoryRow { id: number; tool: string; employee: string; assigned_at: string; expected_return_at: string | null; returned_at: string | null; condition_out: string; condition_return: string | null; deteriorated: boolean; notes: string | null; assigned_by: string; returned_by: string | null }

const props = defineProps<{ tools: Tool[]; employees: Employee[]; locations: Option[]; history: HistoryRow[]; today: string }>();
const page = usePage<{ auth: { permissions: string[] }; flash?: { success?: string; warning?: string; error?: string } }>();
const toast = useToast();

const canManage = computed(() => page.props.auth.permissions.includes('inventory.tools.manage'));
const canAssign = computed(() => page.props.auth.permissions.includes('inventory.tools.assign'));
const employeeOptions = computed(() => props.employees.map((item) => ({ ...item, label: `${item.code} — ${item.name}` })));
const blockedTools = computed(() => props.tools.filter((item) => ['damaged', 'maintenance'].includes(item.status)));

const showCreate = ref(false);
const showAssign = ref(false);
const showReturn = ref(false);
const showStatus = ref(false);
const selected = ref<Tool | null>(null);

const tool = useForm({ code: '', name: '', brand: '', model: '', serial_number: '', condition: 'good', location_id: '' as number | '', description: '', notes: '' });
const assignment = useForm({ employee_id: '' as number | '', assigned_at: props.today, expected_return_at: '', condition_out: 'good', notes: '' });
const returning = useForm({ returned_at: props.today, condition_return: 'good', notes: '' });
const statusForm = useForm({ condition: 'good', notes: '' });

const conditionRank = (condition: string): number => ({ good: 0, fair: 1, maintenance: 2, damaged: 3 })[condition] ?? 0;
const returnDeteriorated = computed(() => {
    const conditionOut = selected.value?.assignment?.condition_out;
    return conditionOut ? conditionRank(returning.condition_return) > conditionRank(conditionOut) : false;
});

watch(() => page.props.flash?.success, (message) => {
    if (message) toast.add({ severity: 'success', summary: 'Herramientas', detail: message, life: 5000 });
}, { immediate: true });
watch(() => page.props.flash?.warning, (message) => {
    if (message) toast.add({ severity: 'warn', summary: 'Alerta de herramienta', detail: message, life: 10000 });
}, { immediate: true });
watch(() => page.props.flash?.error, (message) => {
    if (message) toast.add({ severity: 'error', summary: 'Herramientas', detail: message, life: 8000 });
}, { immediate: true });

function create(): void {
    tool.post('/inventory/tools', { preserveScroll: true, onSuccess: () => { showCreate.value = false; tool.reset(); } });
}
function openAssign(item: Tool): void {
    if (item.status !== 'available') return;
    selected.value = item;
    assignment.reset();
    assignment.condition_out = ['good', 'fair'].includes(item.condition) ? item.condition : 'good';
    showAssign.value = true;
}
function assign(): void {
    if (!selected.value) return;
    assignment.post(`/inventory/tools/${selected.value.id}/assign`, { preserveScroll: true, onSuccess: () => { showAssign.value = false; selected.value = null; assignment.reset(); } });
}
function openReturn(item: Tool): void {
    if (item.status !== 'assigned' || !item.assignment) return;
    selected.value = item;
    returning.reset();
    returning.condition_return = item.assignment.condition_out;
    showReturn.value = true;
}
function returnTool(): void {
    if (!selected.value) return;
    returning.post(`/inventory/tools/${selected.value.id}/return`, { preserveScroll: true, onSuccess: () => { showReturn.value = false; selected.value = null; returning.reset(); } });
}
function openStatus(item: Tool): void {
    if (item.status === 'assigned') return;
    selected.value = item;
    statusForm.reset();
    statusForm.condition = item.condition;
    showStatus.value = true;
}
function updateStatus(): void {
    if (!selected.value) return;
    statusForm.patch(`/inventory/tools/${selected.value.id}/status`, { preserveScroll: true, onSuccess: () => { showStatus.value = false; selected.value = null; statusForm.reset(); } });
}
function severity(status: string): 'success' | 'info' | 'warn' | 'danger' {
    if (status === 'available') return 'success';
    if (status === 'assigned') return 'info';
    if (status === 'maintenance') return 'warn';
    return 'danger';
}
function statusLabel(status: string): string {
    return ({ available: 'Disponible', assigned: 'Asignada', maintenance: 'Mantenimiento', damaged: 'Dañada' } as Record<string, string>)[status] ?? status;
}
function conditionLabel(condition: string | null): string {
    if (!condition) return 'Pendiente';
    return ({ good: 'Buena', fair: 'Regular', maintenance: 'Mantenimiento', damaged: 'Dañada' } as Record<string, string>)[condition] ?? condition;
}
</script>

<template>
    <Head title="Control de herramientas | Vidriería Maradiaga" />
    <Toast position="top-right" />
    <main class="page">
        <InventoryNav />
        <header>
            <div>
                <h1>Control y asignación de herramientas</h1>
                <p>Registra quién tiene cada herramienta, controla su devolución y bloquea las que regresen dañadas o requieran mantenimiento.</p>
            </div>
            <Button v-if="canManage" label="Nueva herramienta" icon="pi pi-plus" @click="showCreate = true" />
        </header>

        <section class="summary">
            <article><span>Total</span><strong>{{ tools.length }}</strong></article>
            <article><span>Asignadas</span><strong>{{ tools.filter((item) => item.status === 'assigned').length }}</strong></article>
            <article><span>Disponibles</span><strong>{{ tools.filter((item) => item.status === 'available').length }}</strong></article>
            <article :class="{ danger: blockedTools.length > 0 }"><span>Dañadas o en mantenimiento</span><strong>{{ blockedTools.length }}</strong></article>
        </section>

        <section v-if="blockedTools.length" class="damage-alert" role="alert">
            <i class="pi pi-exclamation-triangle" />
            <div>
                <strong>Herramientas bloqueadas</strong>
                <p>No pueden asignarse nuevamente hasta que un responsable actualice su condición a Buena o Regular.</p>
                <ul><li v-for="item in blockedTools" :key="item.id">{{ item.code }} — {{ item.name }}: {{ statusLabel(item.status) }}</li></ul>
            </div>
        </section>

        <section class="panel">
            <h2>Inventario de herramientas</h2>
            <div class="table"><table><thead><tr><th>Código</th><th>Herramienta</th><th>Ubicación</th><th>Estado</th><th>Asignada a</th><th>Acciones</th></tr></thead><tbody>
                <tr v-for="item in tools" :key="item.id">
                    <td>{{ item.code }}</td>
                    <td><strong>{{ item.name }}</strong><small>{{ [item.brand, item.model, item.serial_number].filter(Boolean).join(' · ') || 'Sin datos adicionales' }}</small></td>
                    <td>{{ item.location ?? 'Sin ubicación' }}</td>
                    <td><Tag :severity="severity(item.status)" :value="statusLabel(item.status)" /><small>Condición: {{ conditionLabel(item.condition) }}</small></td>
                    <td><template v-if="item.assignment"><strong>{{ item.assignment.employee }}</strong><small>Desde {{ item.assignment.assigned_at }}<template v-if="item.assignment.expected_return_at"> · Prevista {{ item.assignment.expected_return_at }}</template></small><small>Salió en condición {{ conditionLabel(item.assignment.condition_out) }}</small></template><span v-else>Sin asignar</span></td>
                    <td><div class="row-actions">
                        <Button v-if="canAssign && item.status === 'available'" label="Asignar" icon="pi pi-user-plus" size="small" @click="openAssign(item)" />
                        <Button v-if="canAssign && item.status === 'assigned'" label="Recibir" icon="pi pi-replay" severity="success" size="small" @click="openReturn(item)" />
                        <Button v-if="canManage && item.status !== 'assigned'" label="Actualizar estado" icon="pi pi-wrench" severity="secondary" size="small" outlined @click="openStatus(item)" />
                    </div></td>
                </tr>
                <tr v-if="!tools.length"><td colspan="6">Todavía no hay herramientas registradas.</td></tr>
            </tbody></table></div>
        </section>

        <section class="panel history-panel">
            <div><h2>Historial de asignaciones y devoluciones</h2><p>Compara la condición de salida con la condición de retorno y conserva quién realizó cada operación.</p></div>
            <div class="table"><table><thead><tr><th>Herramienta</th><th>Empleado</th><th>Asignación</th><th>Devolución</th><th>Condición</th><th>Registrado por</th></tr></thead><tbody>
                <tr v-for="row in history" :key="row.id">
                    <td>{{ row.tool }}</td><td>{{ row.employee }}</td>
                    <td>{{ row.assigned_at }}<small v-if="row.expected_return_at">Prevista: {{ row.expected_return_at }}</small></td>
                    <td>{{ row.returned_at ?? 'Pendiente' }}</td>
                    <td><span>{{ conditionLabel(row.condition_out) }} → {{ conditionLabel(row.condition_return) }}</span><Tag v-if="row.deteriorated" value="Deterioro detectado" severity="danger" /><small v-if="row.notes">{{ row.notes }}</small></td>
                    <td>Entregó: {{ row.assigned_by }}<small v-if="row.returned_by">Recibió: {{ row.returned_by }}</small></td>
                </tr>
                <tr v-if="!history.length"><td colspan="6">No hay asignaciones registradas.</td></tr>
            </tbody></table></div>
        </section>

        <Dialog v-model:visible="showCreate" modal header="Registrar herramienta" :style="{ width: '52rem', maxWidth: 'calc(100vw - 2rem)' }">
            <form class="grid" @submit.prevent="create">
                <label>Código *<input v-model.trim="tool.code" required maxlength="50" /></label><label>Nombre *<input v-model.trim="tool.name" required maxlength="180" /></label>
                <label>Marca<input v-model.trim="tool.brand" maxlength="100" /></label><label>Modelo<input v-model.trim="tool.model" maxlength="100" /></label>
                <label>Número de serie<input v-model.trim="tool.serial_number" maxlength="100" /></label><label>Condición *<select v-model="tool.condition"><option value="good">Buena</option><option value="fair">Regular</option><option value="maintenance">Mantenimiento</option><option value="damaged">Dañada</option></select></label>
                <label class="wide">Ubicación<Select v-model="tool.location_id" :options="locations" option-label="name" option-value="id" filter show-clear placeholder="Selecciona una ubicación" /></label>
                <label class="wide">Descripción<textarea v-model.trim="tool.description" maxlength="1000" /></label><label class="wide">Notas<textarea v-model.trim="tool.notes" maxlength="1000" /></label>
                <ul v-if="Object.keys(tool.errors).length" class="errors wide"><li v-for="(message, key) in tool.errors" :key="key">{{ message }}</li></ul>
                <div class="actions wide"><Button type="button" label="Cancelar" severity="secondary" text @click="showCreate = false" /><Button type="submit" label="Guardar herramienta" icon="pi pi-save" :loading="tool.processing" /></div>
            </form>
        </Dialog>

        <Dialog v-model:visible="showAssign" modal :header="`Asignar ${selected?.name ?? ''}`" :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }">
            <form class="grid" @submit.prevent="assign">
                <label class="wide">Empleado responsable *<Select v-model="assignment.employee_id" :options="employeeOptions" option-label="label" option-value="id" filter placeholder="Busca por nombre o código" required /></label>
                <label>Fecha de asignación *<input v-model="assignment.assigned_at" type="date" :max="today" required /></label><label>Devolución prevista<input v-model="assignment.expected_return_at" type="date" :min="assignment.assigned_at" /></label>
                <label>Condición de entrega *<select v-model="assignment.condition_out"><option value="good">Buena</option><option value="fair">Regular</option></select></label><label class="wide">Notas<textarea v-model.trim="assignment.notes" maxlength="1000" /></label>
                <ul v-if="Object.keys(assignment.errors).length" class="errors wide"><li v-for="(message, key) in assignment.errors" :key="key">{{ message }}</li></ul>
                <div class="actions wide"><Button type="button" label="Cancelar" severity="secondary" text @click="showAssign = false" /><Button type="submit" label="Confirmar asignación" icon="pi pi-check" :loading="assignment.processing" /></div>
            </form>
        </Dialog>

        <Dialog v-model:visible="showReturn" modal :header="`Recibir ${selected?.name ?? ''}`" :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }">
            <form class="grid" @submit.prevent="returnTool">
                <p class="wide condition-out">La herramienta salió en condición <strong>{{ conditionLabel(selected?.assignment?.condition_out ?? null) }}</strong>.</p>
                <label>Fecha de devolución *<input v-model="returning.returned_at" type="date" :max="today" required /></label><label>Condición al recibir *<select v-model="returning.condition_return"><option value="good">Buena</option><option value="fair">Regular</option><option value="maintenance">Requiere mantenimiento</option><option value="damaged">Dañada</option></select></label>
                <div v-if="returnDeteriorated" class="return-warning wide" role="alert"><i class="pi pi-exclamation-triangle" />Se detectó deterioro respecto a la condición de salida. La herramienta quedará bloqueada si requiere mantenimiento o está dañada.</div>
                <label class="wide">Observación de devolución<textarea v-model.trim="returning.notes" maxlength="1000" :required="returnDeteriorated" placeholder="Describe la avería, daño o diferencia encontrada" /></label>
                <ul v-if="Object.keys(returning.errors).length" class="errors wide"><li v-for="(message, key) in returning.errors" :key="key">{{ message }}</li></ul>
                <div class="actions wide"><Button type="button" label="Cancelar" severity="secondary" text @click="showReturn = false" /><Button type="submit" label="Registrar devolución" icon="pi pi-check" :severity="returnDeteriorated ? 'danger' : 'success'" :loading="returning.processing" /></div>
            </form>
        </Dialog>

        <Dialog v-model:visible="showStatus" modal :header="`Actualizar estado de ${selected?.name ?? ''}`" :style="{ width: '40rem', maxWidth: 'calc(100vw - 2rem)' }">
            <form class="grid" @submit.prevent="updateStatus">
                <label class="wide">Condición actual *<select v-model="statusForm.condition"><option value="good">Buena — disponible</option><option value="fair">Regular — disponible</option><option value="maintenance">En mantenimiento — bloqueada</option><option value="damaged">Dañada — bloqueada</option></select></label>
                <label class="wide">Observación<textarea v-model.trim="statusForm.notes" maxlength="1000" placeholder="Ejemplo: reparada y probada por mantenimiento" /></label>
                <ul v-if="Object.keys(statusForm.errors).length" class="errors wide"><li v-for="(message, key) in statusForm.errors" :key="key">{{ message }}</li></ul>
                <div class="actions wide"><Button type="button" label="Cancelar" severity="secondary" text @click="showStatus = false" /><Button type="submit" label="Guardar estado" icon="pi pi-save" :loading="statusForm.processing" /></div>
            </form>
        </Dialog>
    </main>
</template>

<style scoped>
.page{max-width:1500px;margin:auto;padding:clamp(1rem,3vw,2.5rem);font-family:system-ui,sans-serif}header,.row-actions,.actions{display:flex;align-items:center;gap:.75rem}header{justify-content:space-between}header p,.history-panel>div>p{color:var(--p-text-muted-color)}.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;margin:1.5rem 0}.summary article,.panel{padding:1.25rem;border:1px solid var(--p-content-border-color);border-radius:1rem;background:var(--p-content-background)}.summary article{display:grid;gap:.35rem}.summary article.danger{border-color:#ef4444;background:color-mix(in srgb,#ef4444 8%,var(--p-content-background))}.summary strong{font-size:1.7rem}.damage-alert,.return-warning{display:flex;align-items:flex-start;gap:.8rem;padding:1rem;border:1px solid #ef4444;border-left-width:5px;border-radius:.75rem;background:color-mix(in srgb,#ef4444 8%,var(--p-content-background));color:var(--p-text-color)}.damage-alert{margin-bottom:1.25rem}.damage-alert i,.return-warning i{color:#dc2626;font-size:1.2rem}.damage-alert p{margin:.25rem 0}.panel+.panel{margin-top:1.25rem}.table{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:.8rem;border-bottom:1px solid var(--p-content-border-color);text-align:left;vertical-align:top}td small{display:block;margin-top:.35rem;color:var(--p-text-muted-color);white-space:pre-line}td .p-tag{margin-top:.35rem}.row-actions{align-items:flex-start;flex-wrap:wrap}.grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.wide{grid-column:1/-1}label{display:grid;gap:.4rem;font-weight:650}input,select,textarea{width:100%;padding:.72rem;border:1px solid var(--p-content-border-color);border-radius:.5rem;background:var(--p-form-field-background);color:var(--p-text-color)}textarea{min-height:5rem}.actions{justify-content:flex-end}.errors{color:var(--p-red-400,#dc2626)}.condition-out{margin:0;padding:.8rem;border-radius:.5rem;background:var(--p-content-hover-background)}@media(max-width:950px){.summary{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:700px){header{align-items:flex-start;flex-direction:column}.summary,.grid{grid-template-columns:1fr}.wide{grid-column:auto}}
</style>
