<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Card, Button, Badge, Alert, FormModal, FormSelect, FormInput, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();
const page = usePage();

const props = defineProps({
    year: { type: Number, required: true },
    types: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

// ------------------------------------------------------------------
// Filters (auto-apply)
// ------------------------------------------------------------------
const yearValue = ref(props.filters.year ?? props.year);
const departmentValue = ref(props.filters.department_id || '');
const searchValue = ref(props.filters.search || '');

let searchTimer = null;

function applyFilters() {
    router.get(route('vacations.balances.index'), {
        year: yearValue.value,
        department_id: departmentValue.value || undefined,
        search: searchValue.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true, only: ['employees', 'filters'] });
}

watch([yearValue, departmentValue], applyFilters);
watch(searchValue, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 400);
});

const departmentOptions = computed(() => [
    { value: '', label: t('vacations.all_departments') },
    ...props.departments.map(d => ({ value: d.id, label: d.name })),
]);

const yearOptions = computed(() => props.years.map(y => ({ value: y, label: String(y) })));

const typeOptions = computed(() => [
    { value: '', label: t('vacations.all_types') },
    ...props.types.map(type => ({ value: type.id, label: typeName(type) })),
]);

// ------------------------------------------------------------------
// Matrix helpers
// ------------------------------------------------------------------
function typeName(type) {
    return locale.value === 'en'
        ? (type.name_en || type.name_ar)
        : (type.name_ar || type.name_en);
}

function balanceOf(employee, typeId) {
    return (employee.balances && employee.balances[typeId]) || null;
}

function entitledOf(employee, typeId) {
    const balance = balanceOf(employee, typeId);
    return balance ? balance.days_entitled : null;
}

function remainingOf(employee, typeId) {
    const balance = balanceOf(employee, typeId);
    return balance ? balance.remaining : null;
}

function totals(employee) {
    let entitled = 0;
    let remaining = 0;

    for (const type of props.types) {
        entitled += entitledOf(employee, type.id) ?? type.default_days_per_year;
        remaining += remainingOf(employee, type.id) ?? 0;
    }

    return { entitled, remaining };
}

// ------------------------------------------------------------------
// Inline cell editing (absolute set)
// ------------------------------------------------------------------
const editing = ref(null);
const setForm = useForm({ user_id: null, vacation_type_id: null, year: props.year, days: 0, notes: '' });

function startEdit(employeeId, typeId, currentDays) {
    setForm.clearErrors();
    editing.value = { employeeId, typeId, draft: currentDays };
}

function commitEdit() {
    const cell = editing.value;
    if (!cell) return;

    // Close the cell immediately so a trailing blur cannot double-submit.
    editing.value = null;

    setForm.user_id = cell.employeeId;
    setForm.vacation_type_id = cell.typeId;
    setForm.year = yearValue.value;
    setForm.days = cell.draft;

    setForm.post(route('vacations.balances.set'), {
        preserveScroll: true,
        onError: () => { editing.value = cell; },
    });
}

function cancelEdit() {
    editing.value = null;
}

function isEditing(employeeId, typeId) {
    return editing.value && editing.value.employeeId === employeeId && editing.value.typeId === typeId;
}

// ------------------------------------------------------------------
// Bulk grant
// ------------------------------------------------------------------
const grantOpen = ref(false);
const grantForm = useForm({ year: props.year, vacation_type_id: '' });

function submitGrant() {
    grantForm.post(route('vacations.balances.grant-all'), {
        preserveScroll: true,
        onSuccess: () => {
            grantOpen.value = false;
            grantForm.reset();
        },
    });
}

// ------------------------------------------------------------------
// Export
// ------------------------------------------------------------------
function exportExcel() {
    const params = new URLSearchParams();
    params.set('year', yearValue.value);
    if (departmentValue.value) params.set('department_id', departmentValue.value);
    if (searchValue.value) params.set('search', searchValue.value);

    window.location.href = route('vacations.balances.export') + '?' + params.toString();
}
</script>

