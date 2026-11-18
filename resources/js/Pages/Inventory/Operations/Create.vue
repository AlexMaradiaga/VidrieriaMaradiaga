<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import axios from 'axios';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import Select from 'primevue/select';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import InventoryNav from '../../../Components/Common/InventoryNav.vue';

interface Unit { id:number; name:string; symbol:string; factor:string }
interface Stock { location_id:number; quantity:string }
interface Product { id:number; sku:string; name:string; category_id:number; category_name:string; base_unit_symbol:string; stock_quantity:string; stocks:Stock[]; units:Unit[] }
interface Location { id:number; name:string; warehouse_id:number; warehouse_code:string; warehouse_name:string; warehouse_address:string|null }
interface Responsible { id:number; name:string; code:string }
interface Customer { id:number; code:string; name:string; address:string|null }
interface ExitLine { key:string; category_id:number|''; product_id:number|''; location_id:number|''; quantity:string }

const props=defineProps<{mode:'exit'|'transfer';products:Product[];locations:Location[];responsibles:Responsible[];customers:Customer[];today:string}>();
const toast=useToast();
const confirm=useConfirm();
const saving=ref(false);
const resultId=ref<number|null>(null);
const errors=ref<Record<string,string[]>>({});
const pending=ref<Record<string,unknown>|null>(null);
const storageKey=computed(()=>`vidrieria.inventory.pending-${props.mode}`);
const form=reactive({customer_id:'' as number|'',requested_by:'',customer_name:'',customer_address:'',reason:'work_order',document_date:props.today,reference:'',notes:'',product_id:'' as number|'',source_location_id:'' as number|'',destination_location_id:'' as number|'',responsible_name:'',quantity:''});
const lines=ref<ExitLine[]>([newLine()]);
const transferCategoryId=ref<number|''>('');

const categories=computed(()=>Array.from(new Map(props.products.map(item=>[item.category_id,item.category_name])),([id,name])=>({id,name})).sort((a,b)=>a.name.localeCompare(b.name,'es')));
const transferProduct=computed(()=>props.products.find(item=>item.id===form.product_id));
const transferProducts=computed(()=>productOptions(transferCategoryId.value));
const transferSourceLocations=computed(()=>locationsForProduct(form.product_id));
const transferDestinationLocations=computed(()=>props.locations.filter(item=>item.id!==form.source_location_id));
const title=computed(()=>props.mode==='exit'?'Registrar orden de salida':'Trasladar existencias');
const canSubmit=computed(()=>props.mode==='transfer'
    ? form.product_id!==''&&form.source_location_id!==''&&form.destination_location_id!==''&&form.responsible_name.trim()!==''&&form.quantity.trim()!==''
    : form.reference.trim()!==''&&form.requested_by.trim()!==''&&form.customer_name.trim()!==''&&lines.value.length>0&&lines.value.every(line=>line.product_id!==''&&line.location_id!==''&&line.quantity.trim()!==''));

onMounted(()=>{const saved=sessionStorage.getItem(storageKey.value);if(saved){try{pending.value=JSON.parse(saved) as Record<string,unknown>;toast.add({severity:'warn',summary:'Operación pendiente',detail:'Hay una operación pendiente. Puedes volver a enviarla sin duplicar el movimiento.',life:7000})}catch{sessionStorage.removeItem(storageKey.value)}}});
watch(()=>form.customer_id,(id)=>{const customer=props.customers.find(item=>item.id===id);if(customer){form.customer_name=customer.name;form.customer_address=customer.address??''}});
watch(()=>form.product_id,()=>{form.source_location_id='';form.destination_location_id=''});
watch(transferCategoryId,()=>{if(transferProduct.value&&transferCategoryId.value!==''&&transferProduct.value.category_id!==transferCategoryId.value)form.product_id=''});
watch(()=>form.source_location_id,()=>{if(form.destination_location_id===form.source_location_id)form.destination_location_id=''});

