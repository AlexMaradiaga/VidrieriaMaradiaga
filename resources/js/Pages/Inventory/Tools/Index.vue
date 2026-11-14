<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import InventoryNav from '../../../Components/Common/InventoryNav.vue';

interface Option { id:number; name:string }
interface Employee extends Option { code:string }
interface Assignment { id:number; employee:string; employee_code:string; assigned_at:string; expected_return_at:string|null; condition_out:string; notes:string|null }
interface Tool { id:number; code:string; name:string; brand:string|null; model:string|null; serial_number:string|null; condition:string; status:string; location:string|null; active:boolean; assignment:Assignment|null }

const props=defineProps<{tools:Tool[];employees:Employee[];locations:Option[];today:string}>();
const page=usePage<{auth:{permissions:string[]}}>();
const canManage=computed(()=>page.props.auth.permissions.includes('inventory.tools.manage'));
const canAssign=computed(()=>page.props.auth.permissions.includes('inventory.tools.assign'));
const showCreate=ref(false);const showAssign=ref(false);const showReturn=ref(false);const selected=ref<Tool|null>(null);
const tool=useForm({code:'',name:'',brand:'',model:'',serial_number:'',condition:'good',location_id:'' as number|'',description:'',notes:''});
const assignment=useForm({employee_id:'' as number|'',assigned_at:props.today,expected_return_at:'',condition_out:'good',notes:''});
const returning=useForm({returned_at:props.today,condition_return:'good',notes:''});
const employeeOptions=computed(()=>props.employees.map(item=>({...item,label:`${item.code} — ${item.name}`})));

function create(){tool.post('/inventory/tools',{preserveScroll:true,onSuccess:()=>{showCreate.value=false;tool.reset()}})}
function openAssign(item:Tool){selected.value=item;assignment.reset();showAssign.value=true}
function assign(){if(!selected.value)return;assignment.post(`/inventory/tools/${selected.value.id}/assign`,{preserveScroll:true,onSuccess:()=>{showAssign.value=false;selected.value=null;assignment.reset()}})}
function openReturn(item:Tool){selected.value=item;returning.reset();showReturn.value=true}
function returnTool(){if(!selected.value)return;returning.post(`/inventory/tools/${selected.value.id}/return`,{preserveScroll:true,onSuccess:()=>{showReturn.value=false;selected.value=null;returning.reset()}})}
function severity(status:string){return status==='available'?'success':status==='assigned'?'info':status==='maintenance'?'warn':'danger'}
function statusLabel(status:string){return({available:'Disponible',assigned:'Asignada',maintenance:'Mantenimiento',damaged:'Dañada'} as Record<string,string>)[status]??status}
function conditionLabel(condition:string){return({good:'Buena',fair:'Regular',maintenance:'Mantenimiento',damaged:'Dañada'} as Record<string,string>)[condition]??condition}
</script>

<template>
<Head title="Herramientas asignadas | Vidriería Maradiaga"/>
<main class="page"><InventoryNav/>
<header><div><h1>Herramientas asignadas</h1><p>Controla herramientas de la empresa, su estado y el empleado responsable.</p></div><Button v-if="canManage" label="Nueva herramienta" icon="pi pi-plus" @click="showCreate=true"/></header>
<section class="summary"><article><span>Total</span><strong>{{tools.length}}</strong></article><article><span>Asignadas</span><strong>{{tools.filter(item=>item.status==='assigned').length}}</strong></article><article><span>Disponibles</span><strong>{{tools.filter(item=>item.status==='available').length}}</strong></article></section>
<section class="panel"><div class="table"><table><thead><tr><th>Código</th><th>Herramienta</th><th>Ubicación</th><th>Estado</th><th>Responsable</th><th>Acciones</th></tr></thead><tbody>
<tr v-for="item in tools" :key="item.id"><td>{{item.code}}</td><td><strong>{{item.name}}</strong><small>{{[item.brand,item.model,item.serial_number].filter(Boolean).join(' · ')}}</small></td><td>{{item.location??'Sin ubicación'}}</td><td><Tag :severity="severity(item.status)" :value="statusLabel(item.status)"/><small>{{conditionLabel(item.condition)}}</small></td><td><template v-if="item.assignment"><strong>{{item.assignment.employee}}</strong><small>Desde {{item.assignment.assigned_at}}<template v-if="item.assignment.expected_return_at"> · Devuelve {{item.assignment.expected_return_at}}</template></small></template><span v-else>Sin asignar</span></td><td><Button v-if="canAssign&&item.status==='available'" label="Asignar" icon="pi pi-user-plus" size="small" @click="openAssign(item)"/><Button v-if="canAssign&&item.status==='assigned'" label="Recibir" icon="pi pi-replay" severity="success" size="small" @click="openReturn(item)"/></td></tr>
<tr v-if="!tools.length"><td colspan="6">Todavía no hay herramientas registradas.</td></tr></tbody></table></div></section>

