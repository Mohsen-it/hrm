<script setup>
import { ref, computed } from 'vue';
import { router, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import Badge from '@/Components/ui/Badge.vue';
import Alert from '@/Components/ui/Alert.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
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

const statusOptions = computed(() => [
    { value: '', label: t('shifts.schedule_status') },
    { value: 'draft', label: t('shifts.draft') },
    { value: 'published', label: t('shifts.published') },
    { value: 'archived', label: t('shifts.archived') },
]);

const yearOptions = computed(() => {
    const years = [];
    const currentYear = new Date().getFullYear();
    for (let y = currentYear - 2; y <= currentYear + 1; y++) {
        years.push({ value: y, label: y });
    }
    return [{ value: '', label: t('common.year') }, ...years];
});

const monthOptions = computed(() => [
    { value: '', label: t('common.month') },
    ...monthNames.value.map((name, idx) => ({ value: idx + 1, label: name })),
]);

const columns = computed(() => [
    { key: 'year', label: t('common.year'), cellClass: 'text-center' },
    { key: 'month', label: t('common.month'), cellClass: 'text-center' },
    { key: 'status', label: t('shifts.schedule_status'), cellClass: 'text-center' },
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

function applyFilter(key, value) {
    const next = { ...props.filters };
    if (value === '' || value === null || value === undefined) {
        delete next[key];
    } else {
        next[key] = value;
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

        <nav class="flex items-center gap-0 border-b border-mistral-hairline-soft overflow-x-auto mb-6" role="tablist">
            <Link
                :href="route('shift-categories.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.shift_categories') }}
            </Link>
            <Link
                :href="route('time-schedules.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.time_schedules_title') }}
            </Link>
            <Link
                :href="route('schedules.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-primary border-mistral-primary"
                role="tab"
                aria-selected="true"
            >
                {{ t('shifts.schedules_title') }}
            </Link>
            <Link
                :href="route('shifts.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.title') }}
            </Link>
            <Link
                :href="route('shift-assignments.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.shift_assignments') }}
            </Link>
        </nav>

        <Card variant="base" padding="sm" class="mb-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <FormSelect
                        :options="yearOptions"
                        :model-value="filters.year ?? ''"
                        @update:modelValue="applyFilter('year', $event)"
                    />
                    <FormSelect
                        :options="monthOptions"
                        :model-value="filters.month ?? ''"
                        @update:modelValue="applyFilter('month', $event)"
                    />
                    <FormSelect
                        :options="statusOptions"
                        :model-value="filters.status ?? ''"
                        @update:modelValue="applyFilter('status', $event)"
                    />
                </div>
            </div>
        </Card>

        <DataTable :columns="columns" :data="periods">
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
                    <Link
                        :href="route('schedules.show', row.id)"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-mistral-steel hover:text-mistral-ink hover:bg-mistral-cream-soft transition-colors"
                        :aria-label="t('common.view')"
                    >
                        <i class="fas fa-eye text-sm"></i>
                    </Link>
                    <button
                        v-if="row.status === 'draft'"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-mistral-steel hover:text-green-600 hover:bg-green-50 transition-colors"
                        :aria-label="t('shifts.publish_schedule')"
                        @click="confirmPublish(row)"
                    >
                        <i class="fas fa-check-circle text-sm"></i>
                    </button>
                    <button
                        v-if="row.status === 'published'"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-md text-mistral-steel hover:text-amber-600 hover:bg-amber-50 transition-colors"
                        :aria-label="t('shifts.regenerate_schedule')"
                        @click="confirmRegenerate(row)"
                    >
                        <i class="fas fa-sync-alt text-sm"></i>
                    </button>
                </div>
            </template>

            <template #footer>
                <Pagination :data="periods" />
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
