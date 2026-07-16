<script setup>

import { computed, ref } from 'vue';

import { router, Link } from '@inertiajs/vue3';

import AppLayout from '@/Layouts/AppLayout.vue';

import PageHeader from '@/Components/ui/PageHeader.vue';

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import Badge from '@/Components/ui/Badge.vue';

import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';

import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({

    device: { type: Object, required: true },

});

const showDelete = ref(false);

const statusVariant = (status) => {

   
const map = { online: 'active', offline: 'inactive', maintenance: 'pending', deactivated: 'inactive' };

   
return map[status] || 'inactive';

};

function formatDateTime(value) {

    if (!value)
return '���';

   
const d = new Date(value);

    if (Number.isNaN(d.getTime()))
return value;

   
return d.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'medium' });

}

function performDelete() {

    showDelete.value = false;

    router.delete(route('fingerprint-devices.destroy', props.device.id));

}

const fields = computed(() => [

    { label: t('fingerprint_devices.device_name'), value: props.device.name },

    { label: t('fingerprint_devices.serial_number'), value: props.device.serial_number },

    { label: t('fingerprint_devices.ip_address'), value: `${props.device.ip_address}:${props.device.port}` },

    { label: t('fingerprint_devices.connection_type'), value: props.device.connection_type?.toUpperCase() },

    { label: t('fingerprint_devices.comm_key'), value: String(props.device.comm_key ?? 0) },

    { label: t('fingerprint_devices.timezone'), value: props.device.timezone },

    { label: t('fingerprint_devices.timeout'), value: `${props.device.timeout}s` },

    { label: t('fingerprint_devices.device_type'), value: props.device.device_type?.name || '���' },

    { label: t('fingerprint_devices.branch'), value: props.device.branch?.branch_name || '���' },

    { label: t('fingerprint_devices.user_count'), value: props.device.user_count ?? 0 },

    { label: t('fingerprint_devices.fingerprint_count'), value: props.device.fingerprint_count ?? 0 },

    { label: t('fingerprint_devices.last_seen'), value: formatDateTime(props.device.last_seen_at) },

    { label: t('fingerprint_devices.last_synced'), value: formatDateTime(props.device.last_synced_at) },

    { label: t('fingerprint_devices.push_enabled'), value: props.device.is_push_enabled ? t('common.yes') : t('common.no') },

    { label: t('fingerprint_devices.notes'), value: props.device.notes || '���' },

]);

</script>

<template>

    <AppLayout :title="t('fingerprint_devices.view_device')">

        <PageHeader

            :title="t('fingerprint_devices.view_device')"

            :description="device.name"

        >

            <template #actions>

                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.back') }}</Button>

                <Button
                    variant="secondary"
                    icon="fas fa-plug"
                    @click="router.post(route('fingerprint-devices.test-connection', device.id), {}, { preserveScroll: true })"
                >
                    {{ t('fingerprint_devices.test_connection') }}
                </Button>

                <Button variant="secondary" icon="fas fa-cloud-download-alt" :href="route('fingerprint-devices.sync', { device_id: device.id })">
                    {{ t('fingerprint_devices.sync_title') }}
                </Button>

                <Button variant="primary" icon="fas fa-edit" :href="route('fingerprint-devices.edit', device.id)">
                    {{ t('common.edit') }}
                </Button>

                <Button variant="danger" icon="fas fa-trash" @click="showDelete = true">
                    {{ t('common.delete') }}
                </Button>

</template>

        </PageHeader>

        <div class="card p-6">

            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">

                <div

                    class="w-20 h-20 rounded-md bg-mistral-surface flex items-center justify-center border border-mistral-hairline-soft"

                >

                    <i class="fas fa-fingerprint text-[32px] text-mistral-stone"></i>

                </div>

                <div>

                    <h2 class="text-[20px] font-semibold text-mistral-ink">

                        {{ device.name }}

                    </h2>

                    <p class="text-[13px] text-mistral-steel mt-1">

                        {{ device.serial_number }}

                    </p>

                    <div class="mt-2 flex items-center gap-2">

                        <Badge :text="t('fingerprint_devices.' + device.status)" :variant="statusVariant(device.status)" />

                    </div>

                </div>

            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">

                <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-right">

                    <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">

                        {{ field.label }}

                    </dt>

                    <dd class="text-[14px] text-mistral-ink mt-1 break-words">

                        {{ field.value }}

                    </dd>

                </div>

            </dl>

        </div>

        <ConfirmDialog

            v-model="showDelete"

            :title="t('fingerprint_devices.delete_confirm_title')"

            :message="t('fingerprint_devices.delete_confirm_message', { name: device.name })"

            :confirm-text="t('common.delete')"

            :cancel-text="t('common.cancel')"

            confirm-variant="danger"

            @confirm="performDelete"

        />

    </AppLayout>

</template>
