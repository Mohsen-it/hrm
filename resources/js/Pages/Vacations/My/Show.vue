<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    request: { type: Object, required: true },
});

const statusVariant = (status) => {
    const map = { pending: 'pending', approved: 'active', rejected: 'inactive', cancelled: 'inactive' };
    return map[status] || 'pending';
};

const fields = computed(() => [
    { label: t('vacations.vacation_type'), value: props.request.vacation_type?.name_ar || '—' },
    { label: t('vacations.start_date'), value: props.request.start_date || '—' },
    { label: t('vacations.end_date'), value: props.request.end_date || '—' },
    { label: t('vacations.total_days'), value: props.request.total_days },
    { label: t('vacations.reason'), value: props.request.reason || '—' },
    { label: t('vacations.manager_note'), value: props.request.manager_note || '—' },
    { label: t('vacations.decided_at'), value: props.request.decided_at || '—' },
]);
</script>

<template>
    <AppLayout :title="t('vacations.view_request')">
        <PageHeader :title="t('vacations.view_request')" :description="request.vacation_type?.name_ar">
            <template #actions>
                <Button variant="secondary" :href="route('vacations.my.index')">{{ t('common.back') }}</Button>
                <Button v-if="request.status === 'pending'" variant="primary" icon="fas fa-edit" :href="route('vacations.my.edit', request.id)">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <div class="card p-6">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-[var(--color-hairline)]">
                <div class="w-16 h-16 rounded-md bg-[var(--color-surface-2)] flex items-center justify-center border border-[var(--color-hairline)]">
                    <i class="fas fa-calendar-check text-[28px] text-[var(--color-ink-subtle)]"></i>
                </div>
                <div>
                    <h2 class="text-[18px] font-semibold text-[var(--color-ink)]">{{ request.vacation_type?.name_ar }}</h2>
                    <p class="text-[13px] text-[var(--color-ink-muted)] mt-1">{{ request.start_date }} — {{ request.end_date }}</p>
                    <div class="mt-2">
                        <Badge :text="t('vacations.' + request.status)" :variant="statusVariant(request.status)" />
                    </div>
                </div>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-right">
                    <dt class="text-[12px] font-semibold text-[var(--color-ink-subtle)] uppercase tracking-wider">{{ field.label }}</dt>
                    <dd class="text-[14px] text-[var(--color-ink)] mt-1 break-words">{{ field.value }}</dd>
                </div>
            </dl>

            <div v-if="request.status === 'pending'" class="mt-6 pt-6 border-t border-[var(--color-hairline)]">
                <Button
                    variant="danger"
                    icon="fas fa-times"
                    @click="router.post(route('vacations.my.cancel', request.id), {}, { preserveScroll: true })"
                >
                    {{ t('vacations.cancel_request') }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
