<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormCheckbox, FormFileUpload, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    user: { type: Object, required: true },
    companies: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    grades: { type: Array, default: () => [] },
    managers: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    currentRotationAssignment: { type: Object, default: null },
    rotations: { type: Array, default: () => [] },
});

const form = reactive({
    _method: 'PUT',
    employee_code: props.user.employee_code || '',
    name: props.user.name || '',
    first_name: props.user.first_name || '',
    last_name: props.user.last_name || '',
    full_name_ar: props.user.full_name_ar || '',
    full_name_en: props.user.full_name_en || '',
    email: props.user.email || '',
    password: '',
    password_confirmation: '',
    national_id: props.user.national_id || '',
    phone: props.user.phone || '',
    phone2: props.user.phone2 || '',
    date_of_birth: props.user.date_of_birth || '',
    gender: props.user.gender || '',
    marital_status: props.user.marital_status || '',
    nationality: props.user.nationality || '',
    hire_date: props.user.hire_date || '',
    termination_date: props.user.termination_date || '',
    employment_type: props.user.employment_type || 'full_time',
    job_title: props.user.job_title || '',
    work_location: props.user.work_location || '',
    address: props.user.address || '',
    city: props.user.city || '',
    state: props.user.state || '',
    country: props.user.country || '',
    postal_code: props.user.postal_code || '',
    emergency_contact_name: props.user.emergency_contact_name || '',
    emergency_contact_phone: props.user.emergency_contact_phone || '',
    emergency_contact_relation: props.user.emergency_contact_relation || '',
    bank_name: props.user.bank_name || '',
    bank_account_number: props.user.bank_account_number || '',
    iban: props.user.iban || '',
    avatar: null,
    status: Number(props.user.status ?? 1),
    is_active_employee: !!props.user.is_active_employee,
    must_change_password: !!props.user.must_change_password,
    company_id: props.user.company_id || '',
    branch_id: props.user.branch_id || '',
    department_id: props.user.department_id || '',
    position_id: props.user.position_id || '',
    grade_id: props.user.grade_id || '',
    manager_id: props.user.manager_id || '',
    roles: (props.user.roles || []).map((r) => r.name),
    permissions: (props.user.permissions || []).slice(),
    rotation_assignment: {
        action: '',
        rotation_id: props.currentRotationAssignment?.rotation_id || '',
        rotation_group_id: props.currentRotationAssignment?.rotation_group_id || '',
        start_date: '',
        end_date: '',
    },
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

const hasRotationAssignment = computed(() => !!props.currentRotationAssignment);

const availableRotationGroups = computed(() => {
    const rotation = props.rotations.find((r) => r.id === form.rotation_assignment.rotation_id);
    return rotation?.groups || [];
});

watch(
    () => form.company_id,
    () => {
        form.branch_id = '';
        form.department_id = '';
    },
);

watch(
    () => form.branch_id,
    () => {
        form.department_id = '';
    },
);

watch(
    () => form.rotation_assignment.action,
    (action) => {
        if (action === 'assign' || action === 'transfer') {
            form.rotation_assignment.start_date = '';
            form.rotation_assignment.end_date = '';
        } else {
            form.rotation_assignment.rotation_id = props.currentRotationAssignment?.rotation_id || '';
            form.rotation_assignment.rotation_group_id = props.currentRotationAssignment?.rotation_group_id || '';
            form.rotation_assignment.start_date = '';
            form.rotation_assignment.end_date = '';
        }
    },
);

watch(
    () => form.rotation_assignment.rotation_id,
    () => {
        form.rotation_assignment.rotation_group_id = '';
    },
);

function submit() {
    processing.value = true;
    errors.value = {};

    const payload = { ...form };
    if (!payload.password) {
        delete payload.password;
        delete payload.password_confirmation;
    }
    if (payload.roles.length === 0) delete payload.roles;
    if (payload.permissions.length === 0) delete payload.permissions;
    if (!payload.rotation_assignment.action) delete payload.rotation_assignment;

    router.post(route('users.update', props.user.id), payload, {
        forceFormData: true,
        preserveScroll: true,
        onError: (err) => {
            errors.value = err;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :title="t('users.edit_user')">
        <PageHeader
            :title="t('users.edit_user')"
            :description="user.name"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('users.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <ErrorSummary :errors="errors" />

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Current Avatar -->
            <Card v-if="user.avatar_url" variant="base" padding="none">
                <div class="p-5 sm:p-6 flex items-center gap-3">
                    <img
                        :src="user.avatar_url"
                        :alt="user.name"
                        class="w-16 h-16 rounded-full object-cover border border-mistral-hairline-soft"
                    />
                    <div>
                        <p class="text-[13px] text-mistral-steel">
                            {{ t('users.current_avatar') }}
                        </p>
                    </div>
                </div>
            </Card>

            <!-- Personal Information -->
            <FormSection
                :title="t('users.personal_info')"
                icon="fas fa-user"
                :collapsible="true"
                :default-open="true"
                :count="16"
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
                        :hint="user.id ? 'اتركه فارغاً إذا كنت لا تريد تغييره' : ''"
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
                :count="7"
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
                        v-model="form.manager_id"
                        :label="t('users.manager')"
                        name="manager_id"
                        :options="managers.map((m) => ({ value: m.id, label: m.name }))"
                        :placeholder="t('users.select_manager')"
                        :error="errorFor('manager_id')"
                    />
                </div>
            </FormSection>

            <!-- Rotation Assignment -->
            <FormSection
                :title="t('users.rotation_assignment')"
                icon="fas fa-rotate"
                :collapsible="true"
                :default-open="true"
            >
                <div class="space-y-4">
                    <div v-if="hasRotationAssignment" class="p-3 bg-mistral-cream-soft rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-[13px]">
                            <div>
                                <span class="text-mistral-steel">{{ t('users.current_rotation') }}:</span>
                                <span class="font-semibold text-mistral-ink ms-1">{{ currentRotationAssignment.rotation_name }}</span>
                            </div>
                            <div>
                                <span class="text-mistral-steel">{{ t('users.current_rotation_group') }}:</span>
                                <span class="font-semibold text-mistral-ink ms-1">{{ currentRotationAssignment.group_name }}</span>
                            </div>
                            <div>
                                <span class="text-mistral-steel">{{ t('shifts.start_date') }}:</span>
                                <span class="font-semibold text-mistral-ink ms-1">{{ currentRotationAssignment.start_date }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-3 bg-mistral-cream-soft rounded-lg">
                        <p class="text-[13px] text-mistral-steel">{{ t('users.no_rotation_assigned') }}</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <FormSelect
                            v-model="form.rotation_assignment.action"
                            :label="t('common.action')"
                            name="rotation_action"
                            :options="[
                                { value: '', label: t('common.no_change') },
                                { value: hasRotationAssignment ? 'transfer' : 'assign', label: t('shifts.assign_rotation') },
                                ...(hasRotationAssignment ? [{ value: 'unassign', label: t('shifts.remove_from_rotation') }] : []),
                            ]"
                            :placeholder="t('common.select_action')"
                        />
                        <template v-if="form.rotation_assignment.action && form.rotation_assignment.action !== 'unassign'">
                            <FormSelect
                                v-model="form.rotation_assignment.rotation_id"
                                :label="t('users.rotation')"
                                name="rotation_id"
                                :options="rotations.map((r) => ({ value: r.id, label: r.name }))"
                                :placeholder="t('users.select_rotation')"
                                :error="errorFor('rotation_assignment.rotation_id')"
                            />
                            <FormSelect
                                v-model="form.rotation_assignment.rotation_group_id"
                                :label="t('users.rotation_group')"
                                name="rotation_group_id"
                                :options="availableRotationGroups.map((g) => ({ value: g.id, label: g.name }))"
                                :placeholder="t('users.select_rotation_group')"
                                :error="errorFor('rotation_assignment.rotation_group_id')"
                            />
                            <FormInput
                                v-model="form.rotation_assignment.start_date"
                                :label="t('users.rotation_start_date')"
                                name="rotation_start_date"
                                type="date"
                                :error="errorFor('rotation_assignment.start_date')"
                            />
                            <FormInput
                                v-model="form.rotation_assignment.end_date"
                                :label="t('users.rotation_end_date')"
                                name="rotation_end_date"
                                type="date"
                                :error="errorFor('rotation_assignment.end_date')"
                            />
                        </template>
                        <template v-if="form.rotation_assignment.action === 'unassign'">
                            <FormInput
                                v-model="form.rotation_assignment.end_date"
                                :label="t('shifts.end_date')"
                                name="rotation_end_date"
                                type="date"
                                :error="errorFor('rotation_assignment.end_date')"
                            />
                        </template>
                    </div>
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
                        :label="t('users.replace_avatar')"
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
                :save-label="t('common.update')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('users.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