<template>
    <AppLayout :title="t('vacations.vacation_balances')">
        <PageHeader
            :title="t('vacations.balances_matrix_title')"
            :description="t('vacations.balances_matrix_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-file-excel" @click="exportExcel">
                    {{ t('vacations.export_excel') }}
                </Button>
                <Button variant="primary" icon="fas fa-wand-magic-sparkles" @click="grantOpen = true">
                    {{ t('vacations.grant_to_all') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="error" :message="flashError" class="mb-4" />

        <!-- Filters -->
        <Card variant="base" padding="sm" class="mb-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <FormSelect
                    v-model="yearValue"
                    :label="t('vacations.select_year')"
                    :options="yearOptions"
                />
                <FormSelect
                    v-model="departmentValue"
                    :label="t('vacations.department')"
                    :options="departmentOptions"
                />
                <FormInput
                    v-model="searchValue"
                    type="search"
                    :label="t('vacations.search_employees')"
                    :placeholder="t('vacations.search_employees')"
                />
            </div>
        </Card>

        <!-- Matrix -->
        <Card variant="base" padding="none">
            <div class="overflow-x-auto">
                <table class="w-full text-[13px] min-w-[720px]">
                    <thead>
                        <tr class="text-mistral-steel border-b border-mistral-hairline-soft bg-mistral-surface/50">
                            <th class="py-3 px-4 text-start sticky start-0 bg-mistral-surface/95 z-10">
                                {{ t('vacations.employee') }}
                            </th>
                            <th
                                v-for="type in types"
                                :key="type.id"
                                class="py-3 px-2 text-center align-bottom min-w-[110px]"
                            >
                                <div class="flex items-center justify-center gap-1.5">
                                    <span
                                        class="inline-block w-2 h-2 rounded-full shrink-0"
                                        :style="{ backgroundColor: type.color || 'var(--color-primary)' }"
                                    ></span>
                                    <span class="text-[12px] font-semibold text-mistral-ink leading-tight">
                                        {{ typeName(type) }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-mistral-stone font-normal mt-1">
                                    {{ t('vacations.default_value') }}: {{ type.default_days_per_year }}
                                </div>
                            </th>
                            <th class="py-3 px-3 text-center min-w-[100px]">
                                <span class="text-[12px] font-semibold text-mistral-ink">{{ t('vacations.total_entitled') }}</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="employee in employees"
                            :key="employee.id"
                            class="border-b border-mistral-hairline-soft hover:bg-mistral-primary/[0.03] transition-colors"
                        >
                            <td class="py-2.5 px-4 sticky start-0 bg-white z-10">
                                <div class="flex items-center gap-2.5">
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-semibold text-mistral-ink truncate">{{ employee.name }}</p>
                                        <p class="text-[11px] text-mistral-steel mt-0.5">
                                            {{ employee.employee_code || '—' }}
                                        </p>
                                        <Badge
                                            v-if="employee.department_name"
                                            :text="employee.department_name"
                                            variant="info"
                                            class="mt-1"
                                        />
                                    </div>
                                </div>
                            </td>

                            <td
                                v-for="type in types"
                                :key="type.id"
                                class="py-2.5 px-2 text-center"
                            >
                                <template v-if="isEditing(employee.id, type.id)">
                                    <input
                                        :key="'cell-' + employee.id + '-' + type.id"
                                        v-model.number="editing.draft"
                                        type="number"
                                        min="0"
                                        max="366"
                                        autofocus
                                        class="w-16 rounded-md border border-mistral-primary bg-white text-center text-[13px] px-1 py-1 outline-none shadow-sm"
                                        @keyup.enter="commitEdit"
                                        @keyup.esc="cancelEdit"
                                        @blur="commitEdit"
                                    />
                                </template>
                                <button
                                    v-else
                                    type="button"
                                    class="group inline-flex flex-col items-center justify-center rounded-lg px-2.5 py-1.5 hover:bg-mistral-primary/[0.06] hover:ring-1 hover:ring-mistral-primary/30 transition-all cursor-pointer min-w-[72px]"
                                    :title="t('vacations.click_to_edit')"
                                    @click="startEdit(employee.id, type.id, entitledOf(employee, type.id) ?? type.default_days_per_year)"
                                >
                                    <span
                                        class="text-[14px] font-bold tabular-nums group-hover:text-mistral-primary transition-colors"
                                        :class="balanceOf(employee, type.id) ? 'text-mistral-ink' : 'text-mistral-stone'"
                                    >
                                        {{ entitledOf(employee, type.id) ?? type.default_days_per_year }}
                                    </span>
                                    <span
                                        v-if="balanceOf(employee, type.id)"
                                        class="text-[10px] text-mistral-steel mt-0.5"
                                    >
                                        {{ t('vacations.remaining') }}: {{ remainingOf(employee, type.id) }}
                                    </span>
                                    <span
                                        v-else
                                        class="text-[10px] text-mistral-stone mt-0.5"
                                    >
                                        {{ t('vacations.default_value') }}
                                    </span>
                                </button>
                            </td>

                            <td class="py-2.5 px-3 text-center">
                                <div class="inline-flex flex-col items-center">
                                    <span class="text-[14px] font-bold text-mistral-primary tabular-nums">
                                        {{ totals(employee).entitled }}
                                    </span>
                                    <span class="text-[10px] text-mistral-steel">
                                        {{ t('vacations.remaining') }}: {{ totals(employee).remaining }}
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="employees.length === 0">
                            <td :colspan="types.length + 2" class="py-14 text-center">
                                <div class="flex flex-col items-center gap-2 text-mistral-stone">
                                    <i class="fas fa-scale-balanced text-[28px]"></i>
                                    <p class="text-[13px]">{{ t('vacations.no_employees_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Bulk grant modal -->
        <FormModal v-model="grantOpen" :title="t('vacations.grant_to_all')">
            <form @submit.prevent="submitGrant" class="space-y-4">
                <p class="text-[13px] text-mistral-steel leading-relaxed">
                    {{ t('vacations.grant_to_all_description') }}
                </p>
                <FormSelect
                    v-model="grantForm.year"
                    :label="t('vacations.granting_year')"
                    :options="yearOptions"
                    required
                />
                <FormSelect
                    v-model="grantForm.vacation_type_id"
                    :label="t('vacations.optional_type')"
                    :options="typeOptions"
                />
                <FormActions
                    :save-label="t('vacations.grant_to_all')"
                    :cancel-label="t('common.cancel')"
                    :saving="grantForm.processing"
                    @cancel="grantOpen = false"
                />
            </form>
        </FormModal>
    </AppLayout>
</template>
