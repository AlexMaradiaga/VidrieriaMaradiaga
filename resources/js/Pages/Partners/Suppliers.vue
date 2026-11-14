<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import InventoryNav from '../../Components/Common/InventoryNav.vue';

interface Supplier {
    id: number; code: string; legal_name: string; trade_name: string | null;
    tax_id: string | null; contact_name: string | null; email: string | null;
    phone: string | null; address: string | null; payment_terms_days: number; active: boolean;
}
defineProps<{ suppliers: Supplier[] }>();
const page = usePage<{ auth: { permissions: string[] } }>();
const canManage = computed(() => page.props.auth?.permissions?.includes('suppliers.manage') ?? false);
const toast = useToast();
const empty = () => ({ code: '', legal_name: '', trade_name: '', tax_id: '', contact_name: '', email: '', phone: '', address: '', payment_terms_days: 0 });
const form = useForm(empty());
const editForm = useForm(empty());
const editDialog = ref<HTMLDialogElement | null>(null);
const editingId = ref<number | null>(null);
const editingName = ref('');
const fields = [
    { key: 'code', label: 'Código', required: true, max: 30, type: 'text' },
    { key: 'legal_name', label: 'Razón social', required: true, max: 150, type: 'text' },
    { key: 'trade_name', label: 'Nombre comercial', required: false, max: 150, type: 'text' },
    { key: 'tax_id', label: 'RTN/identificación', required: false, max: 30, type: 'text' },
    { key: 'contact_name', label: 'Contacto', required: false, max: 120, type: 'text' },
    { key: 'email', label: 'Correo', required: false, max: 150, type: 'email' },
    { key: 'phone', label: 'Teléfono', required: false, max: 30, type: 'tel' },
] as const;
const submit = () => form.post('/suppliers', {
    preserveScroll: true,
    onSuccess: () => { form.reset(); toast.add({ severity: 'success', summary: 'Proveedor creado', life: 3500 }); },
});
const toggle = (supplier: Supplier) => router.patch(`/suppliers/${supplier.id}/active`, { active: !supplier.active }, { preserveScroll: true });
const openEdit = (supplier: Supplier) => {
    editForm.clearErrors();
    editForm.defaults({ code: supplier.code, legal_name: supplier.legal_name,
        trade_name: supplier.trade_name ?? '', tax_id: supplier.tax_id ?? '',
        contact_name: supplier.contact_name ?? '', email: supplier.email ?? '',
        phone: supplier.phone ?? '', address: supplier.address ?? '',
        payment_terms_days: Number(supplier.payment_terms_days) });
    editForm.reset();
    editingId.value = supplier.id;
    editingName.value = supplier.legal_name;
    editDialog.value?.showModal();
};
const closeEdit = () => {
    if (editForm.processing) return;
    editDialog.value?.close();
    editingId.value = null;
    editForm.clearErrors();
};
const saveEdit = () => {
    if (editingId.value === null || editForm.processing) return;
    editForm.put(`/suppliers/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editDialog.value?.close();
            editingId.value = null;
            toast.add({ severity: 'success', summary: 'Proveedor actualizado', detail: 'Los cambios se guardaron correctamente.', life: 3500 });
        },
        onError: async () => {
            await nextTick();
            editDialog.value?.querySelector<HTMLInputElement>('[aria-invalid="true"]')?.focus();
        },
    });
};
</script>

<template>
    <Head title="Proveedores" />
    <Toast position="top-right" />
    <main class="page">
        <InventoryNav />
        <h1>Proveedores</h1>
        <p>Datos comerciales reutilizados por compras e inventario.</p>
        <form v-if="canManage" class="card grid" @submit.prevent="submit">
            <label v-for="field in fields" :key="field.key">
                {{ field.label }}{{ field.required ? ' *' : '' }}
                <input v-model="form[field.key]" :type="field.type" :required="field.required" :maxlength="field.max">
                <small v-if="form.errors[field.key]" class="error">{{ form.errors[field.key] }}</small>
            </label>
            <label>Días de crédito<input v-model.number="form.payment_terms_days" type="number" min="0" max="3650" step="1" required><small class="error">{{ form.errors.payment_terms_days }}</small></label>
            <label class="wide">Dirección<textarea v-model="form.address" maxlength="500" /><small class="error">{{ form.errors.address }}</small></label>
            <button :disabled="form.processing">{{ form.processing ? 'Guardando…' : 'Guardar proveedor' }}</button>
        </form>
        <section class="card">
            <h2>Proveedores registrados</h2>
            <div class="table"><table>
                <thead><tr><th>Código</th><th>Nombre</th><th>Contacto</th><th>Crédito</th><th>Estado</th><th v-if="canManage">Acciones</th></tr></thead>
                <tbody>
                    <tr v-for="s in suppliers" :key="s.id">
                        <td>{{ s.code }}</td><td>{{ s.legal_name }}</td><td>{{ s.phone || s.email || '—' }}</td>
                        <td>{{ s.payment_terms_days }} días</td><td>{{ s.active ? 'Activo' : 'Inactivo' }}</td>
                        <td v-if="canManage"><div class="actions">
                            <button type="button" :aria-label="`Editar proveedor ${s.legal_name}`" @click="openEdit(s)">Editar</button>
                            <button type="button" class="danger" @click="toggle(s)">{{ s.active ? 'Desactivar' : 'Activar' }}</button>
                        </div></td>
                    </tr>
                    <tr v-if="!suppliers.length"><td :colspan="canManage ? 6 : 5">No hay proveedores registrados.</td></tr>
                </tbody>
            </table></div>
        </section>
    </main>
    <dialog ref="editDialog" class="supplier-dialog" aria-labelledby="supplier-edit-title" aria-describedby="supplier-edit-description" @cancel.prevent="closeEdit">
        <form @submit.prevent="saveEdit" :aria-busy="editForm.processing">
            <header class="modal-header">
                <div><h2 id="supplier-edit-title">Editar proveedor</h2><p id="supplier-edit-description">{{ editingName }}</p></div>
                <button type="button" class="outline" aria-label="Cerrar edición" :disabled="editForm.processing" @click="closeEdit">×</button>
            </header>
            <fieldset class="grid modal-body" :disabled="editForm.processing">
                <label v-for="field in fields" :key="field.key">
                    {{ field.label }}{{ field.required ? ' *' : '' }}
                    <input v-model="editForm[field.key]" :type="field.type" :required="field.required" :maxlength="field.max"
                        :autofocus="field.key === 'code'" :aria-invalid="!!editForm.errors[field.key]" :aria-describedby="editForm.errors[field.key] ? `edit-error-${field.key}` : undefined">
                    <small v-if="editForm.errors[field.key]" :id="`edit-error-${field.key}`" class="error">{{ editForm.errors[field.key] }}</small>
                </label>
                <label>Días de crédito<input v-model.number="editForm.payment_terms_days" type="number" min="0" max="3650" step="1" required :aria-invalid="!!editForm.errors.payment_terms_days" aria-describedby="edit-credit-error"><small id="edit-credit-error" class="error">{{ editForm.errors.payment_terms_days }}</small></label>
                <label class="wide">Dirección<textarea v-model="editForm.address" maxlength="500" :aria-invalid="!!editForm.errors.address" aria-describedby="edit-address-error" /><small id="edit-address-error" class="error">{{ editForm.errors.address }}</small></label>
            </fieldset>
            <footer class="modal-actions">
                <button type="button" class="outline" :disabled="editForm.processing" @click="closeEdit">Cancelar</button>
                <button type="submit" :disabled="editForm.processing">{{ editForm.processing ? 'Guardando…' : 'Guardar cambios' }}</button>
            </footer>
        </form>
    </dialog>
</template>

<style scoped>
.page{max-width:1400px;margin:auto;padding:2rem}.card{padding:1.5rem;border:1px solid var(--p-content-border-color);border-radius:1rem;margin:1rem 0;background:var(--p-content-background)}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.wide{grid-column:1/-1}label{display:grid;gap:.4rem;font-weight:600}input,textarea{width:100%;box-sizing:border-box;padding:.75rem;border:1px solid var(--p-content-border-color);border-radius:.5rem;background:transparent;color:inherit}input:focus,textarea:focus{outline:2px solid var(--p-primary-color)}textarea{min-height:5rem}button{width:max-content;padding:.7rem 1rem;border:0;border-radius:.5rem;background:var(--p-primary-color);color:var(--p-primary-contrast-color);font-weight:700;cursor:pointer}button:disabled{opacity:.6;cursor:wait}.danger{background:#ef4444;color:#fff}.outline{background:transparent;color:var(--p-text-color);border:1px solid var(--p-content-border-color)}.actions{display:flex;gap:.5rem;flex-wrap:wrap}.table{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:.8rem;border-bottom:1px solid var(--p-content-border-color)}.error{color:#dc2626;font-weight:400}.supplier-dialog{width:min(48rem,calc(100% - 2rem));max-height:calc(100dvh - 2rem);margin:auto;padding:0;border:1px solid var(--p-content-border-color);border-radius:1rem;background:var(--p-content-background);color:var(--p-text-color);overflow:auto;box-shadow:0 24px 70px #0006}.supplier-dialog::backdrop{background:#0f172a99;backdrop-filter:blur(3px)}.modal-header{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:1.25rem 1.5rem;border-bottom:1px solid var(--p-content-border-color)}.modal-header h2{margin:0}.modal-header p{margin:.4rem 0 0;color:var(--p-text-muted-color)}.modal-body{margin:0;padding:1.5rem;border:0;min-width:0}.modal-actions{display:flex;justify-content:flex-end;gap:.75rem;padding:1rem 1.5rem;border-top:1px solid var(--p-content-border-color)}@media(max-width:700px){.page{padding:1rem}.grid{grid-template-columns:1fr}.wide{grid-column:auto}.modal-body{padding:1rem}.modal-actions{flex-wrap:wrap}.modal-actions button{flex:1}}
</style>
