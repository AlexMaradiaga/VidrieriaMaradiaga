<script setup lang="ts">
import { computed, reactive, ref } from "vue";
import { Head } from "@inertiajs/vue3";
import Button from "primevue/button";
import InventoryNav from "../../../Components/Common/InventoryNav.vue";
interface Part {
    label: string;
    width: number;
    height: number;
    quantity: number;
    rotate: boolean;
}
interface Placement {
    label: string;
    width: number;
    height: number;
    x: number;
    y: number;
    sheet: number;
}
interface Sheet {
    used: number;
    rows: { y: number; height: number; x: number }[];
}
const sheet = reactive({ width: 2440, height: 1830, kerf: 3, margin: 10 });
const parts = reactive<Part[]>([
    { label: "Pieza A", width: 600, height: 400, quantity: 2, rotate: true },
]);
const result = ref<{
    placements: Placement[];
    sheets: Sheet[];
    usedArea: number;
} | null>(null);
function add() {
    parts.push({
        label: `Pieza ${String.fromCharCode(65 + parts.length)}`,
        width: 0,
        height: 0,
        quantity: 1,
        rotate: true,
    });
}
function remove(i: number) {
    if (parts.length > 1) parts.splice(i, 1);
}
function calculate() {
    const usableW = sheet.width - 2 * sheet.margin,
        usableH = sheet.height - 2 * sheet.margin;
    if (usableW <= 0 || usableH <= 0) return;
    const expanded = parts
        .flatMap((p) =>
            Array.from({ length: Math.max(0, Math.floor(p.quantity)) }, () => ({
                ...p,
            })),
        )
        .filter((p) => p.width > 0 && p.height > 0)
        .sort(
            (a, b) => Math.max(b.width, b.height) - Math.max(a.width, a.height),
        );
    const sheets: Sheet[] = [];
    const placements: Placement[] = [];
    let usedArea = 0;
    for (const p of expanded) {
        let placed = false;
        for (let si = 0; si <= sheets.length && !placed; si++) {
            if (si === sheets.length) sheets.push({ used: 0, rows: [] });
            const target = sheets[si]!;
            const choices: Array<[number, number]> =
                p.rotate && p.width !== p.height
                    ? [
                          [p.width, p.height],
                          [p.height, p.width],
                      ]
                    : [[p.width, p.height]];
            for (const [w, h] of choices) {
                for (const row of target.rows) {
                    if (h <= row.height && row.x + w <= usableW) {
                        placements.push({
                            label: p.label,
                            width: w,
                            height: h,
                            x: sheet.margin + row.x,
                            y: sheet.margin + row.y,
                            sheet: si + 1,
                        });
                        row.x += w + sheet.kerf;
                        target.used += w * h;
                        usedArea += w * h;
                        placed = true;
                        break;
                    }
                }
                if (placed) break;
                const nextY = target.rows.length
                    ? Math.max(
                          ...target.rows.map(
                              (r) => r.y + r.height + sheet.kerf,
                          ),
                      )
                    : 0;
                if (w <= usableW && nextY + h <= usableH) {
                    target.rows.push({
                        y: nextY,
                        height: h,
                        x: w + sheet.kerf,
                    });
                    placements.push({
                        label: p.label,
                        width: w,
                        height: h,
                        x: sheet.margin,
                        y: sheet.margin + nextY,
                        sheet: si + 1,
                    });
                    target.used += w * h;
                    usedArea += w * h;
                    placed = true;
                    break;
                }
            }
            if (
                !placed &&
                target.rows.length === 0 &&
                si === sheets.length - 1
            ) {
                sheets.pop();
                break;
            }
        }
    }
    result.value = { placements, sheets, usedArea };
}
const utilization = computed(() =>
    result.value && result.value.sheets.length
        ? (
              (result.value.usedArea /
                  (result.value.sheets.length * sheet.width * sheet.height)) *
              100
          ).toFixed(2)
        : "0.00",
);
const unplaced = computed(() =>
    Math.max(
        0,
        parts.reduce((s, p) => s + p.quantity, 0) -
            (result.value?.placements.length ?? 0),
    ),
);
</script>
<template>
    <Head title="Calculadora de cortes | Vidriería Maradiaga" />
    <main class="page">
        <InventoryNav />
        <h1>Calculadora de cortes rectangulares</h1>
        <p class="lead">
            Estimación por acomodo de filas. Verifica medidas y sentido del
            material antes de cortar.
        </p>
        <section class="panel">
            <h2>Lámina o plancha</h2>
            <div class="grid four">
                <label
                    >Ancho (mm)<input
                        v-model.number="sheet.width"
                        type="number"
                        min="1" /></label
                ><label
                    >Alto (mm)<input
                        v-model.number="sheet.height"
                        type="number"
                        min="1" /></label
                ><label
                    >Ancho del corte (mm)<input
                        v-model.number="sheet.kerf"
                        type="number"
                        min="0" /></label
                ><label
                    >Margen perimetral (mm)<input
                        v-model.number="sheet.margin"
                        type="number"
                        min="0"
                /></label>
            </div>
            <h2>Piezas</h2>
            <div v-for="(part, index) in parts" :key="index" class="part">
                <input v-model.trim="part.label" placeholder="Nombre" /><input
                    v-model.number="part.width"
                    type="number"
                    min="1"
                    placeholder="Ancho mm"
                /><input
                    v-model.number="part.height"
                    type="number"
                    min="1"
                    placeholder="Alto mm"
                /><input
                    v-model.number="part.quantity"
                    type="number"
                    min="1"
                    placeholder="Cantidad"
                /><label class="check"
                    ><input v-model="part.rotate" type="checkbox" />
                    Girar</label
                ><Button
                    icon="pi pi-trash"
                    severity="danger"
                    text
                    @click="remove(index)"
                />
            </div>
            <div class="actions">
                <Button
                    label="Agregar pieza"
                    icon="pi pi-plus"
                    severity="secondary"
                    outlined
                    @click="add"
                /><Button
                    label="Calcular aprovechamiento"
                    icon="pi pi-calculator"
                    @click="calculate"
                />
            </div>
        </section>
        <section v-if="result" class="panel">
            <h2>Resultado estimado</h2>
            <div class="metrics">
                <article>
                    <span>Planchas requeridas</span
                    ><strong>{{ result.sheets.length }}</strong>
                </article>
                <article>
                    <span>Aprovechamiento</span
                    ><strong>{{ utilization }}%</strong>
                </article>
                <article>
                    <span>Piezas no ubicadas</span
                    ><strong>{{ unplaced }}</strong>
                </article>
            </div>
            <div
                v-for="(_, sheetIndex) in result.sheets"
                :key="sheetIndex"
                class="sheet"
            >
                <h3>Plancha {{ sheetIndex + 1 }}</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Pieza</th>
                            <th>Medida</th>
                            <th>Posición inicial</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(p, i) in result.placements.filter(
                                (p) => p.sheet === sheetIndex + 1,
                            )"
                            :key="i"
                        >
                            <td>{{ p.label }}</td>
                            <td>{{ p.width }} × {{ p.height }} mm</td>
                            <td>X {{ p.x }} / Y {{ p.y }} mm</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
