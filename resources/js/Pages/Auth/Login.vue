<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import { Button, FormInput, FormCheckbox } from '@/Components/ui';

const { t, direction } = useTranslations();
const page = usePage();

const errors = page.props.errors || {};

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <div :dir="direction" class="min-h-screen flex items-center justify-center bg-mistral-surface p-4 relative">
        <!-- Language switcher -->
        <div class="absolute top-4" :class="direction === 'rtl' ? 'left-4' : 'right-4'">
            <LanguageSwitcher :dir="direction" />
        </div>

        <div class="w-full max-w-[400px]">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <div
                    class="w-14 h-14 rounded-2xl bg-mistral-primary flex items-center justify-center text-white font-bold text-[26px] shadow-lg shadow-mistral-primary/20"
                >
                    H
                </div>
                <h1 class="mt-4 text-[24px] font-bold text-mistral-ink tracking-tight">HRM</h1>
                <p class="mt-1 text-[14px] text-mistral-stone">{{ t('common.login_subtitle') }}</p>
            </div>

            <!-- Form card -->
            <div class="bg-white border border-mistral-hairline-soft rounded-2xl p-6 shadow-level-2">
                <form @submit.prevent="submit" class="space-y-4">
                    <FormInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        :label="t('common.email')"
                        :placeholder="t('common.email_placeholder')"
                        :error="errors.email"
                        required
                        autofocus
                    />

                    <FormInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        :label="t('common.password')"
                        :placeholder="t('common.password_placeholder')"
                        :error="errors.password"
                        required
                    />

                    <div class="flex items-center justify-between">
                        <FormCheckbox
                            id="remember"
                            v-model="form.remember"
                            :label="t('common.remember_me')"
                        />
                    </div>

                    <Button
                        type="submit"
                        variant="primary"
                        block
                        size="lg"
                        :loading="form.processing"
                        icon="fas fa-right-to-bracket"
                    >
                        {{ t('common.login') }}
                    </Button>
                </form>
            </div>

            <!-- Footer -->
            <p class="mt-6 text-center text-[12px] text-mistral-muted">
                {{ t('common.copyright') }}
            </p>
        </div>
    </div>
</template>
