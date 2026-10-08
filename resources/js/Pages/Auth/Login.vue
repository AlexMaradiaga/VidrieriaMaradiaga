<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useI18n } from 'vue-i18n';
import brandLogo from '../../assets/vidrieria-maradiaga-logo.png';

const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
});

function submit(): void {
    form.clearErrors();

    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="t('login.pageTitle')" />

    <main class="login-page">
        <section class="brand-panel" aria-label="Vidriería Maradiaga">
            <img
                class="brand-logo"
                :src="brandLogo"
                alt="Vidriería Maradiaga"
            />

            <h1>{{ t('login.slogan') }}</h1>

            <p class="brand-description">
                {{ t('login.brandDescription') }}
            </p>

            <span class="brand-footer">{{ t('login.system') }}</span>
        </section>

        <section class="form-panel">
            <form class="login-form" @submit.prevent="submit">
                <p class="eyebrow">{{ t('login.welcome') }}</p>

                <h2>{{ t('login.title') }}</h2>

                <p class="form-description">
                    {{ t('login.instruction') }}
                </p>

                <div class="field">
                    <label for="email">{{ t('login.email') }}</label>

                    <InputText
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        maxlength="255"
                        required
                        autofocus
                        :invalid="Boolean(form.errors.email)"
                        :aria-invalid="Boolean(form.errors.email)"
                        :aria-describedby="form.errors.email ? 'email-error' : undefined"
                    />

                    <small
                        v-if="form.errors.email"
                        id="email-error"
                        class="error"
                        role="alert"
                    >
                        {{ form.errors.email }}
                    </small>
                </div>

                <div class="field">
                    <label for="password">{{ t('login.password') }}</label>

                    <InputText
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        :invalid="Boolean(form.errors.password)"
                        :aria-invalid="Boolean(form.errors.password)"
                        :aria-describedby="form.errors.password ? 'password-error' : undefined"
                    />

                    <small
                        v-if="form.errors.password"
                        id="password-error"
                        class="error"
                        role="alert"
                    >
                        {{ form.errors.password }}
                    </small>
                </div>

                <Button
                    type="submit"
                    :label="t('login.submit')"
                    icon="pi pi-sign-in"
                    :loading="form.processing"
                    :disabled="form.processing"
                    class="submit-button"
                />

                <p class="help">
                    {{ t('login.help') }}
                </p>
            </form>
        </section>
    </main>
</template>

<style scoped>
.login-page {
    min-height: 100vh;
    min-height: 100dvh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    background: #f5f7fb;
    color: #172338;
}

.brand-panel {
    display: flex;
    flex-direction: column;
    justify-content: center;
    padding: clamp(2rem, 6vw, 6rem);
    background: linear-gradient(145deg, #11243a, #164c60);
    color: white;
}

.brand-logo {
    width: min(100%, 22rem);
    height: auto;
    margin-bottom: 2rem;
    border-radius: 0.8rem;
    box-shadow: 0 18px 45px #06111f55;
}

.eyebrow {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.14em;
}

.brand-panel h1 {
    max-width: 520px;
    margin: 1rem 0;
    font-size: clamp(2rem, 4vw, 3.5rem);
    line-height: 1.12;
}

.brand-description {
    max-width: 420px;
    color: #d4e2ed;
    line-height: 1.7;
}

.brand-footer {
    margin-top: 3rem;
    font-size: 0.8rem;
    color: #bdcedb;
}

.form-panel {
    display: grid;
    place-items: center;
    padding: 2rem;
}

.login-form {
    width: 100%;
    max-width: 400px;
}

.login-form h2 {
    margin: 0.6rem 0;
    font-size: 2rem;
}

.form-description,
.help {
    color: #58677a;
    line-height: 1.5;
}

.form-description {
    margin-bottom: 2rem;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1.25rem;
}

.field label {
    font-size: 0.9rem;
    font-weight: 600;
}

.error {
    color: #b42318;
}

.submit-button {
    width: 100%;
    margin-top: 0.5rem;
}

.help {
    margin-top: 1.5rem;
    text-align: center;
    font-size: 0.85rem;
}

@media (max-width: 760px) {
    .login-page {
        grid-template-columns: 1fr;
    }

    .brand-panel {
        padding: 1.75rem;
    }

    .brand-logo {
        width: min(100%, 15rem);
        margin-bottom: 0.75rem;
    }

    .brand-panel h1 {
        font-size: 1.8rem;
    }

    .brand-footer {
        display: none;
    }

    .form-panel {
        padding: 2rem 1.5rem;
    }
}
</style>
