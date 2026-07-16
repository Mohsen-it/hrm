<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    grades: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedGrade = ref(null);

const columns = computed(() => [
    { key: 'grade_code', label: t('grades.code'), sortable: true },
    { key: 'grade_name', label: t('grades.name'), sortable: true },
    { key: 'level', label: t('grades.level'), cellClass: 'text-center' },
    { key: 'company', label: t('grades.company') },
    { key: 'salary_range', label: t('grades.salary_range') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[160px]' },
]);

const companyOptions = computed(() => [
    { value: '', label: t('grades.select_company') },
    ...props.companies.map((c) => ({ value: c.id, label: c.company_name })),
]);

const levelValue = ref(props.filters?.level ?? '');

function onSearch(value) {
    router.get(
        route('grades.index'),
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
        route('grades.index'),
        next,
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(grade) {
    selectedGrade.value = grade;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedGrade.value) return;
    router.delete(route('grades.destroy', selectedGrade.value.id), {
        preserveScroll: true,
    });
}

function formatSalary(value) {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString();
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('grades.title')">
        <PageHeader
            :title="t('grades.title')"
            :description="t('grades.index_description')"
        >
            <template #actions>
                <Button variant="primary" icon="fas fa-plus" :href="route('grades.create')">
                    {{ t('grades.add_new') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <div class="card p-6 mb-4"">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <SearchInput
                        v-model="search"
                        :placeholder="t('common.search')"
                        @search="onSearch"
                    />
                    <FormSelect
                        :model-value="filters.company_id ?? ''"
                        :options="companyOptions"
                        class="max-w-[220px]"
                        @update:model-value="(v) => applyFilter('company_id', v)"
                    />
                    <FormInput
                        v-model="levelValue"
                        type="number"
                        :placeholder="t('grades.level')"
                        class="max-w-[140px]"
                        @change="applyFilter('level', levelValue)"
                    />
                    <FormSelect
                        :model-value="filters.status ?? ''"
                        :options="[
                            { value: '', label: t('common.all_statuses') },
                            { value: '1', label: t('common.active') },
                            { value: '0', label: t('common.inactive') },
                        ]"
                        class="max-w-[180px]"
                        @update:model-value="(v) => applyFilter('status', v)"
                    />
                </div>
            </div>
        </div>

        <DataTable :columns="columns" :data="grades">
            <template #cell-level="{ row }">
                <span>{{ row.level }}</span>
            </template>

            <template #cell-company="{ row }">
                <span>{{ row.company?.company_name || '—' }}</span>
            </template>

            <template #cell-salary_range="{ row }">
                <span>
                    {{ formatSalary(row.min_salary) }} - {{ formatSalary(row.max_salary) }}
                </span>
            </template>

            <template #cell-status="{ row }">
                <Badge v-if="row.status === 1" :text="t('common.active')" variant="active" />
                <Badge v-else :text="t('common.inactive')" variant="inactive" />
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('grades.show', row.id)" />
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('grades.edit', row.id)" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('grades.delete_confirm_title')"
            :message="t('grades.delete_confirm_message', { name: selectedGrade?.grade_name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
