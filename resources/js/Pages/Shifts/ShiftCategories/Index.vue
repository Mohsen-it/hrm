<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, DataTable, ConfirmDialog, Badge, IconButton, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    categories: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
});

const showDelete = ref(false);
const selectedCategory = ref(null);

const typeVariant = (type) => {
    const map = { cyclic: 'info', weekly: 'active', hours: 'pending' };
    return map[type] || 'inactive';
};

const typeLabel = (type) => {
    const map = {
        cyclic: t('shifts.cyclic'),
        weekly: t('shifts.weekly'),
        hours: t('shifts.hours'),
    };
    return map[type] || type;
};

const dayLabels = computed(() => [
    { value: 0, label: t('shifts.sunday') },
    { value: 1, label: t('shifts.monday') },
    { value: 2, label: t('shifts.tuesday') },
    { value: 3, label: t('shifts.wednesday') },
    { value: 4, label: t('shifts.thursday') },
    { value: 5, label: t('shifts.friday') },
    { value: 6, label: t('shifts.saturday') },
]);

const periodLabels = computed(() => ({
    daily: t('shifts.daily'),
    weekly: t('shifts.weekly_label'),
    monthly: t('shifts.monthly'),
}));

const typeFilterOptions = computed(() => [
    { value: '', label: t('shifts.category_type') },
    ...props.types,
]);

function formatWorkPattern(category) {
    if (category.type === 'cyclic') {
        return `${category.work_days || 0}+${category.rest_days || 0}`;
    }
    if (category.type === 'weekly') {
        const days = category.work_days_json;
        if (!Array.isArray(days) && typeof days === 'object' && days !== null) {
            const dayKeys = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const activeDays = dayKeys
                .map((key, idx) => days[key] ? idx : null)
                .filter(v => v !== null);
            if (activeDays.length === 0) return '—';
            return activeDays
                .map((d) => {
                    const found = dayLabels.value.find((l) => l.value === Number(d));
                    return found ? found.label : null;
                })
                .filter(Boolean)
                .join(' - ');
        }
        if (!Array.isArray(days) || days.length === 0) return '—';
        const sorted = [...days].sort((a, b) => a - b);
        return sorted
            .map((d) => {
                const found = dayLabels.value.find((l) => l.value === Number(d));
                return found ? found.label : null;
            })
            .filter(Boolean)
            .join(' - ');
    }
    if (category.type === 'hours') {
        const hours = category.required_hours || 0;
        const period = periodLabels.value[category.period_type] || category.period_type || '';
        return `${hours} / ${period}`;
    }
    return '—';
}

const columns = computed(() => [
    { key: 'name', label: t('shifts.category_name'), sortable: true },
    { key: 'type', label: t('shifts.category_type'), cellClass: 'text-center', filterable: true, filterType: 'select', filterOptions: typeFilterOptions.value },
    { key: 'work_pattern', label: t('shifts.work_days'), cellClass: 'text-center' },
    { key: 'schedule', label: t('shifts.schedule_name') },
    { key: 'employees_count', label: t('shifts.employees_count'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[240px]' },
]);

function onSearch(value) {
    router.get(
        route('shift-categories.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
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
    router.get(route('shift-categories.index'), next, { preserveState: true, preserveScroll: true, replace: true });
}

function onPageChange(page) {
    router.get(
        route('shift-categories.index'),
        { ...props.filters, page },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onPerPageChange(perPage) {
    router.get(
        route('shift-categories.index'),
        { ...props.filters, per_page: perPage },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(category) {
    selectedCategory.value = category;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedCategory.value) return;
    router.delete(route('shift-categories.destroy', selectedCategory.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('shifts.shift_categories')">
        <PageHeader
            :title="t('shifts.shift_categories')"
            :description="t('shifts.index_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('time-schedules.index')" icon="fas fa-clock">
                    {{ t('shifts.time_schedules') }}
                </Button>
                <Button variant="secondary" :href="route('shift-assignments.index')" icon="fas fa-user-check">
                    {{ t('shifts.shift_assignments') }}
                </Button>
                <Button variant="primary" :href="route('shift-categories.create')" icon="fas fa-plus">
                    {{ t('shifts.add_category') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="categories"
            storage-key="shift-categories"
            @search="onSearch"
            @filter-change="onFilterChange"
            @page-change="onPageChange"
            @per-page-change="onPerPageChange"
        >
            <template #cell-type="{ row }">
                <Badge :text="typeLabel(row.type)" :variant="typeVariant(row.type)" />
            </template>

            <template #cell-work_pattern="{ row }">
                <span class="text-[13px]">{{ formatWorkPattern(row) }}</span>
            </template>

            <template #cell-schedule="{ row }">
                <Link
                    v-if="row.time_schedule?.id"
                    :href="route('time-schedules.show', row.time_schedule.id)"
                    class="text-mistral-primary hover:underline"
                >
                    {{ row.time_schedule.name }}
                </Link>
                <span v-else class="text-mistral-muted">—</span>
            </template>

            <template #cell-employees_count="{ row }">
                <Link
                    :href="route('shift-assignments.index', { category_id: row.id })"
                    class="text-mistral-primary hover:underline font-medium"
                >
                    {{ row.active_employees_count || 0 }}
                </Link>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton
                        icon="fas fa-user-plus"
                        variant="ghost"
                        :aria-label="t('shifts.assign_employee')"
                        :href="route('shift-assignments.assign', { category: row.id })"
                    />
                    <IconButton
                        icon="fas fa-users"
                        variant="ghost"
                        :aria-label="t('shifts.bulk_assign')"
                        :href="route('shift-assignments.bulk-assign', { category: row.id })"
                    />
                    <IconButton
                        icon="fas fa-eye"
                        variant="ghost"
                        :aria-label="t('common.view')"
                        :href="route('shift-categories.show', row.id)"
                    />
                    <IconButton
                        icon="fas fa-edit"
                        variant="ghost"
                        :aria-label="t('common.edit')"
                        :href="route('shift-categories.edit', row.id)"
                    />
                    <IconButton
                        icon="fas fa-trash"
                        variant="danger"
                        :aria-label="t('common.delete')"
                        @click="confirmDelete(row)"
                    />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('shifts.delete_confirm_title')"
            :message="t('shifts.delete_confirm_message', { name: selectedCategory?.name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