function newLine():ExitLine{return{key:crypto.randomUUID(),category_id:'',product_id:'',location_id:'',quantity:''}}
function productOptions(categoryId:number|''){return props.products.filter(item=>categoryId===''||item.category_id===categoryId).map(item=>({...item,label:`${item.sku} — ${item.name} · ${Number(item.stock_quantity)>0?`Disponible: ${item.stock_quantity} ${item.base_unit_symbol}`:'Sin existencia'}`}))}
function selectedProduct(productId:number|''){return props.products.find(item=>item.id===productId)}
function locationsForProduct(productId:number|''){const product=selectedProduct(productId);const ids=new Set((product?.stocks??[]).filter(stock=>Number(stock.quantity)>0).map(stock=>stock.location_id));return props.locations.filter(location=>ids.has(location.id))}
function lineCategoryChanged(line:ExitLine):void{const product=selectedProduct(line.product_id);if(product&&line.category_id!==''&&product.category_id!==line.category_id){line.product_id='';line.location_id=''}}
function lineProductChanged(line:ExitLine):void{line.location_id=''}
function addLine():void{lines.value.push(newLine())}
function removeLine(index:number):void{if(lines.value.length>1)lines.value.splice(index,1)}
function payload():Record<string,unknown>{const common={operation_key:crypto.randomUUID(),document_date:form.document_date,reference:form.reference.trim()||null,notes:form.notes.trim()||null};if(props.mode==='exit')return{...common,reason:form.reason,requested_by:form.requested_by.trim(),customer_id:form.customer_id===''?null:Number(form.customer_id),customer_name:form.customer_name.trim(),customer_address:form.customer_address.trim()||null,lines:lines.value.map(line=>({product_id:Number(line.product_id),location_id:Number(line.location_id),quantity:line.quantity.trim()}))};return{...common,product_id:Number(form.product_id),source_location_id:Number(form.source_location_id),destination_location_id:Number(form.destination_location_id),responsible_name:form.responsible_name.trim(),quantity:form.quantity.trim()}}
function submit():void{if(saving.value||resultId.value!==null)return;if(pending.value!==null){void save();return}const message=props.mode==='exit'?`Se registrará la orden ${form.reference} con ${lines.value.length} producto(s) para ${form.customer_name}. Esta acción afectará las existencias.`:`Se trasladarán ${form.quantity} ${transferProduct.value?.base_unit_symbol??''} de ${transferProduct.value?.name??'este producto'}.`;confirm.require({header:props.mode==='exit'?'Confirmar orden de salida':'Confirmar traslado',message,icon:'pi pi-exclamation-triangle',rejectProps:{label:'Cancelar',severity:'secondary',outlined:true},acceptProps:{label:'Confirmar',severity:props.mode==='exit'?'danger':'success'},accept:()=>void save()})}
async function save():Promise<void>{saving.value=true;errors.value={};try{const body=pending.value??payload();pending.value=body;sessionStorage.setItem(storageKey.value,JSON.stringify(body));const response=await axios.post(`/inventory/${props.mode==='exit'?'exits':'transfers'}`,body,{headers:{Accept:'application/json'}});resultId.value=Number(response.data.data.movement_id??response.data.data.transfer_id);pending.value=null;sessionStorage.removeItem(storageKey.value);toast.add({severity:'success',summary:'Operación registrada',detail:response.data.message,life:6000})}catch(error:unknown){if(axios.isAxiosError(error)&&error.response?.status===422){errors.value=(error.response.data as {errors?:Record<string,string[]>}).errors??{};pending.value=null;sessionStorage.removeItem(storageKey.value)}const detail=Object.values(errors.value).flat().join(' ')||'No fue posible registrar la operación.';toast.add({severity:'error',summary:'Revisa la información',detail,life:9000})}finally{saving.value=false}}
function reset():void{resultId.value=null;errors.value={};pending.value=null;sessionStorage.removeItem(storageKey.value);Object.assign(form,{customer_id:'',requested_by:'',customer_name:'',customer_address:'',reason:'work_order',document_date:props.today,reference:'',notes:'',product_id:'',source_location_id:'',destination_location_id:'',responsible_name:'',quantity:''});lines.value=[newLine()]}
</script>

