<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import Button from '@/Components/ui/Button.vue';

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
    <div :dir="direction" class="min-h-screen flex items-center justify-center bg-[#f8fafc] p-4">
        <!-- Language switcher (top-left in RTL) -->
        <div class="absolute top-4" :class="direction === 'rtl' ? 'left-4' : 'right-4'">
            <LanguageSwitcher :dir="direction" />
        </div>

        <div class="w-full max-w-[420px]">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <div
                    class="w-14 h-14 rounded-lg bg-[#2563eb] flex items-center justify-center text-white font-bold text-[28px] shadow-[0_4px_6px_-1px_rgba(0,0,0,0.07)]"
                >
                    H
                </div>
                <h1 class="mt-4 text-[24px] font-bold text-[#0f172a]">HRM</h1>
                <p class="mt-1 text-[14px] text-[#475569]">{{ t('common.login_subtitle') }}</p>
            </div>

            <!-- Form card -->
            <div class="card p-6">
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email -->
                    <div>
                        <label
                            class="block text-[14px] font-semibold text-[#475569] mb-1 text-right"
                            for="email"
                        >
                            {{ t('common.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="form-input"
                            :placeholder="t('common.email_placeholder')"
                            required
                            autofocus
                            dir="rtl"
                        />
                        <p v-if="errors.email" class="mt-1 text-[11px] text-[#dc2626] text-right">
                            {{ errors.email }}
                        </p>
                    </div>

                    <!-- Password -->
                    <div>
                        <label
                            class="block text-[14px] font-semibold text-[#475569] mb-1 text-right"
                            for="password"
                        >
                            {{ t('common.password') }}
                        </label>
                        <input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="form-input"
                            :placeholder="t('common.password_placeholder')"
                            required
                            dir="rtl"
                        />
                        <p v-if="errors.password" class="mt-1 text-[11px] text-[#dc2626] text-right">
                            {{ errors.password }}
                        </p>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center gap-2">
                        <input
                            id="remember"
                            v-model="form.remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border-[#cbd5e1] text-[#2563eb] focus:ring-[#dbeafe]"
                        />
                        <label for="remember" class="text-[14px] text-[#475569] cursor-pointer">
                            {{ t('common.remember_me') }}
                        </label>
                    </div>

                    <!-- Submit -->
                    <Button
                        type="submit"
                        variant="primary"
                        block
                        :loading="form.processing"
                        icon="fas fa-sign-in-alt"
                    >
                        {{ t('common.login') }}
                    </Button>
                </form>
            </div>

            <!-- Footer -->
            <p class="mt-6 text-center text-[12px] text-[#94a3b8]">
                {{ t('common.copyright') }}
            </p>
        </div>
    </div>
</template>