<Dialog v-model:visible="showCreate" modal header="Registrar herramienta" :style="{width:'52rem',maxWidth:'calc(100vw - 2rem)'}"><form class="grid" @submit.prevent="create"><label>Código *<input v-model.trim="tool.code" required maxlength="50"/></label><label>Nombre *<input v-model.trim="tool.name" required maxlength="180"/></label><label>Marca<input v-model.trim="tool.brand" maxlength="100"/></label><label>Modelo<input v-model.trim="tool.model" maxlength="100"/></label><label>Número de serie<input v-model.trim="tool.serial_number" maxlength="100"/></label><label>Condición *<select v-model="tool.condition"><option value="good">Buena</option><option value="fair">Regular</option><option value="maintenance">Mantenimiento</option><option value="damaged">Dañada</option></select></label><label class="wide">Ubicación<Select v-model="tool.location_id" :options="locations" option-label="name" option-value="id" filter show-clear placeholder="Selecciona una ubicación"/></label><label class="wide">Descripción<textarea v-model.trim="tool.description" maxlength="1000"/></label><label class="wide">Notas<textarea v-model.trim="tool.notes" maxlength="1000"/></label><ul v-if="Object.keys(tool.errors).length" class="errors"><li v-for="(message,key) in tool.errors" :key="key">{{message}}</li></ul><div class="actions wide"><Button type="button" label="Cancelar" severity="secondary" text @click="showCreate=false"/><Button type="submit" label="Guardar herramienta" icon="pi pi-save" :loading="tool.processing"/></div></form></Dialog>

<Dialog v-model:visible="showAssign" modal :header="`Asignar ${selected?.name??''}`" :style="{width:'40rem',maxWidth:'calc(100vw - 2rem)'}"><form class="grid" @submit.prevent="assign"><label class="wide">Empleado responsable *<Select v-model="assignment.employee_id" :options="employeeOptions" option-label="label" option-value="id" filter placeholder="Busca por nombre o código" required/></label><label>Fecha de asignación *<input v-model="assignment.assigned_at" type="date" :max="today" required/></label><label>Devolución prevista<input v-model="assignment.expected_return_at" type="date" :min="assignment.assigned_at"/></label><label>Condición de entrega *<select v-model="assignment.condition_out"><option value="good">Buena</option><option value="fair">Regular</option></select></label><label class="wide">Notas<textarea v-model.trim="assignment.notes" maxlength="1000"/></label><ul v-if="Object.keys(assignment.errors).length" class="errors wide"><li v-for="(message,key) in assignment.errors" :key="key">{{message}}</li></ul><div class="actions wide"><Button type="button" label="Cancelar" severity="secondary" text @click="showAssign=false"/><Button type="submit" label="Confirmar asignación" icon="pi pi-check" :loading="assignment.processing"/></div></form></Dialog>

<Dialog v-model:visible="showReturn" modal :header="`Recibir ${selected?.name??''}`" :style="{width:'40rem',maxWidth:'calc(100vw - 2rem)'}"><form class="grid" @submit.prevent="returnTool"><label>Fecha de devolución *<input v-model="returning.returned_at" type="date" :max="today" required/></label><label>Condición al recibir *<select v-model="returning.condition_return"><option value="good">Buena</option><option value="fair">Regular</option><option value="maintenance">Requiere mantenimiento</option><option value="damaged">Dañada</option></select></label><label class="wide">Notas<textarea v-model.trim="returning.notes" maxlength="1000"/></label><ul v-if="Object.keys(returning.errors).length" class="errors wide"><li v-for="(message,key) in returning.errors" :key="key">{{message}}</li></ul><div class="actions wide"><Button type="button" label="Cancelar" severity="secondary" text @click="showReturn=false"/><Button type="submit" label="Registrar devolución" icon="pi pi-check" :loading="returning.processing"/></div></form></Dialog>
</main></template>

<style scoped>
.page{max-width:1400px;margin:auto;padding:clamp(1rem,3vw,2.5rem);font-family:system-ui,sans-serif}header{display:flex;align-items:center;justify-content:space-between;gap:1rem}header p{color:var(--p-text-muted-color)}.summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin:1.5rem 0}.summary article,.panel{padding:1.25rem;border:1px solid var(--p-content-border-color);border-radius:1rem;background:var(--p-content-background)}.summary article{display:grid;gap:.35rem}.summary strong{font-size:1.7rem}.table{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:.8rem;border-bottom:1px solid var(--p-content-border-color);text-align:left;vertical-align:top}td small{display:block;margin-top:.35rem;color:var(--p-text-muted-color)}.grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.wide{grid-column:1/-1}label{display:grid;gap:.4rem;font-weight:650}input,select,textarea{width:100%;padding:.72rem;border:1px solid var(--p-content-border-color);border-radius:.5rem;background:var(--p-form-field-background);color:var(--p-text-color)}textarea{min-height:5rem}.actions{display:flex;justify-content:flex-end;gap:.75rem}.errors{color:var(--p-red-400)}@media(max-width:700px){header{align-items:flex-start;flex-direction:column}.summary,.grid{grid-template-columns:1fr}.wide{grid-column:auto}}
</style>
