<script setup>
import { reactive, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormTextarea, FormSelect, FormCheckbox, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = reactive({
    name: '',
    manufacturer: 'ZKTeco',
    protocol: 'ADMS',
    sdk_version: '',
    default_port: 4370,
    supports_fingerprint: true,
    supports_face: false,
    max_fingerprints: 3000,
    max_users: 10000,
    description: '',
    is_active: true,
});

const errors = ref({});
const processing = ref(false);

const protocolOptions = [
    { value: 'ADMS', label: 'ADMS' },
    { value: 'SSR', label: 'SSR' },
    { value: 'SDK', label: 'SDK' },
];

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('fingerprint-device-types.store'), form, {
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
    <AppLayout :title="t('fingerprint_devices.add_type')">
        <PageHeader
            :title="t('fingerprint_devices.add_type')"
            :description="t('fingerprint_devices.create_type_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-device-types.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('fingerprint_devices.type_info')" icon="fas fa-microchip" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.name"
                        :label="t('fingerprint_devices.type_name')"
                        name="name"
                        required
                        :error="errorFor('name')"
                        autofocus
                    />
                    <FormInput
                        v-model="form.manufacturer"
                        :label="t('fingerprint_devices.manufacturer')"
                        name="manufacturer"
                        required
                        :error="errorFor('manufacturer')"
                    />
                    <FormSelect
                        v-model="form.protocol"
                        :label="t('fingerprint_devices.protocol')"
                        name="protocol"
                        :options="protocolOptions"
                        :error="errorFor('protocol')"
                    />
                    <FormInput
                        v-model="form.sdk_version"
                        :label="t('fingerprint_devices.sdk_version')"
                        name="sdk_version"
                        :error="errorFor('sdk_version')"
                    />
                    <FormInput
                        v-model="form.default_port"
                        :label="t('fingerprint_devices.default_port')"
                        name="default_port"
                        type="number"
                        :error="errorFor('default_port')"
                    />
                </div>
            </FormSection>

            <FormSection :title="t('fingerprint_devices.capabilities')" icon="fas fa-shield-halved" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.max_fingerprints"
                        :label="t('fingerprint_devices.max_fingerprints')"
                        name="max_fingerprints"
                        type="number"
                        :error="errorFor('max_fingerprints')"
                    />
                    <FormInput
                        v-model="form.max_users"
                        :label="t('fingerprint_devices.max_users')"
                        name="max_users"
                        type="number"
                        :error="errorFor('max_users')"
                    />
                </div>
                <div class="mt-4 flex items-center gap-4">
                    <FormCheckbox v-model="form.supports_fingerprint" :label="t('fingerprint_devices.supports_fingerprint')" />
                    <FormCheckbox v-model="form.supports_face" :label="t('fingerprint_devices.supports_face')" />
                </div>
            </FormSection>

            <FormSection :title="t('common.additional')" icon="fas fa-plus-circle" :collapsible="true" :default-open="true">
                <FormTextarea
                    v-model="form.description"
                    :label="t('fingerprint_devices.description')"
                    name="description"
                    :rows="3"
                    :error="errorFor('description')"
                    class="mb-4"
                />
                <FormCheckbox v-model="form.is_active" :label="t('common.active')" />
            </FormSection>

            <FormActions :save-label="t('common.save')" :cancel-label="t('common.cancel')" :cancel-href="route('fingerprint-device-types.index')" :saving="processing" />
        </form>
    </AppLayout>
</template>
