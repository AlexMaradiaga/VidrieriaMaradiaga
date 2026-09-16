import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

interface InstallPromptEvent extends Event {
    prompt(): Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed'; platform: string }>;
}

const promptEvent = ref<InstallPromptEvent | null>(null);
const installed = ref(false);

export function usePwaInstall() {
    const capturePrompt = (event: Event): void => {
        event.preventDefault();
        promptEvent.value = event as InstallPromptEvent;
    };

    const markInstalled = (): void => {
        installed.value = true;
        promptEvent.value = null;
    };

    onMounted(() => {
        installed.value = window.matchMedia('(display-mode: standalone)').matches;
        window.addEventListener('beforeinstallprompt', capturePrompt);
        window.addEventListener('appinstalled', markInstalled);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeinstallprompt', capturePrompt);
        window.removeEventListener('appinstalled', markInstalled);
    });

    async function install(): Promise<boolean> {
        if (!promptEvent.value) return false;

        await promptEvent.value.prompt();
        const choice = await promptEvent.value.userChoice;
        promptEvent.value = null;

        return choice.outcome === 'accepted';
    }

    return {
        canInstall: computed(() => promptEvent.value !== null && !installed.value),
        installed: computed(() => installed.value),
        install,
    };
}
