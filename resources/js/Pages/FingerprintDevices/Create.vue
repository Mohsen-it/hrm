<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormCheckbox, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    deviceTypes: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
    companies: { type: Array, default: () => [] },
    subordinations: { type: Array, default: () => [] },
});

const form = reactive({
    device_type_id: '',
    branch_id: '',
    default_company_id: '',
    default_branch_id: '',
    default_subordination_id: '',
    name: '',
    serial_number: '',
    ip_address: '',
    port: 4370,
    comm_key: '',
    hikvision_username: '',
    hikvision_password: '',
    timezone: 'Asia/Baghdad',
    connection_type: 'tcp',
    timeout: 30,
    status: 'offline',
    notes: '',
    is_push_enabled: false,
    push_url: '',
});

const errors = ref({});
const processing = ref(false);

const statusOptions = [
    { value: 'online', label: t('fingerprint_devices.online') },
    { value: 'offline', label: t('fingerprint_devices.offline') },
    { value: 'maintenance', label: t('fingerprint_devices.maintenance') },
    { value: 'deactivated', label: t('fingerprint_devices.deactivated') },
];

const connectionTypeOptions = [
    { value: 'tcp', label: 'TCP' },
    { value: 'udp', label: 'UDP' },
];

const deviceTypeOptions = computed(() =>
    props.deviceTypes.map((dt) => ({
        value: dt.id,
        label: `${dt.name} (${dt.manufacturer})${dt.default_port ? ' :' + dt.default_port : ''}`,
    })),
);

const isHikvision = computed(() => {
    const selected = props.deviceTypes.find((dt) => String(dt.id) === String(form.device_type_id));
    return selected && (selected.manufacturer || '').toLowerCase().includes('hik');
});

watch(
    () => [form.hikvision_username, form.hikvision_password, isHikvision.value],
    () => {
        if (isHikvision.value && form.hikvision_username) {
            form.comm_key = form.hikvision_password
                ? `${form.hikvision_username}:${form.hikvision_password}`
                : form.hikvision_username;
        }
    },
);

const commKeyHint = computed(() =>
    isHikvision.value
        ? 'Format: username:password (e.g. admin:alep@2025)'
        : t('fingerprint_devices.comm_key_hint'),
);

const branchOptions = computed(() => [
    { value: '', label: t('fingerprint_devices.no_branch') },
    ...props.branches.map((b) => ({
        value: b.id,
        label: b.branch_name,
    })),
]);

const companyOptions = computed(() => [
    { value: '', label: t('fingerprint_devices.no_company') },
    ...props.companies.map((c) => ({
        value: c.id,
        label: c.company_name,
    })),
]);

const defaultBranchOptions = computed(() => [
    { value: '', label: t('fingerprint_devices.no_branch') },
    ...props.branches.map((b) => ({
        value: b.id,
        label: b.branch_name,
    })),
]);

const subordinationOptions = computed(() => [
    { value: '', label: t('fingerprint_devices.no_subordination') },
    ...props.subordinations.map((s) => ({
        value: s.id,
        label: s.name_ar,
    })),
]);

const errorFor = (key) => errors.value[key] || '';

