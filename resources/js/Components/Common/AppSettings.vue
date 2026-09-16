<script setup lang="ts">
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Button from 'primevue/button';
import Drawer from 'primevue/drawer';
import {
    type AppAccent,
    type AppLocale,
    type AppTheme,
    useAppSettings,
} from '../../Composables/useAppSettings';
import { usePwaInstall } from '../../Composables/usePwaInstall';

const open = ref(false);
const { t, locale } = useI18n();
const { theme, accent, language } = useAppSettings();
const { canInstall, installed, install } = usePwaInstall();

watch(
    language,
    (value) => {
        locale.value = value;
    },
    { immediate: true },
);

const languages: Array<{ value: AppLocale; icon: string; label: string }> = [
    { value: 'es', icon: '🇭🇳', label: 'settings.spanish' },
    { value: 'en', icon: '🇺🇸', label: 'settings.english' },
];

const themes: Array<{ value: AppTheme; icon: string; label: string }> = [
    { value: 'dark', icon: 'pi pi-moon', label: 'settings.dark' },
    { value: 'light', icon: 'pi pi-sun', label: 'settings.light' },
    { value: 'soft', icon: 'pi pi-cloud', label: 'settings.soft' },
];

const accents: Array<{ value: AppAccent; label: string }> = [
    { value: 'emerald', label: 'settings.emerald' },
    { value: 'blue', label: 'settings.blue' },
    { value: 'violet', label: 'settings.violet' },
    { value: 'amber', label: 'settings.amber' },
];
</script>

<template>
    <Button
        class="settings-trigger"
        icon="pi pi-cog"
        rounded
        :aria-label="t('settings.open')"
        :title="t('settings.open')"
        @click="open = true"
    />

    <Drawer
        v-model:visible="open"
        position="right"
        class="settings-drawer"
        :header="t('settings.title')"
    >
        <section class="settings-section">
            <h2>{{ t('settings.language') }}</h2>

            <div class="choice-grid two-columns">
                <button
                    v-for="item in languages"
                    :key="item.value"
                    type="button"
                    class="choice-card"
                    :class="{ selected: language === item.value }"
                    @click="language = item.value"
                >
                    <span class="choice-icon">{{ item.icon }}</span>
                    <span>{{ t(item.label) }}</span>
                </button>
            </div>
        </section>

        <section class="settings-section">
            <h2>{{ t('settings.application') }}</h2>

            <Button
                v-if="canInstall"
                :label="t('settings.install')"
                icon="pi pi-download"
                fluid
                @click="install"
            />

            <p v-else class="install-status">
                <i :class="installed ? 'pi pi-check-circle' : 'pi pi-info-circle'" aria-hidden="true"></i>
                {{ t(installed ? 'settings.installed' : 'settings.installHelp') }}
            </p>
        </section>

        <section class="settings-section">
            <h2>{{ t('settings.appearance') }}</h2>

            <div class="choice-grid">
                <button
                    v-for="item in themes"
                    :key="item.value"
                    type="button"
                    class="choice-card theme-card"
                    :class="[`preview-${item.value}`, { selected: theme === item.value }]"
                    @click="theme = item.value"
                >
                    <i :class="item.icon" aria-hidden="true"></i>
                    <span>{{ t(item.label) }}</span>
                </button>
            </div>
        </section>

        <section class="settings-section">
            <h2>{{ t('settings.accent') }}</h2>

            <div class="accent-grid">
                <button
                    v-for="item in accents"
                    :key="item.value"
                    type="button"
                    class="accent-choice"
                    :class="{ selected: accent === item.value }"
                    :data-color="item.value"
                    :aria-label="t(item.label)"
                    :title="t(item.label)"
                    @click="accent = item.value"
                >
                    <span></span>
                    <i v-if="accent === item.value" class="pi pi-check"></i>
                </button>
            </div>
        </section>

        <p class="settings-note">
            <i class="pi pi-save" aria-hidden="true"></i>
            {{ t('settings.saved') }}
        </p>
    </Drawer>
</template>

<style scoped>
.settings-trigger {
    position: fixed;
    right: 1.25rem;
    bottom: 1.25rem;
    z-index: 1000;
    width: 3rem;
    height: 3rem;
    box-shadow: 0 12px 30px rgb(0 0 0 / 25%);
}

.settings-section + .settings-section {
    margin-top: 2rem;
}

.settings-section h2 {
    margin: 0 0 0.75rem;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--p-text-muted-color);
}

.choice-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
}

.choice-grid.two-columns {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.choice-card {
    display: flex;
    min-height: 5rem;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 0.5rem;
    border: 1px solid var(--p-content-border-color);
    border-radius: 0.75rem;
    background: var(--p-content-background);
    color: var(--p-text-color);
    cursor: pointer;
}

.choice-card:hover,
.choice-card.selected {
    border-color: var(--p-primary-color);
}

.choice-card.selected {
    box-shadow: 0 0 0 2px color-mix(in srgb, var(--p-primary-color) 25%, transparent);
}

.choice-icon {
    font-size: 1.5rem;
}

.theme-card i {
    font-size: 1.2rem;
}

.preview-dark {
    background: #111827;
    color: #f8fafc;
}

.preview-light {
    background: #ffffff;
    color: #172033;
}

.preview-soft {
    background: #f4f7f5;
    color: #24352f;
}

.accent-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.accent-choice {
    display: grid;
    width: 2.75rem;
    height: 2.75rem;
    place-items: center;
    border: 2px solid transparent;
    border-radius: 999px;
    background: transparent;
    cursor: pointer;
}

.accent-choice span {
    grid-area: 1 / 1;
    width: 2rem;
    height: 2rem;
    border-radius: inherit;
    background: var(--preview-color);
}

.accent-choice i {
    z-index: 1;
    grid-area: 1 / 1;
    color: white;
    font-size: 0.8rem;
}

.accent-choice.selected {
    border-color: var(--p-text-color);
}

.accent-choice[data-color='emerald'] { --preview-color: #10b981; }
.accent-choice[data-color='blue'] { --preview-color: #3b82f6; }
.accent-choice[data-color='violet'] { --preview-color: #8b5cf6; }
.accent-choice[data-color='amber'] { --preview-color: #d97706; }

.settings-note {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 2rem;
    color: var(--p-text-muted-color);
    font-size: 0.85rem;
}

.install-status {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    margin: 0;
    color: var(--p-text-muted-color);
    line-height: 1.45;
}

@media (max-width: 480px) {
    .settings-trigger {
        right: 0.85rem;
        bottom: 0.85rem;
    }
}
</style>
