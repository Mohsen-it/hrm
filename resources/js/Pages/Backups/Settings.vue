<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { PageHeader, Button, Card, FormSelect, FormInput, FormSwitch, Alert, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';
import { usePageTitle } from '@/composables/usePageTitle';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    config: { type: Object, default: () => ({}) },
    lastBackup: { type: Object, default: null },
});

const loading = ref(false);
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const form = computed({
    get: () => props.config || {},
    set: (val) => {},
});

const freqOptions = [
    { value: 'daily', label: t('backups.freq_daily') },
    { value: 'weekly', label: t('backups.freq_weekly') },
    { value: 'monthly', label: t('backups.freq_monthly') },
    { value: 'manual_only', label: t('backups.freq_manual') },
];

const dayOptions = [
    { value: '0', label: 'Sunday' },
    { value: '1', label: 'Monday' },
    { value: '2', label: 'Tuesday' },
    { value: '3', label: 'Wednesday' },
    { value: '4', label: 'Thursday' },
    { value: '5', label: 'Friday' },
    { value: '6', label: 'Saturday' },
];

const tzOptions = [
    { value: 'Asia/Damascus', label: 'Asia/Damascus (عمان)' },
    { value: 'Asia/Riyadh', label: 'Asia/Riyadh (الرياض)' },
    { value: 'Africa/Cairo', label: 'Africa/Cairo (القاهرة)' },
    { value: 'Europe/Istanbul', label: 'Europe/Istanbul (إسطنبول)' },
    { value: 'UTC', label: 'UTC' },
];

function save() {
    loading.value = true;
    router.post(route('backups.settings.update'), { ...form.value }, {
        preserveScroll: true,
        onFinish: () => { loading.value = false; },
    });
}

function runNow() {
    loading.value = true;
    router.post(route('backups.store'), {}, {
        preserveScroll: true,
        onFinish: () => { loading.value = false; },
    });
}

usePageTitle(t('backups.settings_title'));
</script>

<template>
    <PageHeader :title="t('backups.settings_title')" :description="t('backups.settings_description')">
        <template #actions>
            <Button variant="secondary" :href="route('backups.index')">
                {{ t('backups.back_to_list') }}
            </Button>
            <Button variant="primary" :loading="loading" @click="runNow">
                {{ t('backups.create_backup') }}
            </Button>
        </template>
    </PageHeader>

    <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
    <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

    <!-- Last backup status -->
    <Card v-if="lastBackup">
        <template #header>
            <h3 class="text-[15px] font-bold text-mistral-ink">{{ t('backups.last_backup') }}</h3>
        </template>
        <div class="flex items-center gap-4 p-4">
            <div class="w-12 h-12 rounded-xl bg-mistral-success/10 flex items-center justify-center">
                <i class="fas fa-check-circle text-mistral-success text-[20px]" aria-hidden="true"></i>
            </div>
            <div class="flex-1">
                <div class="text-[14px] font-bold text-mistral-ink">{{ lastBackup.file_name }}</div>
                <div class="text-[12px] text-mistral-steel mt-0.5">
                    {{ lastBackup.created_at }} · {{ (lastBackup.file_size / 1024 / 1024).toFixed(1) }} MB
                    · <span class="text-mistral-success font-semibold">{{ lastBackup.verification_status }}</span>
                </div>
            </div>
            <Button variant="secondary" size="sm" :href="route('backups.show', lastBackup.id)">
                {{ t('backups.details') }}
            </Button>
        </div>
    </Card>

    <form @submit.prevent="save" class="space-y-4">
        <!-- Schedule section -->
        <Card>
            <template #header>
                <h3 class="text-[15px] font-bold text-mistral-ink">{{ t('backups.schedule') }}</h3>
                <p class="text-[12px] text-mistral-steel mt-1">{{ t('backups.schedule_desc') }}</p>
            </template>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                <!-- Frequency -->
                <div class="md:col-span-2">
                    <label class="block text-[12px] font-medium text-mistral-steel mb-1">{{ t('backups.frequency') }}</label>
                    <FormSelect
                        :model-value="form.frequency || 'daily'"
                        :options="freqOptions"
                        @update:model-value="form.frequency = $event"
                    />
                    <p class="text-[11px] text-mistral-stone mt-1">{{ t('backups.frequency_help') }}</p>
                </div>

                <!-- Daily time -->
                <div>
                    <label class="block text-[12px] font-medium text-mistral-steel mb-1">{{ t('backups.daily_time') }}</label>
                    <FormInput
                        v-model="form.scheduled_time"
                        type="time"
                    />
                </div>

                <!-- Timezone -->
                <div>
                    <label class="block text-[12px] font-medium text-mistral-steel mb-1">{{ t('backups.timezone') }}</label>
                    <FormSelect
                        :model-value="form.timezone || 'Asia/Damascus'"
                        :options="tzOptions"
                        @update:model-value="form.timezone = $event"
                    />
                </div>

                <!-- Weekly day -->
                <div>
                    <label class="block text-[12px] font-medium text-mistral-steel mb-1">{{ t('backups.weekly_day') }}</label>
                    <FormSelect
                        :model-value="String(form.day_of_week ?? '0')"
                        :options="dayOptions"
                        @update:model-value="form.day_of_week = $event"
                    />
                </div>

                <!-- Retention daily -->
                <div>
                    <label class="block text-[12px] font-medium text-mistral-steel mb-1">{{ t('backups.retention_daily') }}</label>
                    <FormInput
                        v-model="form.retention_daily"
                        type="number"
                        min="1"
                        max="365"
                    />
                    <p class="text-[11px] text-mistral-stone mt-1">{{ t('backups.retention_help') }}</p>
                </div>
            </div>
        </Card>

        <!-- Options section -->
        <Card>
            <template #header>
                <h3 class="text-[15px] font-bold text-mistral-ink">{{ t('backups.options') }}</h3>
            </template>
            <div class="space-y-4 p-4">
                <FormSwitch
                    :model-value="form.encryption_enabled !== false"
                    :label="t('backups.encryption')"
                    :description="t('backups.encryption_desc')"
                    @update:model-value="form.encryption_enabled = $event"
                    @change="form.encryption_enabled = $event"
                />
                <FormSwitch
                    :model-value="form.verification_enabled !== false"
                    :label="t('backups.verification')"
                    :description="t('backups.verification_desc')"
                    @update:model-value="form.verification_enabled = $event"
                    @change="form.verification_enabled = $event"
                />
                <FormSwitch
                    :model-value="form.notify_on_failure !== false"
                    :label="t('backups.notify_failure')"
                    :description="t('backups.notify_failure_desc')"
                    @update:model-value="form.notify_on_failure = $event"
                    @change="form.notify_on_failure = $event"
                />
                <FormSwitch
                    :model-value="form.include_files === true"
                    :label="t('backups.include_files')"
                    :description="t('backups.include_files_desc')"
                    @update:model-value="form.include_files = $event"
                    @change="form.include_files = $event"
                />
            </div>
        </Card>

        <FormActions
            :save-label="t('settings.save_all')"
            :cancel-label="t('common.back')"
            :cancel-href="route('backups.index')"
            :saving="loading"
            class="mt-4"
        />
    </form>
</template>
