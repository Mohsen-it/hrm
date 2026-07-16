<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    balances: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const statusVariant = (status) => {
    const map = { pending: 'pending', approved: 'active', rejected: 'inactive', cancelled: 'inactive' };
    return map[status] || 'pending';
};

const columns = computed(() => [
    { key: 'vacation_type.name_ar', label: t('vacations.vacation_type') },
    { key: 'start_date', label: t('vacations.start_date'), sortable: true },
    { key: 'end_date', label: t('vacations.end_date'), sortable: true },
    { key: 'total_days', label: t('vacations.total_days'), cellClass: 'text-center' },
    { key: 'status', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[120px]' },
]);

function applyFilter(key, value) {
    router.get(route('vacations.my.index'), { ...props.filters, [key]: value }, { preserveState: true, preserveScroll: true, replace: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('vacations.my_vacations')">
        <PageHeader :title="t('vacations.my_vacations')" :description="t('vacations.my_description')">
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('vacations.my.create')">
                    {{ t('vacations.new_request') }}
                </Button>
            </template>
        </PageHeader>

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>

        <div v-if="balances.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div v-for="balance in balances" :key="balance.id" class="card p-4">
                <p class="text-[12px] text-[var(--color-ink-muted)] mb-1">{{ balance.vacation_type?.name_ar || t('vacations.vacation') }}</p>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[20px] font-bold text-[var(--color-ink)]">{{ balance.remaining_days || 0 }}</p>
                        <p class="text-[11px] text-[var(--color-ink-subtle)]">{{ t('vacations.remaining') }}</p>
                    </div>
                    <div class="text-left">
                        <p class="text-[14px] text-[var(--color-ink-muted)]">{{ balance.total_days || 0 }}</p>
                        <p class="text-[11px] text-[var(--color-ink-subtle)]">{{ t('vacations.total') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card p-4 mb-4">
            <select class="form-input max-w-[180px]" :value="filters.status ?? ''" @change="applyFilter('status', $event.target.value)">
                <option value="">{{ t('common.all_statuses') }}</option>
                <option value="pending">{{ t('vacations.pending') }}</option>
                <option value="approved">{{ t('vacations.approved') }}</option>
                <option value="rejected">{{ t('vacations.rejected') }}</option>
            </select>
        </div>

        <DataTable :columns="columns" :data="requests">
            <template #cell-status="{ row }">
                <Badge :text="t('vacations.' + row.status)" :variant="statusVariant(row.status)" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1.5">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" variant="info" :href="route('vacations.my.show', row.id)" />
                </div>
            </template>
        </DataTable>
    </AppLayout>
</template>