<template>
<Head :title="`${title} | Vidriería Maradiaga ERP`"/><Toast/><ConfirmDialog/>
<main class="operation-page"><InventoryNav/>
<header><h1>{{title}}</h1><p v-if="mode==='exit'">Registra el encabezado de la orden y todos los productos entregados al cliente en un solo movimiento.</p><p v-else>Traslada existencias entre bodegas y ubicaciones activas.</p></header>

<section v-if="mode==='exit'" class="panel"><h2>Datos de la orden</h2><div class="grid">
<label>Número de orden *<input v-model.trim="form.reference" maxlength="100" placeholder="Ejemplo: 31" required/></label>
<label>Fecha *<input v-model="form.document_date" type="date" :max="today" required/></label>
<label>Solicitado por *<input v-model.trim="form.requested_by" list="exit-responsibles" maxlength="180" placeholder="Nombre de quien solicitó" required/><datalist id="exit-responsibles"><option v-for="item in responsibles" :key="item.id" :value="item.name">{{item.code}}</option></datalist></label>
<label>Cliente registrado (opcional)<select v-model="form.customer_id"><option value="">Escribir cliente manualmente</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{customer.code}} — {{customer.name}}</option></select></label>
<label>Cliente *<input v-model.trim="form.customer_name" maxlength="180" placeholder="Nombre del cliente" required/></label>
<label>Domicilio del cliente<input v-model.trim="form.customer_address" maxlength="500" placeholder="Ciudad, colonia o dirección"/></label>
<label>Motivo *<select v-model="form.reason"><option value="work_order">Orden de trabajo</option><option value="sale">Venta</option><option value="production">Producción</option><option value="internal_consumption">Consumo interno</option><option value="waste">Desperdicio</option><option value="adjustment">Ajuste</option></select></label>
<label class="wide">Notas<textarea v-model.trim="form.notes" maxlength="1000" placeholder="Observaciones adicionales de la orden"/></label>
</div></section>

<section v-if="mode==='exit'" class="panel"><div class="section-title"><div><h2>Productos de la orden</h2><p>La unidad se toma automáticamente del catálogo del producto.</p></div><Button label="Agregar producto" icon="pi pi-plus" outlined :disabled="saving||resultId!==null" @click="addLine"/></div>
<article v-for="(line,index) in lines" :key="line.key" class="line-card"><div class="line-heading"><strong>Producto {{index+1}}</strong><Button v-if="lines.length>1" icon="pi pi-trash" severity="danger" text rounded aria-label="Quitar producto" :disabled="saving||resultId!==null" @click="removeLine(index)"/></div><div class="line-grid">
<label>Categoría<select v-model="line.category_id" :disabled="saving||resultId!==null" @change="lineCategoryChanged(line)"><option value="">Todas las categorías</option><option v-for="category in categories" :key="category.id" :value="category.id">{{category.name}}</option></select></label>
<label>Producto *<Select v-model="line.product_id" :options="productOptions(line.category_id)" option-label="label" option-value="id" filter :filter-fields="['sku','name','category_name']" filter-placeholder="Escribe el código o nombre" placeholder="Selecciona o busca un producto" empty-message="No hay productos en esta categoría" :disabled="saving||resultId!==null" @change="lineProductChanged(line)"/></label>
<label>Ubicación con existencia *<select v-model="line.location_id" :disabled="line.product_id===''||saving||resultId!==null"><option value="">{{line.product_id===''?'Primero selecciona el producto':'Selecciona la ubicación'}}</option><option v-for="location in locationsForProduct(line.product_id)" :key="location.id" :value="location.id">{{location.name}}</option></select><small v-if="line.product_id!==''&&!locationsForProduct(line.product_id).length">Este producto no tiene existencia disponible.</small></label>
<label>Cantidad ({{selectedProduct(line.product_id)?.base_unit_symbol??'unidad base'}}) *<input v-model.trim="line.quantity" inputmode="decimal" placeholder="0" required/></label>
</div></article></section>

