<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { useToast } from 'primevue/usetoast';
import { ref, watch } from 'vue';
import InventoryNav from '../../Components/Common/InventoryNav.vue';

interface User {
    id: number;
    name: string;
    email: string;
    active: boolean;
    last_login_at: string | null;
    roles: string[];
}

interface Role {
    id: number;
    name: string;
    permissions: string[];
}

interface Employee {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
    department: string | null;
    job_title: string | null;
    active: boolean;
    user_email: string | null;
}

interface PageProps extends Record<string, unknown> {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
}

defineProps<{
    users: User[];
    roles: Role[];
    permissions: string[];
    employees: Employee[];
}>();

const page = usePage<PageProps>();
const toast = useToast();

const user = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
});

const role = useForm({
    name: '',
    permissions: [] as string[],
});

const employee = useForm({
    employee_code: '',
    national_id: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    hire_date: '',
    department: '',
    job_title: '',
    monthly_salary: 0,
    user_id: '' as number | string,
});

const editUserForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
    active: true,
});

const selectedUser = ref<User | null>(null);
const editUserDialog = ref<HTMLDialogElement | null>(null);

watch(
    () => page.props.flash?.success,
    (message) => {
        if (message) {
            toast.add({
                severity: 'success',
                summary: 'Operación completada',
                detail: message,
                life: 5000,
            });
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => {
        if (message) {
            toast.add({
                severity: 'error',
                summary: 'No se pudo completar',
                detail: message,
                life: 7000,
            });
        }
    },
    { immediate: true },
);

const saveUser = () => user.post('/access/users', {
    preserveScroll: true,
    onSuccess: () => user.reset(),
});

const saveRole = () => role.post('/access/roles', {
    preserveScroll: true,
    onSuccess: () => role.reset(),
});

const saveEmployee = () => employee.post('/access/employees', {
    preserveScroll: true,
    onSuccess: () => employee.reset(),
});

function openEditUser(userToEdit: User): void {
    selectedUser.value = userToEdit;
    editUserForm.clearErrors();
    editUserForm.defaults({
        name: userToEdit.name,
        email: userToEdit.email,
        password: '',
        password_confirmation: '',
        roles: [...userToEdit.roles],
        active: userToEdit.active,
    });
    editUserForm.reset();
    editUserDialog.value?.showModal();
}

function closeEditUser(): void {
    if (editUserForm.processing) return;

    editUserDialog.value?.close();
    selectedUser.value = null;
    editUserForm.clearErrors();
    editUserForm.reset();
}

function updateUser(): void {
    if (!selectedUser.value) return;

    editUserForm.put(`/access/users/${selectedUser.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editUserDialog.value?.close();
            selectedUser.value = null;
            editUserForm.reset();
        },
        onError: (errors) => {
            const detail = Object.values(errors)[0] ?? 'Revisa los datos del usuario.';
            toast.add({
                severity: 'error',
                summary: 'No se pudo actualizar el usuario',
                detail,
                life: 7000,
            });
        },
    });
}

const toggleEmployee = (item: Employee) => router.patch(
    `/access/employees/${item.id}/active`,
    { active: !item.active },
    { preserveScroll: true },
);

const editRole = (item: Role) => {
    const selected = prompt('Permisos separados por coma', item.permissions.join(','));
    if (selected === null) return;

    router.put(
        `/access/roles/${item.id}`,
        { permissions: selected.split(',').map((permission) => permission.trim()).filter(Boolean) },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Usuarios, roles y empleados" />
    <Toast position="top-right" />

    <main class="page">
        <InventoryNav />
        <h1>Usuarios, roles y empleados</h1>

        <div class="columns">
            <section class="card">
                <h2>Nuevo usuario</h2>
                <form class="form" @submit.prevent="saveUser">
                    <label>Nombre<input v-model.trim="user.name" autocomplete="name" required /></label>
                    <label>Correo<input v-model.trim="user.email" type="email" autocomplete="email" required /></label>
                    <label>Contraseña<input v-model="user.password" type="password" autocomplete="new-password" minlength="8" required /></label>
                    <label>Confirmación<input v-model="user.password_confirmation" type="password" autocomplete="new-password" required /></label>
                    <fieldset>
                        <legend>Roles</legend>
                        <label v-for="item in roles" :key="item.id" class="check">
                            <input v-model="user.roles" type="checkbox" :value="item.name" />{{ item.name }}
                        </label>
                    </fieldset>
                    <button :disabled="user.processing">{{ user.processing ? 'Guardando…' : 'Crear usuario' }}</button>
                    <p v-if="Object.keys(user.errors).length" class="error">{{ Object.values(user.errors)[0] }}</p>
                </form>
            </section>

            <section class="card">
                <h2>Nuevo rol</h2>
                <form class="form" @submit.prevent="saveRole">
                    <label>Nombre<input v-model.trim="role.name" required /></label>
                    <fieldset class="permissions">
                        <legend>Permisos</legend>
                        <label v-for="permission in permissions" :key="permission" class="check">
                            <input v-model="role.permissions" type="checkbox" :value="permission" />{{ permission }}
                        </label>
                    </fieldset>
                    <button :disabled="role.processing">{{ role.processing ? 'Guardando…' : 'Crear rol' }}</button>
                    <p v-if="Object.keys(role.errors).length" class="error">{{ Object.values(role.errors)[0] }}</p>
                </form>
            </section>
        </div>

        <section class="card table-card">
            <h2>Usuarios</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Nombre</th><th>Correo</th><th>Roles</th><th>Estado</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in users" :key="item.id">
                            <td>{{ item.name }}</td>
                            <td>{{ item.email }}</td>
                            <td>{{ item.roles.join(', ') }}</td>
                            <td><span :class="['status', item.active ? 'active' : 'inactive']">{{ item.active ? 'Activo' : 'Inactivo' }}</span></td>
                            <td><button type="button" class="small-button" @click="openEditUser(item)"><i class="pi pi-pencil" /> Editar</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h2 class="roles-title">Roles</h2>
            <div class="table-scroll">
                <table>
                    <tbody>
                        <tr v-for="item in roles" :key="item.id">
                            <td>{{ item.name }}</td>
                            <td>{{ item.permissions.length }} permisos</td>
                            <td><button type="button" @click="editRole(item)">Editar permisos</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Nuevo empleado</h2>
            <form class="employee" @submit.prevent="saveEmployee">
                <label>Código *<input v-model.trim="employee.employee_code" required /></label>
                <label>Identidad<input v-model.trim="employee.national_id" /></label>
                <label>Nombres *<input v-model.trim="employee.first_name" required /></label>
                <label>Apellidos *<input v-model.trim="employee.last_name" required /></label>
                <label>Departamento<input v-model.trim="employee.department" /></label>
                <label>Cargo<input v-model.trim="employee.job_title" /></label>
                <label>Fecha de ingreso<input v-model="employee.hire_date" type="date" /></label>
                <label>Salario mensual<input v-model.number="employee.monthly_salary" type="number" min="0" step=".01" /></label>
                <label>Usuario vinculado
                    <select v-model="employee.user_id">
                        <option value="">Sin usuario</option>
                        <option v-for="item in users" :key="item.id" :value="item.id">{{ item.name }} — {{ item.email }}</option>
                    </select>
                </label>
                <label>Teléfono<input v-model.trim="employee.phone" /></label>
                <button :disabled="employee.processing">{{ employee.processing ? 'Guardando…' : 'Guardar empleado' }}</button>
            </form>
        </section>

        <section class="card table-card">
            <h2>Empleados</h2>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Código</th><th>Nombre</th><th>Área / cargo</th><th>Usuario</th><th>Estado</th><th>Acción</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in employees" :key="item.id">
                            <td>{{ item.employee_code }}</td>
                            <td>{{ item.first_name }} {{ item.last_name }}</td>
                            <td>{{ item.department || '—' }} / {{ item.job_title || '—' }}</td>
                            <td>{{ item.user_email || '—' }}</td>
                            <td>{{ item.active ? 'Activo' : 'Inactivo' }}</td>
                            <td><button class="danger" @click="toggleEmployee(item)">{{ item.active ? 'Desactivar' : 'Activar' }}</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <dialog ref="editUserDialog" class="user-dialog" @cancel.prevent="closeEditUser">
        <form class="modal-form" @submit.prevent="updateUser">
            <header class="modal-header">
                <div>
                    <p class="eyebrow">ADMINISTRACIÓN DE USUARIOS</p>
                    <h2>Editar usuario</h2>
                    <p>Cambia el nombre, el correo o asigna una contraseña nueva.</p>
                </div>
                <button type="button" class="icon-button" aria-label="Cerrar" :disabled="editUserForm.processing" @click="closeEditUser">
                    <i class="pi pi-times" />
                </button>
            </header>

            <div class="modal-body">
                <label>
                    Nombre *
                    <input v-model.trim="editUserForm.name" autocomplete="name" maxlength="255" required autofocus />
                    <small v-if="editUserForm.errors.name" class="field-error">{{ editUserForm.errors.name }}</small>
                </label>

                <label>
                    Correo *
                    <input v-model.trim="editUserForm.email" type="email" autocomplete="email" maxlength="255" required />
                    <small v-if="editUserForm.errors.email" class="field-error">{{ editUserForm.errors.email }}</small>
                </label>

                <div class="password-grid">
                    <label>
                        Nueva contraseña
                        <input v-model="editUserForm.password" type="password" autocomplete="new-password" minlength="8" placeholder="Déjala vacía para conservarla" />
                        <small v-if="editUserForm.errors.password" class="field-error">{{ editUserForm.errors.password }}</small>
                    </label>

                    <label>
                        Confirmar contraseña
                        <input v-model="editUserForm.password_confirmation" type="password" autocomplete="new-password" minlength="8" placeholder="Repite la contraseña nueva" />
                    </label>
                </div>

                <p class="password-help"><i class="pi pi-info-circle" /> La contraseña actual nunca se muestra. Escribe una nueva solo cuando quieras reemplazarla.</p>
            </div>

            <footer class="modal-actions">
                <button type="button" class="secondary" :disabled="editUserForm.processing" @click="closeEditUser">Cancelar</button>
                <button type="submit" :disabled="editUserForm.processing">
                    <i :class="editUserForm.processing ? 'pi pi-spin pi-spinner' : 'pi pi-save'" />
                    {{ editUserForm.processing ? 'Guardando…' : 'Guardar cambios' }}
                </button>
            </footer>
        </form>
    </dialog>
</template>

<style scoped>
.page{max-width:1500px;margin:auto;padding:2rem}.columns{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.card{padding:1.5rem;border:1px solid var(--p-content-border-color);border-radius:1rem;margin:1rem 0;background:var(--p-content-background)}.form,.employee{display:grid;gap:.8rem}.employee{grid-template-columns:repeat(2,minmax(0,1fr))}label{display:grid;gap:.35rem;font-weight:600}input,select{width:100%;padding:.75rem;border:1px solid var(--p-content-border-color);border-radius:.55rem;background:var(--p-form-field-background);color:var(--p-text-color)}input:focus,select:focus{outline:2px solid color-mix(in srgb,var(--p-primary-color) 35%,transparent);border-color:var(--p-primary-color)}.check{display:flex;align-items:center;gap:.4rem;font-weight:400}.check input{width:auto}.permissions{max-height:20rem;overflow:auto}.table-scroll{overflow:auto}table{width:100%;border-collapse:collapse}td,th{padding:.75rem;border-bottom:1px solid var(--p-content-border-color);text-align:left;white-space:nowrap}button{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;width:max-content;padding:.68rem .95rem;border:0;border-radius:.55rem;background:var(--p-primary-color);color:var(--p-primary-contrast-color);font-weight:700;cursor:pointer}button:disabled{cursor:not-allowed;opacity:.65}.small-button{padding:.5rem .75rem}.danger{background:#ef4444}.secondary{background:transparent;color:var(--p-text-color);border:1px solid var(--p-content-border-color)}.error,.field-error{color:#dc2626}.roles-title{margin-top:2rem}.status{display:inline-flex;padding:.25rem .6rem;border-radius:999px;font-size:.8rem;font-weight:700}.status.active{background:#dcfce7;color:#166534}.status.inactive{background:#e5e7eb;color:#4b5563}.user-dialog{width:min(42rem,calc(100% - 2rem));max-height:calc(100vh - 2rem);padding:0;border:1px solid var(--p-content-border-color);border-radius:1rem;background:var(--p-content-background);color:var(--p-text-color);box-shadow:0 24px 70px #0006}.user-dialog::backdrop{background:#0f172a99;backdrop-filter:blur(3px)}.modal-form{display:grid}.modal-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:1.4rem 1.5rem;border-bottom:1px solid var(--p-content-border-color)}.modal-header h2{margin:.2rem 0}.modal-header p{margin:0;color:var(--p-text-muted-color)}.eyebrow{color:var(--p-primary-color)!important;font-size:.72rem;font-weight:800;letter-spacing:.12em}.icon-button{width:2.4rem;height:2.4rem;padding:0;border-radius:50%;background:transparent;color:var(--p-text-color);border:1px solid var(--p-content-border-color)}.modal-body{display:grid;gap:1rem;padding:1.5rem;overflow:auto}.password-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.password-help{display:flex;align-items:flex-start;gap:.5rem;margin:0;padding:.8rem;border-radius:.6rem;background:var(--p-surface-100);color:var(--p-text-muted-color);font-size:.9rem}.modal-actions{display:flex;justify-content:flex-end;gap:.7rem;padding:1rem 1.5rem;border-top:1px solid var(--p-content-border-color)}@media(max-width:800px){.page{padding:1rem}.columns,.employee,.password-grid{grid-template-columns:1fr}.modal-actions{flex-direction:column-reverse}.modal-actions button{width:100%}}
</style>
