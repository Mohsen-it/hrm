<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, IconButton, DataTable, Badge, Alert, ConfirmDialog } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    periods: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const showPublishDialog = ref(false);
const showRegenerateDialog = ref(false);
const selectedPeriod = ref(null);

const generateForm = useForm({
    year: new Date().getFullYear(),
    month: new Date().getMonth() + 1,
});

function handleGenerate() {
    generateForm.post(route('schedules.store'), {
        preserveScroll: true,
    });
}

const statusVariant = (status) => {
    const map = { draft: 'pending', published: 'active', archived: 'inactive' };
    return map[status] || 'inactive';
};

const statusLabel = (status) => {
    const map = {
        draft: t('shifts.draft'),
        published: t('shifts.published'),
        archived: t('shifts.archived'),
    };
    return map[status] || status;
};

const monthNames = computed(() => [
    t('shifts.january'), t('shifts.february'), t('shifts.march'),
    t('shifts.april'), t('shifts.may'), t('shifts.june'),
    t('shifts.july'), t('shifts.august'), t('shifts.september'),
    t('shifts.october'), t('shifts.november'), t('shifts.december'),
]);

const statusFilterOptions = computed(() => [
    { value: '', label: t('shifts.schedule_status') },
    { value: 'draft', label: t('shifts.draft') },
    { value: 'published', label: t('shifts.published') },
    { value: 'archived', label: t('shifts.archived') },
]);

const yearFilterOptions = computed(() => {
    const years = [];
    const currentYear = new Date().getFullYear();
    for (let y = currentYear - 2; y <= currentYear + 1; y++) {
        years.push({ value: y, label: y });
    }
    return [{ value: '', label: t('common.year') }, ...years];
});

const monthFilterOptions = computed(() => [
    { value: '', label: t('common.month') },
    ...monthNames.value.map((name, idx) => ({ value: idx + 1, label: name })),
]);

const columns = computed(() => [
    { key: 'year', label: t('common.year'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: yearFilterOptions.value },
    { key: 'month', label: t('common.month'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: monthFilterOptions.value },
    { key: 'status', label: t('shifts.schedule_status'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: statusFilterOptions.value },
    { key: 'schedule_version', label: t('shifts.schedule_version'), cellClass: 'text-center' },
    { key: 'generated_by_name', label: t('shifts.generated_by') },
    { key: 'generated_at', label: t('shifts.generated_at') },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[200px]' },
]);

function formatMonth(month) {
    return monthNames.value[month - 1] || month;
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('ar-SA', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
}

function onFilterChange(filters) {
    const next = { ...props.filters };
    for (const [key, value] of Object.entries(filters)) {
        if (value === '' || value === null || value === undefined) {
            delete next[key];
        } else {
            next[key] = value;
        }
    }
    router.get(route('schedules.index'), next, { preserveState: true, replace: true });
}

function confirmPublish(period) {
    selectedPeriod.value = period;
    showPublishDialog.value = true;
}

function performPublish() {
    if (!selectedPeriod.value) return;
    router.post(route('schedules.publish', selectedPeriod.value.id), {}, {
        preserveScroll: true,
    });
}

function confirmRegenerate(period) {
    selectedPeriod.value = period;
    showRegenerateDialog.value = true;
}

function performRegenerate() {
    if (!selectedPeriod.value) return;
    router.post(route('schedules.regenerate', selectedPeriod.value.id), {}, {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('shifts.schedules_title')">
        <PageHeader
            :title="t('shifts.schedules_title')"
            :description="t('shifts.schedules_description')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" @click="handleGenerate">
                    {{ t('shifts.generate_schedule') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" dismissible class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" dismissible class="mb-4" />

        <DataTable
            :columns="columns"
            :data="periods"
            :filters="filters"
            :route-name="'schedules.index'"
            :only="['periods']"
            storage-key="schedules"
            @filter-change="onFilterChange"
        >
            <template #cell-month="{ row }">
                <span class="text-[13px]">{{ formatMonth(row.month) }}</span>
            </template>

            <template #cell-status="{ row }">
                <Badge :text="statusLabel(row.status)" :variant="statusVariant(row.status)" />
            </template>

            <template #cell-generated_at="{ row }">
                <span class="text-[13px]">{{ formatDateTime(row.generated_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton
                        icon="fas fa-eye"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.view')"
                        :href="route('schedules.show', row.id)"
                    />
                    <IconButton
                        v-if="row.status === 'draft'"
                        icon="fas fa-check-circle"
                        variant="success"
                        size="sm"
                        :aria-label="t('shifts.publish_schedule')"
                        @click="confirmPublish(row)"
                    />
                    <IconButton
                        v-if="row.status === 'published'"
                        icon="fas fa-sync-alt"
                        variant="warning"
                        size="sm"
                        :aria-label="t('shifts.regenerate_schedule')"
                        @click="confirmRegenerate(row)"
                    />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showPublishDialog"
            :title="t('shifts.publish_schedule')"
            :message="t('shifts.publish_schedule') + '?'"
            :confirm-text="t('common.confirm')"
            :cancel-text="t('common.cancel')"
            confirm-variant="primary"
            @confirm="performPublish"
        />

        <ConfirmDialog
            v-model="showRegenerateDialog"
            :title="t('shifts.regenerate_schedule')"
            :message="t('shifts.regenerate_schedule') + '?'"
            :confirm-text="t('common.confirm')"
            :cancel-text="t('common.cancel')"
            confirm-variant="warning"
            @confirm="performRegenerate"
        />
    </AppLayout>
</template>