<section v-else class="panel"><div class="grid">
<label>Categoría<select v-model="transferCategoryId"><option value="">Todas las categorías</option><option v-for="category in categories" :key="category.id" :value="category.id">{{category.name}}</option></select></label>
<label class="wide">Producto *<Select v-model="form.product_id" :options="transferProducts" option-label="label" option-value="id" filter :filter-fields="['sku','name','category_name']" filter-placeholder="Escribe el código o nombre" placeholder="Selecciona o busca un producto"/></label>
<label>Ubicación de origen *<select v-model="form.source_location_id" :disabled="form.product_id==='' "><option value="">Selecciona el origen</option><option v-for="location in transferSourceLocations" :key="location.id" :value="location.id">{{location.name}}</option></select></label>
<label>Ubicación de destino *<select v-model="form.destination_location_id" :disabled="form.source_location_id==='' "><option value="">Selecciona el destino</option><option v-for="location in transferDestinationLocations" :key="location.id" :value="location.id">{{location.name}}</option></select></label>
<label>Responsable del traslado *<input v-model.trim="form.responsible_name" list="transfer-responsibles" maxlength="180" placeholder="Nombre del responsable"/><datalist id="transfer-responsibles"><option v-for="item in responsibles" :key="item.id" :value="item.name">{{item.code}}</option></datalist></label>
<label>Cantidad ({{transferProduct?.base_unit_symbol??'unidad base'}}) *<input v-model.trim="form.quantity" inputmode="decimal" placeholder="0"/></label>
<label>Fecha *<input v-model="form.document_date" type="date" :max="today"/></label><label>Referencia<input v-model.trim="form.reference" maxlength="100" placeholder="Factura, orden o documento"/></label><label class="wide">Notas<textarea v-model.trim="form.notes" maxlength="1000"/></label>
</div></section>

<ul v-if="Object.keys(errors).length" class="errors" role="alert"><li v-for="(messages,field) in errors" :key="field"><strong>{{field}}:</strong> {{messages.join(' ')}}</li></ul>
<div class="buttons"><Button v-if="resultId===null" :label="mode==='exit'?'Registrar orden y descontar existencias':'Confirmar traslado'" icon="pi pi-check" :severity="mode==='exit'?'danger':'success'" :loading="saving" :disabled="!canSubmit" @click="submit"/><Button v-else label="Registrar otra operación" icon="pi pi-plus" @click="reset"/><Link href="/">Cancelar</Link></div>
</main>
</template>

<style scoped>
.operation-page{max-width:1180px;margin:auto;padding:clamp(1rem,3vw,2.5rem);font-family:system-ui,sans-serif}header p,.section-title p{color:var(--p-text-muted-color)}.panel{margin-top:1rem;padding:1.5rem;border:1px solid var(--p-content-border-color);border-radius:1rem;background:var(--p-content-background)}h2{font-size:1.1rem;margin:0 0 1rem}.grid,.line-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.wide{grid-column:1/-1}label{display:grid;align-content:start;gap:.4rem;font-weight:650}label small{color:var(--p-orange-600,#c2410c);font-weight:400}input,select,textarea{width:100%;padding:.75rem;border:1px solid var(--p-content-border-color);border-radius:.5rem;background:var(--p-form-field-background);color:var(--p-text-color)}textarea{min-height:5rem}.section-title,.line-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem}.section-title h2{margin-bottom:.25rem}.section-title p{margin:0}.line-card{margin-top:1rem;padding:1rem;border:1px solid var(--p-content-border-color);border-radius:.75rem;background:var(--p-content-hover-background)}.line-heading{margin-bottom:.8rem}.buttons{display:flex;align-items:center;gap:1rem;margin-top:1.5rem;flex-wrap:wrap}.buttons a{color:var(--p-primary-color)}.errors{margin-top:1rem;padding:1rem 1rem 1rem 2rem;border-radius:.75rem;background:var(--p-red-50);color:var(--p-red-700);line-height:1.6}@media(max-width:760px){.grid,.line-grid{grid-template-columns:1fr}.wide{grid-column:auto}.section-title{align-items:flex-start;flex-direction:column}}
</style>