<style scoped>
.page {
    max-width: 1150px;
    margin: auto;
    padding: clamp(1rem, 3vw, 2.5rem);
    font-family: system-ui, sans-serif;
}
.lead {
    color: var(--p-text-muted-color);
}
.panel {
    margin-top: 1.5rem;
    padding: 1.4rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 1rem;
    background: var(--p-content-background);
}
.grid {
    display: grid;
    gap: 1rem;
}
.four {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}
label {
    display: grid;
    gap: 0.35rem;
    font-weight: 650;
}
input {
    padding: 0.7rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 0.5rem;
    background: var(--p-form-field-background);
    color: var(--p-text-color);
}
.part {
    display: grid;
    grid-template-columns: 1.5fr repeat(3, 1fr) auto auto;
    gap: 0.6rem;
    margin: 0.7rem 0;
}
.check {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}
.actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 1rem;
}
.metrics {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
.metrics article {
    padding: 1rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 0.75rem;
}
.metrics span {
    display: block;
    color: var(--p-text-muted-color);
}
.metrics strong {
    font-size: 1.8rem;
}
.sheet {
    margin-top: 1.5rem;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th,
td {
    padding: 0.7rem;
    border-bottom: 1px solid var(--p-content-border-color);
    text-align: left;
}
@media (max-width: 800px) {
    .four,
    .metrics,
    .part {
        grid-template-columns: 1fr;
    }
}
</style>
