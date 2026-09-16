import { readonly, ref, watch } from 'vue';

export type AppTheme = 'dark' | 'light' | 'soft';
export type AppAccent = 'emerald' | 'blue' | 'violet' | 'amber';
export type AppLocale = 'es' | 'en';

function storedValue<T extends string>(
    key: string,
    allowed: readonly T[],
    fallback: T,
): T {
    const value = localStorage.getItem(key) as T | null;
    return value !== null && allowed.includes(value) ? value : fallback;
}

const theme = ref<AppTheme>(
    storedValue('vidrieria.theme', ['dark', 'light', 'soft'], 'soft'),
);

const accent = ref<AppAccent>(
    storedValue(
        'vidrieria.accent',
        ['emerald', 'blue', 'violet', 'amber'],
        'emerald',
    ),
);

const language = ref<AppLocale>(
    storedValue('vidrieria.locale', ['es', 'en'], 'es'),
);

function applyAppearance(): void {
    const root = document.documentElement;

    root.classList.toggle('app-dark', theme.value === 'dark');
    root.dataset.theme = theme.value;
    root.dataset.accent = accent.value;
    root.lang = language.value;

    localStorage.setItem('vidrieria.theme', theme.value);
    localStorage.setItem('vidrieria.accent', accent.value);
    localStorage.setItem('vidrieria.locale', language.value);
}

let initialized = false;

export function initializeAppSettings(): void {
    if (initialized) return;

    initialized = true;
    applyAppearance();
    watch([theme, accent, language], applyAppearance, { flush: 'sync' });
}

export function useAppSettings() {
    return {
        theme,
        accent,
        language,
        availableThemes: readonly(['dark', 'light', 'soft'] as const),
        availableAccents: readonly(
            ['emerald', 'blue', 'violet', 'amber'] as const,
        ),
    };
}
