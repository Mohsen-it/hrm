<script setup>
import { ref, computed, reactive } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormDatepicker from '@/Components/ui/FormDatepicker.vue';
import Badge from '@/Components/ui/Badge.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import FormModal from '@/Components/ui/FormModal.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Alert from '@/Components/ui/Alert.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    assignments: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Object, default: () => ({ data: [] }) },
    departments: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showUnassign = ref(false);
const selectedAssignment = ref(null);

const showTransfer = ref(false);
const transferForm = reactive({
    employee_id: null,
    new_category_id: '',
    effective_date: new Date().toISOString().slice(0, 10),
});
const transferErrors = reactive({});
const transferProcessing = ref(false);

const categoryOptions = computed(() => {
    const cats = props.categories?.data || props.categories || [];
    return cats.map((c) => ({ value: c.id, label: c.name }));
});

const departmentOptions = computed(() =>
    (props.departments || []).map((d) => ({ value: d.id, label: d.department_name })),
);

const statusOptions = [
    { value: '', label: t('shifts.status') },
    { value: 'active', label: t('shifts.active') },
    { value: 'closed', label: t('shifts.closed') },
];

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

const columns = computed(() => [
    { key: 'employee', label: t('shifts.employee') },
    { key: 'category', label: t('shifts.category'), cellClass: 'text-center' },
    { key: 'start_date', label: t('shifts.start_date'), cellClass: 'text-center' },
    { key: 'end_date', label: t('shifts.end_date'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[140px]' },
]);

function onSearch(value) {
    router.get(
        route('shift-assignments.index'),
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
        route('shift-assignments.index'),
        next,
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmUnassign(assignment) {
    selectedAssignment.value = assignment;
    showUnassign.value = true;
}

function performUnassign() {
    if (!selectedAssignment.value) return;
    router.post(
        route('shift-assignments.unassign'),
        { employee_id: selectedAssignment.value.employee?.id },
        { preserveScroll: true },
    );
}

function openTransfer(assignment) {
    selectedAssignment.value = assignment;
    transferForm.employee_id = assignment.employee?.id;
    transferForm.new_category_id = '';
    transferForm.effective_date = new Date().toISOString().slice(0, 10);
    Object.keys(transferErrors).forEach((k) => delete transferErrors[k]);
    showTransfer.value = true;
}

function performTransfer() {
    if (!transferForm.employee_id || !transferForm.new_category_id || !transferForm.effective_date) return;
    transferProcessing.value = true;
    Object.keys(transferErrors).forEach((k) => delete transferErrors[k]);

    router.post(
        route('shift-assignments.transfer'),
        {
            employee_id: transferForm.employee_id,
            new_category_id: transferForm.new_category_id,
            effective_date: transferForm.effective_date,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showTransfer.value = false;
            },
            onError: (err) => {
                Object.assign(transferErrors, err || {});
            },
            onFinish: () => {
                transferProcessing.value = false;
            },
        },
    );
}

const transferEmployeeName = computed(() => {
    if (!selectedAssignment.value?.employee) return '';
    const emp = selectedAssignment.value.employee;
    return `${emp.first_name || ''} ${emp.last_name || ''}`.trim() || emp.name || '';
});

const transferCurrentCategory = computed(() => {
    return selectedAssignment.value?.category?.name || '';
});

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('shifts.shift_assignments')">
        <PageHeader
            :title="t('shifts.shift_assignments')"
            :description="t('shifts.assignments_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('shift-categories.index')" icon="fas fa-layer-group">
                    {{ t('shifts.shift_categories') }}
                </Button>
                <Button variant="primary" :href="route('shift-assignments.assign')" icon="fas fa-user-plus">
                    {{ t('shifts.assign_employee') }}
                </Button>
                <Button variant="secondary" :href="route('shift-assignments.bulk-assign')" icon="fas fa-users">
                    {{ t('shifts.bulk_assign') }}
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
                :href="route('shifts.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.title') }}
            </Link>
            <Link
                :href="route('shift-assignments.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-primary border-mistral-primary"
                role="tab"
                aria-selected="true"
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
                        :options="categoryOptions"
                        :model-value="filters.category_id ?? ''"
                        :placeholder="t('shifts.shift_category')"
                        @update:modelValue="applyFilter('category_id', $event)"
                    />
                    <FormSelect
                        :options="departmentOptions"
                        :model-value="filters.department_id ?? ''"
                        :placeholder="t('shifts.department')"
                        @update:modelValue="applyFilter('department_id', $event)"
                    />
                    <FormSelect
                        :options="statusOptions"
                        :model-value="filters.status ?? ''"
                        :placeholder="t('shifts.status')"
                        @update:modelValue="applyFilter('status', $event)"
                    />
                </div>
            </div>
        </Card>

        <DataTable :columns="columns" :data="assignments">
            <template #cell-employee="{ row }">
                <div class="flex flex-col">
                    <span class="text-[14px] font-medium text-mistral-ink">
                        {{ row.employee?.first_name }} {{ row.employee?.last_name }}
                    </span>
                    <span class="text-[12px] text-mistral-muted">
                        {{ row.employee?.emp_code }}
                    </span>
                </div>
            </template>

            <template #cell-category="{ row }">
                <div class="flex flex-col items-center gap-1">
                    <Link
                        v-if="row.category?.id"
                        :href="route('shift-categories.show', row.category.id)"
                        class="text-mistral-primary hover:underline text-[14px]"
                    >
                        {{ row.category?.name || '—' }}
                    </Link>
                    <span v-else>—</span>
                    <Badge
                        v-if="row.category?.type"
                        :text="typeLabel(row.category?.type)"
                        :variant="typeVariant(row.category?.type)"
                    />
                </div>
            </template>

            <template #cell-start_date="{ row }">
                <span>{{ row.start_date || '—' }}</span>
            </template>

            <template #cell-end_date="{ row }">
                <Badge v-if="row.is_active" :text="t('shifts.active')" variant="active" />
                <span v-else>{{ row.end_date || '—' }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton
                        v-if="row.is_active"
                        icon="fas fa-exchange-alt"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('shifts.transfer')"
                        @click="openTransfer(row)"
                    />
                    <IconButton
                        v-if="row.is_active"
                        icon="fas fa-user-slash"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('shifts.unassign')"
                        @click="confirmUnassign(row)"
                    />
                </div>
            </template>

            <template #footer>
                <Pagination :data="assignments" />
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showUnassign"
            :title="t('shifts.unassign_confirm_title')"
            :message="t('shifts.unassign_confirm_message')"
            :confirm-text="t('common.confirm')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performUnassign"
        />

        <FormModal
            v-model="showTransfer"
            :title="t('shifts.transfer_title')"
            size="md"
        >
            <div class="space-y-4">
                <Alert type="info" :message="t('shifts.transfer_message')" />

                <div class="grid grid-cols-1 gap-3">
                    <div>
                        <label class="block text-[13px] text-mistral-slate mb-1">
                            {{ t('shifts.employee_full_name') }}
                        </label>
                        <div class="text-[14px] font-medium text-mistral-ink p-2 bg-mistral-surface rounded-md">
                            {{ transferEmployeeName }}
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] text-mistral-slate mb-1">
                            {{ t('shifts.current_category') }}
                        </label>
                        <div class="text-[14px] text-mistral-ink p-2 bg-mistral-surface rounded-md">
                            {{ transferCurrentCategory }}
                        </div>
                    </div>
                    <FormSelect
                        v-model="transferForm.new_category_id"
                        :label="t('shifts.new_category')"
                        :options="categoryOptions"
                        :placeholder="t('shifts.select_category')"
                        :error="transferErrors?.new_category_id"
                        required
                    />
                    <FormDatepicker
                        v-model="transferForm.effective_date"
                        :label="t('shifts.effective_date')"
                        :error="transferErrors?.effective_date"
                        required
                    />
                </div>
            </div>

            <template #footer>
                <Button variant="secondary" @click="showTransfer = false">{{ t('common.cancel') }}</Button>
                <Button
                    variant="primary"
                    :loading="transferProcessing"
                    :disabled="!transferForm.new_category_id || !transferForm.effective_date"
                    icon="fas fa-exchange-alt"
                    @click="performTransfer"
                >
                    {{ t('shifts.transfer') }}
                </Button>
            </template>
        </FormModal>
    </AppLayout>
</template>
