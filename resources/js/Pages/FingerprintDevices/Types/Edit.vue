<script setup>
import { reactive, ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormTextarea from '@/Components/ui/FormTextarea.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    deviceType: { type: Object, required: true },
});

const form = reactive({
    _method: 'PUT',
    name: props.deviceType.name || '',
    manufacturer: props.deviceType.manufacturer || 'ZKTeco',
    protocol: props.deviceType.protocol || 'ADMS',
    sdk_version: props.deviceType.sdk_version || '',
    default_port: props.deviceType.default_port || 4370,
    supports_fingerprint: !!props.deviceType.supports_fingerprint,
    supports_face: !!props.deviceType.supports_face,
    max_fingerprints: props.deviceType.max_fingerprints || 3000,
    max_users: props.deviceType.max_users || 10000,
    description: props.deviceType.description || '',
    is_active: !!props.deviceType.is_active,
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
    router.post(route('fingerprint-device-types.update', props.deviceType.id), form, {
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
    <AppLayout :title="t('fingerprint_devices.edit_type')">
        <PageHeader
            :title="t('fingerprint_devices.edit_type')"
            :description="deviceType.name"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-device-types.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="card p-6" @submit.prevent="submit">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                    v-model="form.name"
                    :label="t('fingerprint_devices.type_name')"
                    name="name"
                    required
                    :error="errorFor('name')"
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

            <div class="mt-4">
                <FormTextarea
                    v-model="form.description"
                    :label="t('fingerprint_devices.description')"
                    name="description"
                    :rows="3"
                    :error="errorFor('description')"
                />
            </div>

            <div class="mt-4 flex items-center gap-4 flex-wrap">
                <div class="flex items-center gap-2">
                    <input
                        id="supports_fingerprint_edit"
                        v-model="form.supports_fingerprint"
                        type="checkbox"
                        class="w-4 h-4"
                    />
                    <label for="supports_fingerprint_edit" class="text-[14px] text-[var(--color-ink)]">
                        {{ t('fingerprint_devices.supports_fingerprint') }}
                    </label>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        id="supports_face_edit"
                        v-model="form.supports_face"
                        type="checkbox"
                        class="w-4 h-4"
                    />
                    <label for="supports_face_edit" class="text-[14px] text-[var(--color-ink)]">
                        {{ t('fingerprint_devices.supports_face') }}
                    </label>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        id="is_active_edit"
                        v-model="form.is_active"
                        type="checkbox"
                        class="w-4 h-4"
                    />
                    <label for="is_active_edit" class="text-[14px] text-[var(--color-ink)]">
                        {{ t('common.active') }}
                    </label>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-start gap-2">
                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                    <i v-if="processing" class="fas fa-spinner fa-spin"></i>
                    <i v-else class="fas fa-save"></i>
                    <span>{{ t('common.update') }}</span>
                </Button>
                <Button variant="secondary" :href="route('fingerprint-device-types.index')">{{ t('common.cancel') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
