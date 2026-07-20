<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormCheckbox, FormFileUpload, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    companies: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    subordinations: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    managers: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    attendanceGroups: { type: Array, default: () => [] },
});

const form = reactive({
    employee_code: '',
    name: '',
    first_name: '',
    last_name: '',
    full_name_ar: '',
    full_name_en: '',
    email: '',
    password: '',
    password_confirmation: '',
    national_id: '',
    phone: '',
    phone2: '',
    date_of_birth: '',
    gender: '',
    marital_status: '',
    nationality: '',
    hire_date: '',
    termination_date: '',
    employment_type: 'full_time',
    job_title: '',
    work_location: '',
    address: '',
    city: '',
    state: '',
    country: '',
    postal_code: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    emergency_contact_relation: '',
    bank_name: '',
    bank_account_number: '',
    iban: '',
    avatar: null,
    status: 1,
    is_active_employee: true,
    must_change_password: true,
    company_id: '',
    branch_id: '',
    department_id: '',
    position_id: '',
    grade_id: '',
    subordination_id: '',
    shift_id: '',
    manager_id: '',
    attendance_group_id: '',
    roles: [],
    permissions: [],
});

const errors = ref({});
const processing = ref(false);

const statusOptions = [
    { value: 1, label: t('common.active') },
    { value: 0, label: t('common.inactive') },
];

const genderOptions = [
    { value: 'male', label: t('users.gender_male') },
    { value: 'female', label: t('users.gender_female') },
];

const maritalOptions = [
    { value: 'single', label: t('users.marital_single') },
    { value: 'married', label: t('users.marital_married') },
    { value: 'divorced', label: t('users.marital_divorced') },
    { value: 'widowed', label: t('users.marital_widowed') },
];

const employmentOptions = [
    { value: 'full_time', label: t('users.employment_full_time') },
    { value: 'part_time', label: t('users.employment_part_time') },
    { value: 'contract', label: t('users.employment_contract') },
    { value: 'temporary', label: t('users.employment_temporary') },
    { value: 'intern', label: t('users.employment_intern') },
];

const errorFor = (key) => errors.value[key] || '';

const filteredBranches = computed(() => {
    if (!form.company_id) return props.branches;
    return props.branches.filter((b) => b.company_id === form.company_id);
});

const filteredDepartments = computed(() => {
    if (!form.branch_id) return props.departments;
    return props.departments.filter((d) => d.branch_id === form.branch_id);
});

watch(
    () => form.company_id,
    () => { form.branch_id = ''; form.department_id = ''; },
);

watch(
    () => form.branch_id,
    () => { form.department_id = ''; },
);

