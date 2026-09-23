<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { computed, nextTick, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormCheckbox, FormFileUpload, FormSection, FormActions, ErrorSummary, Alert, Tabs } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

// Speedy entry: remember the operator's last picks (company/branch/...)
// so the next create opens pre-filled. Gender falls back to male.
const QUICK_DEFAULTS_KEY = 'hrm-user-quick-defaults';

function loadQuickDefaults() {
    try {
        const raw = localStorage.getItem(QUICK_DEFAULTS_KEY);
        return raw ? JSON.parse(raw) : {};
    } catch {
        return {};
    }
}

function saveQuickDefaults() {
    try {
        localStorage.setItem(QUICK_DEFAULTS_KEY, JSON.stringify({
            gender: form.gender,
            company_id: form.company_id,
            branch_id: form.branch_id,
            department_id: form.department_id,
            position_id: form.position_id,
            grade_id: form.grade_id,
            subordination_id: form.subordination_id,
            rotation_id: form.rotation_assignment.rotation_id,
            rotation_group_id: form.rotation_assignment.rotation_group_id,
        }));
    } catch {
        // Storage unavailable (private mode) — creation still works.
    }
}

// Form mode: quick (essential fields) vs full. Same toggle exists in Edit.vue.
const mode = ref('quick');
const modeTabs = computed(() => [
    { value: 'quick', label: t('users.form_mode_quick') },
    { value: 'full', label: t('users.form_mode_full') },
]);

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
    rotations: { type: Array, default: () => [] },
});

const form = useForm({
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
    attendance_exemption_type: '',
    attendance_exemption_from: '',
    attendance_exemption_to: '',
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
    device_privilege: '',
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
    rotation_assignment: {
        action: '',
        rotation_id: '',
        rotation_group_id: '',
        start_date: new Date().toISOString().slice(0, 10),
        end_date: '',
    },
});

const statusOptions = [
    { value: 1, label: t('common.active') },
    { value: 0, label: t('common.inactive') },
];