function onDeviceTypeChange(value) {
    form.device_type_id = value;
    const selected = props.deviceTypes.find((dt) => dt.id === value);
    if (selected?.default_port && form.port === 4370) {
        form.port = selected.default_port;
    }
}

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('fingerprint-devices.store'), form, {
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
    <AppLayout :title="t('fingerprint_devices.add_device')">
        <PageHeader
            :title="t('fingerprint_devices.add_device')"
            :description="t('fingerprint_devices.create_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('fingerprint_devices.device_info')" icon="fas fa-fingerprint" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        :model-value="form.device_type_id"
                        :label="t('fingerprint_devices.device_type')"
                        name="device_type_id"
                        :options="deviceTypeOptions"
                        required
                        :error="errorFor('device_type_id')"
                        autofocus
                        @update:model-value="onDeviceTypeChange"
                    />
                    <FormSelect
                        v-model="form.branch_id"
                        :label="t('fingerprint_devices.branch')"
                        name="branch_id"
                        :options="branchOptions"
                        :error="errorFor('branch_id')"
                    />
                    <FormInput
                        v-model="form.name"
                        :label="t('fingerprint_devices.device_name')"
                        name="name"
                        required
                        :error="errorFor('name')"
                    />
                    <FormInput
                        v-model="form.serial_number"
                        :label="t('fingerprint_devices.serial_number')"
                        name="serial_number"
                        required
                        :error="errorFor('serial_number')"
                    />
                </div>
            </FormSection>

            <FormSection :title="t('fingerprint_devices.default_org_section')" icon="fas fa-sitemap" :collapsible="true" :default-open="true">
                <p class="text-sm text-mistral-ink-soft mb-4">{{ t('fingerprint_devices.default_org_hint') }}</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <FormSelect
                        v-model="form.default_company_id"
                        :label="t('fingerprint_devices.default_company')"
                        name="default_company_id"
                        :options="companyOptions"
                        :error="errorFor('default_company_id')"
                    />
                    <FormSelect
                        v-model="form.default_branch_id"
                        :label="t('fingerprint_devices.default_branch')"
                        name="default_branch_id"
                        :options="defaultBranchOptions"
                        :error="errorFor('default_branch_id')"
                    />
                    <FormSelect
                        v-model="form.default_subordination_id"
                        :label="t('fingerprint_devices.default_subordination')"
                        name="default_subordination_id"
                        :options="subordinationOptions"
                        :error="errorFor('default_subordination_id')"
                    />
                </div>
            </FormSection>

            <FormSection :title="t('fingerprint_devices.connection')" icon="fas fa-plug" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.ip_address"
                        :label="t('fingerprint_devices.ip_address')"
                        name="ip_address"
                        required
                        :placeholder="t('fingerprint_devices.ip_placeholder')"
                        :error="errorFor('ip_address')"
                    />
                    <FormInput
                        v-model="form.port"
                        :label="t('fingerprint_devices.port')"
                        name="port"
                        type="number"
                        :error="errorFor('port')"
                    />
                    <FormInput
                        v-if="!isHikvision"
                        v-model="form.comm_key"
                        :label="t('fingerprint_devices.comm_key')"
                        name="comm_key"
                        type="number"
                        :hint="t('fingerprint_devices.comm_key_hint')"
                        :error="errorFor('comm_key')"
                    />
                    <template v-else>
                        <FormInput
                            v-model="form.hikvision_username"
                            label="Username"
                            name="hikvision_username"
                            placeholder="admin"
                            :error="errorFor('comm_key')"
                        />
                        <FormInput
                            v-model="form.hikvision_password"
                            label="Password"
                            name="hikvision_password"
                            type="password"
                            placeholder="••••••"
                            :error="errorFor('comm_key')"
                        />
                        <input type="hidden" name="comm_key" :value="form.comm_key" />
                    </template>
                    <FormSelect
                        v-model="form.connection_type"
                        :label="t('fingerprint_devices.connection_type')"
                        name="connection_type"
                        :options="connectionTypeOptions"
                        :error="errorFor('connection_type')"
                    />
                    <FormSelect
                        v-model="form.status"
                        :label="t('common.status')"
                        name="status"
                        :options="statusOptions"
                        :error="errorFor('status')"
                    />
                    <FormInput
                        v-model="form.timezone"
                        :label="t('fingerprint_devices.timezone')"
                        name="timezone"
                        :error="errorFor('timezone')"
                    />
                    <FormInput
                        v-model="form.timeout"
                        :label="t('fingerprint_devices.timeout')"
                        name="timeout"
                        type="number"
                        :error="errorFor('timeout')"
                    />
                </div>
            </FormSection>

            <FormSection :title="t('common.additional')" icon="fas fa-plus-circle" :collapsible="true" :default-open="true">
                <FormTextarea
                    v-model="form.notes"
                    :label="t('fingerprint_devices.notes')"
                    name="notes"
                    :rows="3"
                    :error="errorFor('notes')"
                    class="mb-4"
                />
                <FormCheckbox v-model="form.is_push_enabled" :label="t('fingerprint_devices.push_enabled')" class="mb-4" />
                <FormInput
                    v-if="form.is_push_enabled"
                    v-model="form.push_url"
                    :label="t('fingerprint_devices.push_url')"
                    name="push_url"
                    type="url"
                    :error="errorFor('push_url')"
                />
            </FormSection>

            <FormActions :save-label="t('common.save')" :cancel-label="t('common.cancel')" :cancel-href="route('fingerprint-devices.index')" :saving="processing" />
        </form>
    </AppLayout>
</template>