function submit() {
    processing.value = true;
    errors.value = {};
    const payload = { ...form };
    if (payload.roles.length === 0) delete payload.roles;
    if (payload.permissions.length === 0) delete payload.permissions;
    router.post(route('users.store'), payload, {
        forceFormData: true,
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('users.add_new')">
        <PageHeader
            :title="t('users.add_new')"
            :description="t('users.create_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('users.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <ErrorSummary :errors="errors" />

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Personal Information -->
            <FormSection
                :title="t('users.personal_info')"
                icon="fas fa-user"
                :collapsible="true"
                :default-open="true"
                :count="8"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.employee_code"
                        :label="t('users.employee_code')"
                        name="employee_code"
                        :error="errorFor('employee_code')"
                    />
                    <FormInput
                        v-model="form.name"
                        :label="t('users.name')"
                        name="name"
                        required
                        autofocus
                        :error="errorFor('name')"
                    />
                    <FormInput
                        v-model="form.email"
                        :label="t('users.email')"
                        name="email"
                        type="email"
                        required
                        :error="errorFor('email')"
                    />
                    <FormInput
                        v-model="form.password"
                        :label="t('users.password')"
                        name="password"
                        type="password"
                        required
                        :error="errorFor('password')"
                    />
                    <FormInput
                        v-model="form.password_confirmation"
                        :label="t('users.password_confirmation')"
                        name="password_confirmation"
                        type="password"
                    />
                    <FormInput
                        v-model="form.national_id"
                        :label="t('users.national_id')"
                        name="national_id"
                        :error="errorFor('national_id')"
                    />
                    <FormInput
                        v-model="form.first_name"
                        :label="t('users.first_name')"
                        name="first_name"
                        :error="errorFor('first_name')"
                    />
                    <FormInput
                        v-model="form.last_name"
                        :label="t('users.last_name')"
                        name="last_name"
                        :error="errorFor('last_name')"
                    />
                    <FormInput
                        v-model="form.full_name_ar"
                        :label="t('users.full_name_ar')"
                        name="full_name_ar"
                        :error="errorFor('full_name_ar')"
                    />
                    <FormInput
                        v-model="form.full_name_en"
                        :label="t('users.full_name_en')"
                        name="full_name_en"
                        :error="errorFor('full_name_en')"
                    />
                    <FormInput
                        v-model="form.phone"
                        :label="t('users.phone')"
                        name="phone"
                        :error="errorFor('phone')"
                    />
                    <FormInput
                        v-model="form.phone2"
                        :label="t('users.phone2')"
                        name="phone2"
                        :error="errorFor('phone2')"
                    />
                    <FormInput
                        v-model="form.date_of_birth"
                        :label="t('users.date_of_birth')"
                        name="date_of_birth"
                        type="date"
                        :error="errorFor('date_of_birth')"
                    />
                    <FormSelect
                        v-model="form.gender"
                        :label="t('users.gender')"
                        name="gender"
                        :options="genderOptions"
                        :placeholder="t('users.select_gender')"
                        :error="errorFor('gender')"
                    />
                    <FormSelect
                        v-model="form.marital_status"
                        :label="t('users.marital_status')"
                        name="marital_status"
                        :options="maritalOptions"
                        :placeholder="t('users.select_marital_status')"
                        :error="errorFor('marital_status')"
                    />
                    <FormInput
                        v-model="form.nationality"
                        :label="t('users.nationality')"
                        name="nationality"
                        :error="errorFor('nationality')"
                    />
                </div>
            </FormSection>

            <!-- Employment Information -->
            <FormSection
                :title="t('users.employment_info')"
                icon="fas fa-briefcase"
                :collapsible="true"
                :default-open="true"
                :count="6"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.hire_date"
                        :label="t('users.hire_date')"
                        name="hire_date"
                        type="date"
                        :error="errorFor('hire_date')"
                    />
                    <FormInput
                        v-model="form.termination_date"
                        :label="t('users.termination_date')"
                        name="termination_date"
                        type="date"
                        :error="errorFor('termination_date')"
                    />
                    <FormSelect
                        v-model="form.employment_type"
                        :label="t('users.employment_type')"
                        name="employment_type"
                        :options="employmentOptions"
                        :placeholder="t('users.select_employment_type')"
                        :error="errorFor('employment_type')"
                    />
                    <FormInput
                        v-model="form.job_title"
                        :label="t('users.job_title')"
                        name="job_title"
                        :error="errorFor('job_title')"
                    />
                    <FormInput
                        v-model="form.work_location"
                        :label="t('users.work_location')"
                        name="work_location"
                        :error="errorFor('work_location')"
                    />
                    <FormSelect
                        v-model="form.status"
                        :label="t('common.status')"
                        name="status"
                        :options="statusOptions"
                        required
                        :error="errorFor('status')"
                    />
                </div>
            </FormSection>

            <!-- Organizational Information -->
            <FormSection
                :title="t('users.organizational_info')"
                icon="fas fa-sitemap"
                :collapsible="true"
                :default-open="true"
                :count="9"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormSelect
                        v-model="form.company_id"
                        :label="t('users.company')"
                        name="company_id"
                        :options="companies.map((c) => ({ value: c.id, label: c.company_name }))"
                        :placeholder="t('users.select_company')"
                        :error="errorFor('company_id')"
                    />
                    <FormSelect
                        v-model="form.branch_id"
                        :label="t('users.branch')"
                        name="branch_id"
                        :options="filteredBranches.map((b) => ({ value: b.id, label: b.branch_name }))"
                        :placeholder="t('users.select_branch')"
                        :error="errorFor('branch_id')"
                    />
                    <FormSelect
                        v-model="form.department_id"
                        :label="t('users.department')"
                        name="department_id"
                        :options="filteredDepartments.map((d) => ({ value: d.id, label: d.department_name }))"
                        :placeholder="t('users.select_department')"
                        :error="errorFor('department_id')"
                    />
                    <FormSelect
                        v-model="form.position_id"
                        :label="t('users.position')"
                        name="position_id"
                        :options="positions.map((p) => ({ value: p.id, label: p.position_name }))"
                        :placeholder="t('users.select_position')"
                        :error="errorFor('position_id')"
                    />
                    <FormSelect
                        v-model="form.grade_id"
                        :label="t('users.grade')"
                        name="grade_id"
                        :options="grades.map((g) => ({ value: g.id, label: g.grade_name }))"
                        :placeholder="t('users.select_grade')"
                        :error="errorFor('grade_id')"
                    />
                    <FormSelect
                        v-model="form.subordination_id"
                        :label="t('users.subordination')"
                        name="subordination_id"
                        :options="subordinations.map((s) => ({ value: s.id, label: s.display_name }))"
                        :placeholder="t('users.select_subordination')"
                        :error="errorFor('subordination_id')"
                    />
                    <FormSelect
                        v-model="form.shift_id"
                        :label="t('users.shift')"
                        name="shift_id"
                        :options="shifts.map((s) => ({ value: s.id, label: s.shift_name }))"
                        :placeholder="t('users.select_shift')"
                        :error="errorFor('shift_id')"
                    />
                    <FormSelect
                        v-model="form.manager_id"
                        :label="t('users.manager')"
                        name="manager_id"
                        :options="managers.map((m) => ({ value: m.id, label: m.name }))"
                        :placeholder="t('users.select_manager')"
                        :error="errorFor('manager_id')"
                    />
                    <FormSelect
                        v-model="form.attendance_group_id"
                        :label="t('attendance.attendance_group')"
                        name="attendance_group_id"
                        :options="attendanceGroups.map((g) => ({ value: g.id, label: g.name }))"
                        :placeholder="t('attendance.select_attendance_group')"
                        :error="errorFor('attendance_group_id')"
                    />
                </div>
            </FormSection>

            <!-- Contact Information -->
            <FormSection
                :title="t('users.contact_info')"
                icon="fas fa-location-dot"
                :collapsible="true"
                :default-open="true"
                :count="5"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.address"
                        :label="t('users.address')"
                        name="address"
                        :error="errorFor('address')"
                    />
                    <FormInput
                        v-model="form.city"
                        :label="t('users.city')"
                        name="city"
                        :error="errorFor('city')"
                    />
                    <FormInput
                        v-model="form.state"
                        :label="t('users.state')"
                        name="state"
                        :error="errorFor('state')"
                    />
                    <FormInput
                        v-model="form.country"
                        :label="t('users.country')"
                        name="country"
                        :error="errorFor('country')"
                    />
                    <FormInput
                        v-model="form.postal_code"
                        :label="t('users.postal_code')"
                        name="postal_code"
                        :error="errorFor('postal_code')"
                    />
                </div>
            </FormSection>

            <!-- Emergency Contact -->
            <FormSection
                :title="t('users.emergency_info')"
                icon="fas fa-phone-volume"
                :collapsible="true"
                :default-open="true"
                :count="3"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.emergency_contact_name"
                        :label="t('users.emergency_contact_name')"
                        name="emergency_contact_name"
                        :error="errorFor('emergency_contact_name')"
                    />
                    <FormInput
                        v-model="form.emergency_contact_phone"
                        :label="t('users.emergency_contact_phone')"
                        name="emergency_contact_phone"
                        :error="errorFor('emergency_contact_phone')"
                    />
                    <FormInput
                        v-model="form.emergency_contact_relation"
                        :label="t('users.emergency_contact_relation')"
                        name="emergency_contact_relation"
                        :error="errorFor('emergency_contact_relation')"
                    />
                </div>
            </FormSection>

            <!-- Banking Information -->
            <FormSection
                :title="t('users.banking_info')"
                icon="fas fa-landmark"
                :collapsible="true"
                :default-open="true"
                :count="3"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.bank_name"
                        :label="t('users.bank_name')"
                        name="bank_name"
                        :error="errorFor('bank_name')"
                    />
                    <FormInput
                        v-model="form.bank_account_number"
                        :label="t('users.bank_account_number')"
                        name="bank_account_number"
                        :error="errorFor('bank_account_number')"
                    />
                    <FormInput
                        v-model="form.iban"
                        :label="t('users.iban')"
                        name="iban"
                        :error="errorFor('iban')"
                    />
                </div>
            </FormSection>

            <!-- Avatar & Flags -->
            <FormSection
                :title="t('users.avatar')"
                icon="fas fa-image"
                :collapsible="true"
                :default-open="false"
            >
                <div class="space-y-4">
                    <FormFileUpload
                        v-model="form.avatar"
                        :label="t('users.avatar')"
                        accept="image/*"
                        name="avatar"
                        :error="errorFor('avatar')"
                    />
                    <div class="flex items-center gap-6">
                        <FormCheckbox v-model="form.is_active_employee" :label="t('users.is_active_employee')" />
                        <FormCheckbox v-model="form.must_change_password" :label="t('users.must_change_password')" />
                    </div>
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('users.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