const devicePrivilegeOptions = [
    { value: '', label: t('users.device_privilege_auto') },
    { value: 0, label: t('users.device_privilege_member') },
    { value: 14, label: t('users.device_privilege_admin') },
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

const positionOptions = computed(() => [
    { value: '', label: t('users.no_position') },
    ...props.positions.map((p) => ({ value: p.id, label: p.position_name })),
]);

const attendanceExemptionOptions = [
    { value: '', label: t('users.select_attendance_exemption') },
    { value: 'resignation', label: t('users.attendance_exemption_resignation') },
    { value: 'external_transfer', label: t('users.attendance_exemption_external_transfer') },
    { value: 'suspension', label: t('users.attendance_exemption_suspension') },
    { value: 'retirement', label: t('users.attendance_exemption_retirement') },
];

const filteredBranches = computed(() => {
    if (!form.company_id) return props.branches;
    return props.branches.filter((branch) => String(branch.company_id) === String(form.company_id));
});

const filteredDepartments = computed(() => {
    if (!form.branch_id) return props.departments;
    return props.departments.filter((department) => String(department.branch_id) === String(form.branch_id));
});

const availableRotationGroups = computed(() => {
    const selectedId = String(form.rotation_assignment.rotation_id);
    if (!selectedId) return [];
    const rotation = (props.rotations || []).find((r) => r && String(r.id) === selectedId);
    return Array.isArray(rotation?.groups) ? rotation.groups : [];
});

const rotationOptions = computed(() => [
    { value: '', label: t('users.select_rotation') },
    ...(props.rotations || []).map((r) => ({
        value: String(r.id),
        label: r.name || 'Unnamed Rotation',
    })),
]);

const rotationGroupOptions = computed(() => [
    { value: '', label: t('users.select_rotation_group') },
    ...(availableRotationGroups.value || []).map((g) => ({
        value: String(g.id),
        label: g.name || 'Unnamed Group',
    })),
]);

watch(
    () => form.company_id,
    () => { form.branch_id = ''; form.department_id = ''; },
);

watch(
    () => form.branch_id,
    () => { form.department_id = ''; },
);

watch(
    () => form.rotation_assignment.rotation_id,
    () => { form.rotation_assignment.rotation_group_id = ''; },
);

// Apply instant defaults. Priority: remembered last picks → my own org
// scope → empty. In Vue the selection IS the v-model value (equivalent of
// the native `selected` attribute), so prefilling here = preselected UI.
// Cascading selects need a tick between levels because the company/branch
// watchers reset their children on change.
{
    const saved = loadQuickDefaults();
    const page = usePage();
    const me = page.props.auth?.user || {};
    const idIn = (list, id) => (list || []).some((o) => String(o.id ?? o.value) === String(id));
    // Remembered → mine → the only option (when a list has a single choice
    // it is deterministic, like the static gender default) → empty.
    const pick = (savedId, myId, list) => {
        if (savedId && idIn(list, savedId)) return savedId;
        if (myId && idIn(list, myId)) return myId;
        if ((list || []).length === 1) return list[0].id ?? list[0].value;
        return '';
    };

    form.gender = saved.gender || 'male';

    const defaultCompany = pick(saved.company_id, me.company_id, props.companies);
    if (defaultCompany) {
        form.company_id = defaultCompany;
        nextTick(() => {
            const defaultBranch = pick(saved.branch_id, me.branch_id, filteredBranches.value);
            if (defaultBranch) {
                form.branch_id = defaultBranch;
                nextTick(() => {
                    const defaultDept = pick(saved.department_id, me.department_id, filteredDepartments.value);
                    if (defaultDept) {
                        form.department_id = defaultDept;
                    }
                });
            }
        });
    }
    if (saved.position_id && idIn(props.positions, saved.position_id)) {
        form.position_id = saved.position_id;
    }
    if (saved.grade_id && idIn(props.grades, saved.grade_id)) {
        form.grade_id = saved.grade_id;
    }
    if (saved.subordination_id && idIn(props.subordinations, saved.subordination_id)) {
        form.subordination_id = saved.subordination_id;
    }
    if (saved.rotation_id && idIn(props.rotations, saved.rotation_id)) {
        form.rotation_assignment.rotation_id = String(saved.rotation_id);
        nextTick(() => {
            if (saved.rotation_group_id && idIn(availableRotationGroups.value, saved.rotation_group_id)) {
                form.rotation_assignment.rotation_group_id = String(saved.rotation_group_id);
            }
        });
    }
}

watch(
    () => form.employee_code,
    (employeeCode) => {
        const normalizedCode = String(employeeCode ?? '').trim().toLowerCase();
        form.email = normalizedCode ? `${normalizedCode}@hrm.local` : '';
    },
);

function submit() {
    saveQuickDefaults();
    form.transform((data) => {
        const payload = { ...data };
        if (payload.device_privilege === '' || payload.device_privilege === null) {
            payload.device_privilege = null;
        } else {
            payload.device_privilege = Number(payload.device_privilege);
        }
        // New users can only be assigned (no transfer/unassign yet): infer the
        // action from the picked rotation, otherwise drop the block entirely.
        const ra = payload.rotation_assignment || {};
        if (ra.rotation_id && ra.rotation_group_id && ra.start_date) {
            ra.action = 'assign';
        } else {
            delete payload.rotation_assignment;
        }
        return payload;
    }).post(route('users.store'), {
        preserveScroll: true,
    });
}


usePageTitle(t('users.add_new'));
</script>

<template>
    
        <PageHeader
            :title="t('users.add_new')"
            :description="t('users.create_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('users.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <ErrorSummary :errors="form.errors" />

        <Alert type="info" :message="t('users.required_note')" class="mb-4" />

        <Tabs :tabs="modeTabs" v-model="mode" variant="pill" class="mb-4" />

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Quick create: essential fields only -->
            <FormSection
                v-if="mode === 'quick'"
                :title="t('users.quick_section_title')"
                :description="t('users.quick_section_description')"
                icon="fas fa-bolt"
                :collapsible="false"
                :default-open="true"
                :count="16"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.employee_code"
                        :label="t('users.employee_code')"
                        name="employee_code"
                        required
                        autofocus
                        autocomplete="off"
                        :error="form.errors.employee_code"
                    />
                    <FormInput
                        v-model="form.name"
                        :label="t('users.name')"
                        name="name"
                        required
                        autocomplete="off"
                        :error="form.errors.name"
                    />
                    <FormInput
                        v-model="form.email"
                        :label="t('users.email')"
                        name="email"
                        type="email"
                        readonly
                        :hint="t('users.email_generated_from_employee_code')"
                        :error="form.errors.email"
                    />
                    <FormInput
                        v-model="form.password"
                        :label="t('users.password')"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        :hint="t('users.password_auto_hint')"
                        :error="form.errors.password"
                    />
                    <FormInput
                        v-model="form.phone"
                        :label="t('users.phone')"
                        name="phone"
                        :error="form.errors.phone"
                    />
                    <FormSelect
                        v-model="form.gender"
                        :label="t('users.gender')"
                        name="gender"
                        :options="genderOptions"
                        :placeholder="t('users.select_gender')"
                        :error="form.errors.gender"
                    />
                    <FormSelect
                        v-model="form.company_id"
                        :label="t('users.company')"
                        name="company_id"
                        :options="companies.map((c) => ({ value: c.id, label: c.company_name }))"
                        :placeholder="t('users.select_company')"
                        :error="form.errors.company_id"
                    />
                    <FormSelect
                        v-model="form.branch_id"
                        :label="t('users.branch')"
                        name="branch_id"
                        :options="filteredBranches.map((b) => ({ value: b.id, label: b.branch_name }))"
                        :placeholder="t('users.select_branch')"
                        :error="form.errors.branch_id"
                    />
                    <FormSelect
                        v-model="form.department_id"
                        :label="t('users.department')"
                        name="department_id"
                        :options="filteredDepartments.map((d) => ({ value: d.id, label: d.department_name }))"
                        :placeholder="t('users.select_department')"
                        :error="form.errors.department_id"
                    />
                    <FormSelect
                        v-model="form.position_id"
                        :label="t('users.position')"
                        name="position_id"
                        :options="positionOptions"
                        :error="form.errors.position_id"
                    />
                    <FormSelect
                        v-model="form.grade_id"
                        :label="t('users.grade')"
                        name="grade_id"
                        :options="grades.map((g) => ({ value: g.id, label: g.grade_name }))"
                        :placeholder="t('users.select_grade')"
                        :error="form.errors.grade_id"
                    />
                    <FormSelect
                        v-model="form.subordination_id"
                        :label="t('users.subordination')"
                        name="subordination_id"
                        :options="subordinations.map((s) => ({ value: s.id, label: s.display_name }))"
                        :placeholder="t('users.select_subordination')"
                        :error="form.errors.subordination_id"
                    />
                    <FormSelect
                        v-model="form.rotation_assignment.rotation_id"
                        :label="t('users.rotation')"
                        name="rotation_id"
                        :options="rotationOptions"
                        :placeholder="t('users.select_rotation')"
                        :error="form.errors['rotation_assignment.rotation_id']"
                    />
                    <FormSelect
                        v-model="form.rotation_assignment.rotation_group_id"
                        :label="t('users.rotation_group')"
                        name="rotation_group_id"
                        :options="rotationGroupOptions"
                        :placeholder="t('users.select_rotation_group')"
                        :error="form.errors['rotation_assignment.rotation_group_id']"
                    />
                    <FormInput
                        v-model="form.rotation_assignment.start_date"
                        :label="t('users.rotation_start_date')"
                        name="rotation_start_date"
                        type="date"
                        :error="form.errors['rotation_assignment.start_date']"
                    />
                    <FormInput
                        v-model="form.hire_date"
                        :label="t('users.hire_date')"
                        name="hire_date"
                        type="date"
                        :error="form.errors.hire_date"
                    />
                </div>
            </FormSection>

            <div v-if="mode === 'full'" class="space-y-6">
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
                        required
                        autocomplete="off"
                        :error="form.errors.employee_code"
                    />
                    <FormInput
                        v-model="form.name"
                        :label="t('users.name')"
                        name="name"
                        required
                        autofocus
                        :error="form.errors.name"
                    />
                    <FormInput
                        v-model="form.email"
                        :label="t('users.email')"
                        name="email"
                        type="email"
                        required
                        readonly
                        :hint="t('users.email_generated_from_employee_code')"
                        :error="form.errors.email"
                    />
                    <FormInput
                        v-model="form.email"
                        :label="t('users.email')"
                        name="email"
                        type="email"
                        required
                        readonly
                        :hint="t('users.email_generated_from_employee_code')"
                        :error="form.errors.email"
                    />
                    <FormInput
                        v-model="form.password"
                        :label="t('users.password')"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        :hint="t('users.password_auto_hint')"
                        :error="form.errors.password"
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
                        :error="form.errors.national_id"
                    />
                    <FormInput
                        v-model="form.first_name"
                        :label="t('users.first_name')"
                        name="first_name"
                        :error="form.errors.first_name"
                    />
                    <FormInput
                        v-model="form.last_name"
                        :label="t('users.last_name')"
                        name="last_name"
                        :error="form.errors.last_name"
                    />
                    <FormInput
                        v-model="form.full_name_ar"
                        :label="t('users.full_name_ar')"
                        name="full_name_ar"
                        :error="form.errors.full_name_ar"
                    />
                    <FormInput
                        v-model="form.full_name_en"
                        :label="t('users.full_name_en')"
                        name="full_name_en"
                        :error="form.errors.full_name_en"
                    />
                    <FormInput
                        v-model="form.phone"
                        :label="t('users.phone')"
                        name="phone"
                        :error="form.errors.phone"
                    />
                    <FormInput
                        v-model="form.phone2"
                        :label="t('users.phone2')"
                        name="phone2"
                        :error="form.errors.phone2"
                    />
                    <FormInput
                        v-model="form.date_of_birth"
                        :label="t('users.date_of_birth')"
                        name="date_of_birth"
                        type="date"
                        :error="form.errors.date_of_birth"
                    />
                    <FormSelect
                        v-model="form.gender"
                        :label="t('users.gender')"
                        name="gender"
                        :options="genderOptions"
                        :placeholder="t('users.select_gender')"
                        :error="form.errors.gender"
                    />
                    <FormSelect
                        v-model="form.marital_status"
                        :label="t('users.marital_status')"
                        name="marital_status"
                        :options="maritalOptions"
                        :placeholder="t('users.select_marital_status')"
                        :error="form.errors.marital_status"
                    />
                    <FormInput
                        v-model="form.nationality"
                        :label="t('users.nationality')"
                        name="nationality"
                        :error="form.errors.nationality"
                    />
                </div>
            </FormSection>

            <!-- Employment Information -->
            <FormSection
                :title="t('users.employment_info')"
                icon="fas fa-briefcase"
                :collapsible="true"
                :default-open="false"
                :count="6"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.hire_date"
                        :label="t('users.hire_date')"
                        name="hire_date"
                        type="date"
                        :error="form.errors.hire_date"
                    />
                    <FormInput
                        v-model="form.termination_date"
                        :label="t('users.termination_date')"
                        name="termination_date"
                        type="date"
                        :error="form.errors.termination_date"
                    />
                    <FormSelect
                        v-model="form.attendance_exemption_type"
                        :label="t('users.attendance_exemption_type')"
                        name="attendance_exemption_type"
                        :options="attendanceExemptionOptions"
                        :error="form.errors.attendance_exemption_type"
                    />
                    <FormInput
                        v-model="form.attendance_exemption_from"
                        :label="t('users.attendance_exemption_from')"
                        name="attendance_exemption_from"
                        type="date"
                        :error="form.errors.attendance_exemption_from"
                    />
                    <FormInput
                        v-model="form.attendance_exemption_to"
                        :label="t('users.attendance_exemption_to')"
                        name="attendance_exemption_to"
                        type="date"
                        :error="form.errors.attendance_exemption_to"
                    />
                    <FormSelect
                        v-model="form.employment_type"
                        :label="t('users.employment_type')"
                        name="employment_type"
                        :options="employmentOptions"
                        :placeholder="t('users.select_employment_type')"
                        :error="form.errors.employment_type"
                    />
                    <FormInput
                        v-model="form.job_title"
                        :label="t('users.job_title')"
                        name="job_title"
                        :error="form.errors.job_title"
                    />
                    <FormInput
                        v-model="form.work_location"
                        :label="t('users.work_location')"
                        name="work_location"
                        :error="form.errors.work_location"
                    />
                    <FormSelect
                        v-model="form.status"
                        :label="t('common.status')"
                        name="status"
                        :options="statusOptions"
                        required
                        :error="form.errors.status"
                    />
                    <FormSelect
                        v-model="form.device_privilege"
                        :label="t('users.device_privilege')"
                        name="device_privilege"
                        :options="devicePrivilegeOptions"
                        :error="form.errors.device_privilege"
                    />
                </div>
            </FormSection>

            <!-- Organizational Information -->
            <FormSection
                :title="t('users.organizational_info')"
                icon="fas fa-sitemap"
                :collapsible="true"
                :default-open="false"
                :count="9"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormSelect
                        v-model="form.company_id"
                        :label="t('users.company')"
                        name="company_id"
                        :options="companies.map((c) => ({ value: c.id, label: c.company_name }))"
                        :placeholder="t('users.select_company')"
                        :error="form.errors.company_id"
                    />
                    <FormSelect
                        v-model="form.branch_id"
                        :label="t('users.branch')"
                        name="branch_id"
                        :options="filteredBranches.map((b) => ({ value: b.id, label: b.branch_name }))"
                        :placeholder="t('users.select_branch')"
                        :error="form.errors.branch_id"
                    />
                    <FormSelect
                        v-model="form.department_id"
                        :label="t('users.department')"
                        name="department_id"
                        :options="filteredDepartments.map((d) => ({ value: d.id, label: d.department_name }))"
                        :placeholder="t('users.select_department')"
                        :error="form.errors.department_id"
                    />
                    <FormSelect
                        v-model="form.position_id"
                        :label="t('users.position')"
                        name="position_id"
                        :options="positionOptions"
                        :error="form.errors.position_id"
                    />
                    <FormSelect
                        v-model="form.grade_id"
                        :label="t('users.grade')"
                        name="grade_id"
                        :options="grades.map((g) => ({ value: g.id, label: g.grade_name }))"
                        :placeholder="t('users.select_grade')"
                        :error="form.errors.grade_id"
                    />
                    <FormSelect
                        v-model="form.subordination_id"
                        :label="t('users.subordination')"
                        name="subordination_id"
                        :options="subordinations.map((s) => ({ value: s.id, label: s.display_name }))"
                        :placeholder="t('users.select_subordination')"
                        :error="form.errors.subordination_id"
                    />
                    <FormSelect
                        v-model="form.shift_id"
                        :label="t('users.shift')"
                        name="shift_id"
                        :options="shifts.map((s) => ({ value: s.id, label: s.shift_name }))"
                        :placeholder="t('users.select_shift')"
                        :error="form.errors.shift_id"
                    />
                    <FormSelect
                        v-model="form.manager_id"
                        :label="t('users.manager')"
                        name="manager_id"
                        :options="managers.map((m) => ({ value: m.id, label: m.name }))"
                        :placeholder="t('users.select_manager')"
                        :error="form.errors.manager_id"
                    />
                    <FormSelect
                        v-model="form.attendance_group_id"
                        :label="t('attendance.attendance_group')"
                        name="attendance_group_id"
                        :options="attendanceGroups.map((g) => ({ value: g.id, label: g.name }))"
                        :placeholder="t('attendance.select_attendance_group')"
                        :error="form.errors.attendance_group_id"
                    />
                </div>
            </FormSection>

            <!-- Contact Information -->
            <FormSection
                :title="t('users.contact_info')"
                icon="fas fa-location-dot"
                :collapsible="true"
                :default-open="false"
                :count="5"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.address"
                        :label="t('users.address')"
                        name="address"
                        :error="form.errors.address"
                    />
                    <FormInput
                        v-model="form.city"
                        :label="t('users.city')"
                        name="city"
                        :error="form.errors.city"
                    />
                    <FormInput
                        v-model="form.state"
                        :label="t('users.state')"
                        name="state"
                        :error="form.errors.state"
                    />
                    <FormInput
                        v-model="form.country"
                        :label="t('users.country')"
                        name="country"
                        :error="form.errors.country"
                    />
                    <FormInput
                        v-model="form.postal_code"
                        :label="t('users.postal_code')"
                        name="postal_code"
                        :error="form.errors.postal_code"
                    />
                </div>
            </FormSection>

            <!-- Emergency Contact -->
            <FormSection
                :title="t('users.emergency_info')"
                icon="fas fa-phone-volume"
                :collapsible="true"
                :default-open="false"
                :count="3"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.emergency_contact_name"
                        :label="t('users.emergency_contact_name')"
                        name="emergency_contact_name"
                        :error="form.errors.emergency_contact_name"
                    />
                    <FormInput
                        v-model="form.emergency_contact_phone"
                        :label="t('users.emergency_contact_phone')"
                        name="emergency_contact_phone"
                        :error="form.errors.emergency_contact_phone"
                    />
                    <FormInput
                        v-model="form.emergency_contact_relation"
                        :label="t('users.emergency_contact_relation')"
                        name="emergency_contact_relation"
                        :error="form.errors.emergency_contact_relation"
                    />
                </div>
            </FormSection>

            <!-- Banking Information -->
            <FormSection
                :title="t('users.banking_info')"
                icon="fas fa-landmark"
                :collapsible="true"
                :default-open="false"
                :count="3"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <FormInput
                        v-model="form.bank_name"
                        :label="t('users.bank_name')"
                        name="bank_name"
                        :error="form.errors.bank_name"
                    />
                    <FormInput
                        v-model="form.bank_account_number"
                        :label="t('users.bank_account_number')"
                        name="bank_account_number"
                        :error="form.errors.bank_account_number"
                    />
                    <FormInput
                        v-model="form.iban"
                        :label="t('users.iban')"
                        name="iban"
                        :error="form.errors.iban"
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
                        :error="form.errors.avatar"
                    />
                    <div class="flex items-center gap-6">
                        <FormCheckbox v-model="form.is_active_employee" :label="t('users.is_active_employee')" />
                        <FormCheckbox v-model="form.must_change_password" :label="t('users.must_change_password')" />
                    </div>
                </div>
            </FormSection>

            </div><!-- /full form sections -->

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('users.index')"
                :saving="form.processing"
            />
        </form>
    </template>
