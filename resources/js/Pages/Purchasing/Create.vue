<script setup lang="ts">
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import InventoryNav from "../../Components/Common/InventoryNav.vue";

interface Supplier {
    id: number;
    code: string;
    display_name: string;
    payment_terms_days: number;
}
interface Location {
    id: number;
    name: string;
    warehouse: string;
}
interface Product {
    id: number;
    sku: string;
    name: string;
    average_cost: string;
    unit_id: number;
    unit: string;
}
interface Treasury {
    id: number;
    name: string;
    type: string;
}
interface Line {
    product_id: number | string;
    unit_id: number | string;
    quantity: number;
    unit_cost: number;
    discount: number;
    tax_rate: number;
}

const props = defineProps<{
    suppliers: Supplier[];
    locations: Location[];
    products: Product[];
    treasuryAccounts: Treasury[];
}>();
const today = new Date().toISOString().slice(0, 10);
const emptyLine = (): Line => ({
    product_id: "",
    unit_id: "",
    quantity: 1,
    unit_cost: 0,
    discount: 0,
    tax_rate: 15,
});
const form = useForm({
    operation_key: crypto.randomUUID(),
    supplier_id: "",
    location_id: "",
    supplier_document: "",
    document_date: today,
    due_date: "",
    payment_type: "credit",
    treasury_account_id: "",
    payment_method: "bank_transfer",
    paid_amount: 0,
    notes: "",
    lines: [emptyLine()] as Line[],
});
const productChanged = (line: Line) => {
    const product = props.products.find(
        (item) => item.id === Number(line.product_id),
    );
    if (product) {
        line.unit_id = product.unit_id;
        line.unit_cost = Number(product.average_cost);
    }
};
const supplierChanged = () => {
    const supplier = props.suppliers.find(
        (item) => item.id === Number(form.supplier_id),
    );
    if (supplier && supplier.payment_terms_days) {
        const date = new Date(`${form.document_date}T12:00:00`);
        date.setDate(date.getDate() + supplier.payment_terms_days);
        form.due_date = date.toISOString().slice(0, 10);
    }
};
const totals = computed(() =>
    form.lines.reduce(
        (total, line) => {
            const gross = line.quantity * line.unit_cost;
            const net = Math.max(0, gross - line.discount);
            const tax = (net * line.tax_rate) / 100;
            return {
                subtotal: total.subtotal + gross,
                discount: total.discount + line.discount,
                tax: total.tax + tax,
                total: total.total + net + tax,
            };
        },
        { subtotal: 0, discount: 0, tax: 0, total: 0 },
    ),
);
const add = () => form.lines.push(emptyLine());
const remove = (index: number) => {
    if (form.lines.length > 1) form.lines.splice(index, 1);
};
const sync = () => {
    form.paid_amount =
        form.payment_type === "cash"
            ? Number(totals.value.total.toFixed(2))
            : 0;
};
const submit = () => {
    sync();
    form.post("/purchases");
};
</script>

