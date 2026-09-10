<script setup lang="ts">
import Button from 'primevue/button';
import Card from 'primevue/card';
</script>

<template>
    <main class="dashboard">
        <h1>Vidriería Maradiaga ERP</h1>

        <div class="cards">
            <Card>
                <template #title>Inventory Value</template>

                <template #content>
                    <h2>$248,540</h2>
                </template>
            </Card>

            <Card>
                <template #title>Products</template>

                <template #content>
                    <h2>1,284</h2>
                </template>
            </Card>

            <Card>
                <template #title>Pending Entries</template>

                <template #content>
                    <h2>18</h2>
                </template>
            </Card>

            <Card>
                <template #title>Critical Stock</template>

                <template #content>
                    <h2>24</h2>
                </template>
            </Card>
        </div>

        <div class="actions">
            <Button
                label="Register Entry"
                icon="pi pi-plus"
            />
        </div>
    </main>
</template>

<style scoped>
.dashboard {
    padding: 2rem;
}

.cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    margin-top: 2rem;
}

.actions {
    margin-top: 2rem;
}

@media (max-width: 900px) {
    .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .cards {
        grid-template-columns: 1fr;
    }
}
</style>
