<script setup>
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    title: { type: String, default: '' },
    showMobileToggle: { type: Boolean, default: false },
});

const emit = defineEmits(['toggle-mobile-sidebar']);

const { t } = useTranslations();
</script>

<template>
    <header
        class="navbar sticky top-0 z-30 flex items-center justify-between px-4 md:px-6 bg-[var(--color-canvas)] border-b border-[var(--color-hairline)] h-14"
    >
        <div class="flex items-center gap-3">
            <button
                v-if="showMobileToggle"
                type="button"
                class="md:hidden btn-icon text-[var(--color-ink)]"
                :aria-label="t('common.main') || 'Open menu'"
                @click="emit('toggle-mobile-sidebar')"
            >
                <i class="fas fa-bars text-[18px]" aria-hidden="true"></i>
            </Button>
            <h1 class="text-[18px] font-semibold text-[var(--color-ink)]">{{ title }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <button
                class="btn-icon text-[var(--color-ink-mute)] hover:text-[var(--color-primary)]"
                type="button"
                :title="t('common.notifications') || 'Notifications'"
                :aria-label="t('common.notifications') || 'Notifications'"
            >
                <i class="fas fa-bell" aria-hidden="true"></i>
            </Button>
            <div class="w-px h-6 bg-[var(--color-hairline)]"></div>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="btn-icon text-[var(--color-ink-mute)] hover:text-[var(--color-danger)]"
                :title="t('common.logout')"
                :aria-label="t('common.logout')"
            >
                <i class="fas fa-sign-out-alt rtl-flip" aria-hidden="true"></i>
            </Link>
        </div>
    </header>
</template>
