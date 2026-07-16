<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Alert from '@/Components/ui/Alert.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    categories: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
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

const typeOptions = computed(() => [
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
    { key: 'type', label: t('shifts.category_type'), cellClass: 'text-center' },
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

function applyFilter(key, value) {
    const next = { ...props.filters };
    if (value === '' || value === null || value === undefined) {
        delete next[key];
    } else {
        next[key] = value;
    }
    router.get(
        route('shift-categories.index'),
        next,
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

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" dismissible class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" dismissible class="mb-4" />

        <nav class="flex items-center gap-0 border-b border-mistral-hairline-soft overflow-x-auto mb-6" role="tablist">
            <Link
                :href="route('users.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('users.title') }}
            </Link>
            <Link
                :href="route('shift-categories.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-primary border-mistral-primary"
                role="tab"
                aria-selected="true"
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
                    <SearchInput
                        v-model="search"
                        :placeholder="t('common.search')"
                        @search="onSearch"
                    />
                    <FormSelect
                        :options="typeOptions"
                        :model-value="filters.type ?? ''"
                        @update:modelValue="applyFilter('type', $event)"
                    />
                </div>
            </div>
        </Card>

        <DataTable :columns="columns" :data="categories">
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
                        size="sm"
                        :aria-label="t('shifts.assign_employee')"
                        :href="route('shift-assignments.assign', { category: row.id })"
                    />
                    <IconButton
                        icon="fas fa-users"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('shifts.bulk_assign')"
                        :href="route('shift-assignments.bulk-assign', { category: row.id })"
                    />
                    <IconButton
                        icon="fas fa-eye"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.view')"
                        :href="route('shift-categories.show', row.id)"
                    />
                    <IconButton
                        icon="fas fa-edit"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.edit')"
                        :href="route('shift-categories.edit', row.id)"
                    />
                    <IconButton
                        icon="fas fa-trash"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.delete')"
                        @click="confirmDelete(row)"
                    />
                </div>
            </template>

            <template #footer>
                <Pagination :data="categories" />
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
