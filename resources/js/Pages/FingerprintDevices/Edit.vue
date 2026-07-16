<script setup>

import { reactive, ref, computed } from 'vue';

import { router, Link } from '@inertiajs/vue3';

import AppLayout from '@/Layouts/AppLayout.vue';

import PageHeader from '@/Components/ui/PageHeader.vue';

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import FormInput from '@/Components/ui/FormInput.vue';

import FormTextarea from '@/Components/ui/FormTextarea.vue';

import FormSelect from '@/Components/ui/FormSelect.vue';

import FormCheckbox from '@/Components/ui/FormCheckbox.vue';

import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({

    device: { type: Object, required: true },

    deviceTypes: { type: Array, default: () => [] },

    branches: { type: Array, default: () => [] },

});

const form = reactive({

    _method: 'PUT',

    device_type_id: props.device.device_type_id || '',

    branch_id: props.device.branch_id || '',

    name: props.device.name || '',

    serial_number: props.device.serial_number || '',

    ip_address: props.device.ip_address || '',

    port: props.device.port || 4370,

    comm_key: props.device.comm_key || 0,

    timezone: props.device.timezone || 'Asia/Baghdad',

    connection_type: props.device.connection_type || 'tcp',

    timeout: props.device.timeout || 30,

    status: props.device.status || 'offline',

    notes: props.device.notes || '',

    is_push_enabled: !!props.device.is_push_enabled,

    push_url: props.device.push_url || '',

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

        label: `${dt.name} (${dt.manufacturer})`,

    })),

);

const branchOptions = computed(() => [

    { value: '', label: t('fingerprint_devices.no_branch') },

    ...props.branches.map((b) => ({

        value: b.id,

        label: b.branch_name,

    })),

]);

const errorFor = (key) => errors.value[key] || '';

function submit() {

    processing.value = true;

    errors.value = {};

    router.post(route('fingerprint-devices.update', props.device.id), form, {

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

    <AppLayout :title="t('fingerprint_devices.edit_device')">

        <PageHeader

            :title="t('fingerprint_devices.edit_device')"

            :description="device.name"

        >

            <template #actions>

                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.back') }}</Button>

            
</template>

        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                <FormSelect

                    v-model="form.device_type_id"

                    :label="t('fingerprint_devices.device_type')"

                    name="device_type_id"

                    :options="deviceTypeOptions"

                    required

                    :error="errorFor('device_type_id')"

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

                <FormInput

                    v-model="form.ip_address"

                    :label="t('fingerprint_devices.ip_address')"

                    name="ip_address"

                    required

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

                    v-model="form.comm_key"

                    :label="t('fingerprint_devices.comm_key')"

                    name="comm_key"

                    type="number"

                    :hint="t('fingerprint_devices.comm_key_hint')"

                    :error="errorFor('comm_key')"

                />

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

            <div class="mt-4">

                <FormTextarea

                    v-model="form.notes"

                    :label="t('fingerprint_devices.notes')"

                    name="notes"

                    :rows="3"

                    :error="errorFor('notes')"

                />

            </div>

            <div class="mt-4 flex items-center gap-2">

                <FormCheckbox v-model="form.is_push_enabled" :label="t('fingerprint_devices.push_enabled')" />

            </div>

            <div v-if="form.is_push_enabled" class="mt-4">

                <FormInput

                    v-model="form.push_url"

                    :label="t('fingerprint_devices.push_url')"

                    name="push_url"

                    type="url"

                    :error="errorFor('push_url')"

                />

            </div>

            <div class="mt-6 flex items-center justify-start gap-2">

                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">{{ t('common.update') }}</Button>

                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.cancel') }}</Button>

            </div>

        </Card>

    </AppLayout>

</template>