<template>
    <Head title="Nueva compra" />
    <main class="page">
        <InventoryNav />
        <div class="heading">
            <div>
                <p class="eyebrow">COMPRAS</p>
                <h1>Registrar compra de materiales</h1>
                <p>
                    La confirmación aumenta inventario, actualiza el último
                    costo de compra, recalcula el costo promedio y contabiliza
                    la factura.
                </p>
            </div>
            <Link href="/purchases">Volver</Link>
        </div>
        <form @submit.prevent="submit">
            <section class="card grid">
                <label
                    >Proveedor *<select
                        v-model="form.supplier_id"
                        required
                        @change="supplierChanged"
                    >
                        <option value="">Selecciona</option>
                        <option
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                             {{ supplier.code }} — {{ supplier.display_name }}
                        </option>
                    </select></label
                >
                <label
                    >Ubicación de ingreso *<select
                        v-model="form.location_id"
                        required
                    >
                        <option value="">Selecciona</option>
                        <option
                            v-for="location in locations"
                            :key="location.id"
                            :value="location.id"
                        >
                            {{ location.warehouse }} / {{ location.name }}
                        </option>
                    </select></label
                >
                <label
                    >Factura del proveedor<input
                        v-model="form.supplier_document"
                        maxlength="100"
                /></label>
                <label
                    >Fecha *<input
                        v-model="form.document_date"
                        type="date"
                        required
                /></label>
                <label
                    >Vencimiento<input v-model="form.due_date" type="date"
                /></label>
                <label
                    >Condición<select
                        v-model="form.payment_type"
                        @change="sync"
                    >
                        <option value="credit">Crédito</option>
                        <option value="cash">Contado</option>
                    </select></label
                >
                <label v-if="form.payment_type === 'cash'"
                    >Caja o banco *<select
                        v-model="form.treasury_account_id"
                        required
                    >
                        <option value="">Selecciona</option>
                        <option
                            v-for="account in treasuryAccounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select></label
                >
                <label class="wide"
                    >Notas<textarea v-model="form.notes" />
                </label>
            </section>
            <section class="card">
                <h2>Materiales</h2>
                <div class="line-head">
                    <span>Producto</span><span>Cantidad</span
                    ><span>Costo según factura</span><span>Descuento</span
                    ><span>ISV %</span><span></span>
                </div>
                <div
                    v-for="(line, index) in form.lines"
                    :key="index"
                    class="line"
                >
                    <select
                        v-model="line.product_id"
                        required
                        @change="productChanged(line)"
                    >
                        <option value="">Producto</option>
                        <option
                            v-for="product in products"
                            :key="product.id"
                            :value="product.id"
                        >
                            {{ product.sku }} — {{ product.name }} ({{
                                product.unit
                            }})
                        </option></select
                    ><input
                        v-model.number="line.quantity"
                        type="number"
                        min=".0001"
                        step=".0001"
                        placeholder="Cantidad"
                        required
                    /><input
                        v-model.number="line.unit_cost"
                        type="number"
                        min="0"
                        step=".0001"
                        placeholder="Costo por unidad recibida"
                        required
                    /><input
                        v-model.number="line.discount"
                        type="number"
                        min="0"
                        step=".01"
                        placeholder="Descuento"
                    /><input
                        v-model.number="line.tax_rate"
                        type="number"
                        min="0"
                        max="100"
                        step=".01"
                        placeholder="ISV %"
                    /><button
                        type="button"
                        class="danger"
                        aria-label="Quitar producto"
                        @click="remove(index)"
                    >
                        ×
                    </button>
                </div>
                <p class="help">
                    El costo ingresado se guarda como último costo de compra y
                    actualiza el costo promedio. El precio de venta se
                    administra por separado.
                </p>
                <button type="button" class="secondary" @click="add">
                    + Agregar producto
                </button>
                <div class="totals">
                    <span>Subtotal: {{ totals.subtotal.toFixed(2) }}</span
                    ><span>Descuento: {{ totals.discount.toFixed(2) }}</span
                    ><span>Impuesto: {{ totals.tax.toFixed(2) }}</span
                    ><strong>Total: {{ totals.total.toFixed(2) }}</strong>
                </div>
            </section>
            <p class="error">{{ Object.values(form.errors)[0] }}</p>
            <div class="actions">
                <Link href="/purchases">Cancelar</Link
                ><button :disabled="form.processing">Confirmar compra</button>
            </div>
        </form>
    </main>
</template>

<style scoped>
.page {
    max-width: 1500px;
    margin: auto;
    padding: 2rem;
}
.heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.heading a {
    color: var(--p-primary-color);
}
.eyebrow {
    color: var(--p-primary-color);
    font-weight: 800;
    font-size: 0.75rem;
    letter-spacing: 0.15em;
}
.card {
    padding: 1.5rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 1rem;
    margin: 1rem 0;
    background: var(--p-content-background);
}
.grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
.wide {
    grid-column: 1/-1;
}
label {
    display: grid;
    gap: 0.4rem;
    font-weight: 600;
}
input,
select,
textarea {
    padding: 0.75rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 0.5rem;
    background: transparent;
    color: inherit;
    min-width: 0;
}
textarea {
    min-height: 5rem;
}
.line,
.line-head {
    display: grid;
    grid-template-columns: 2fr repeat(4, 1fr) auto;
    gap: 0.6rem;
    margin-bottom: 0.7rem;
}
.line-head {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--p-text-muted-color);
}
.help {
    color: var(--p-text-muted-color);
    font-size: 0.9rem;
}
.totals,
.actions {
    display: flex;
    justify-content: flex-end;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-top: 1rem;
}
.actions a,
button {
    padding: 0.7rem 1rem;
    border: 0;
    border-radius: 0.5rem;
    background: var(--p-primary-color);
    color: var(--p-primary-contrast-color);
    font-weight: 700;
    text-decoration: none;
}
.actions a,
.secondary {
    background: transparent;
    color: inherit;
    border: 1px solid var(--p-content-border-color);
}
.danger {
    background: #ef4444;
}
.error {
    color: #ef4444;
}
@media (max-width: 900px) {
    .grid {
        grid-template-columns: 1fr;
    }
    .wide {
        grid-column: auto;
    }
    .line-head {
        display: none;
    }
    .line {
        grid-template-columns: 1fr 1fr;
    }
    .line select {
        grid-column: 1/-1;
    }
}
</style>
