<script setup>
import { reactive, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSelect, FormSwitch, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    timeSchedules: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const form = reactive({
    name: '',
    type: 'cyclic',
    work_days: '',
    rest_days: '',
    work_days_json: { sunday: false, monday: false, tuesday: false, wednesday: false, thursday: false, friday: false, saturday: false },
    weekend_days_json: { sunday: false, monday: false, tuesday: false, wednesday: false, thursday: false, friday: false, saturday: false },
    required_hours: '',
    period_type: 'daily',
    overtime_enabled: false,
    fingerprint_enabled: false,
    work_on_holidays: false,
    work_on_weekends: false,
    color: 'var(--color-mistral-primary)',
    time_schedule_id: '',
});

const errors = ref({});
const processing = ref(false);

const errorFor = (key) => errors.value[key] || '';

const typeOptions = computed(() => [
    { value: 'cyclic', label: t('shifts.cyclic') },
    { value: 'weekly', label: t('shifts.weekly') },
    { value: 'hours', label: t('shifts.hours') },
]);

const periodTypeOptions = computed(() => [
    { value: 'daily', label: t('shifts.daily') },
    { value: 'weekly', label: t('shifts.weekly_label') },
    { value: 'monthly', label: t('shifts.monthly') },
]);

const dayKeys = [
    { key: 'sunday', label: t('shifts.sunday') },
    { key: 'monday', label: t('shifts.monday') },
    { key: 'tuesday', label: t('shifts.tuesday') },
    { key: 'wednesday', label: t('shifts.wednesday') },
    { key: 'thursday', label: t('shifts.thursday') },
    { key: 'friday', label: t('shifts.friday') },
    { key: 'saturday', label: t('shifts.saturday') },
];

const scheduleOptions = computed(() => {
    const items = Array.isArray(props.timeSchedules) ? props.timeSchedules : (props.timeSchedules?.data || []);
    return items.map((s) => ({ value: s.id, label: s.name }));
});

function submit() {
    processing.value = true;
    errors.value = {};

    let workDaysJson = form.work_days_json;
    let weekendDaysJson = form.weekend_days_json;

    if (form.type === 'weekly' && workDaysJson && typeof workDaysJson === 'object' && !Array.isArray(workDaysJson)) {
        const dayMap = { sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6 };
        workDaysJson = Object.keys(workDaysJson).filter((k) => workDaysJson[k]).map((k) => dayMap[k]).filter((v) => v !== undefined);
    }

    if (form.type === 'weekly' && weekendDaysJson && typeof weekendDaysJson === 'object' && !Array.isArray(weekendDaysJson)) {
        const dayMap = { sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6 };
        weekendDaysJson = Object.keys(weekendDaysJson).filter((k) => weekendDaysJson[k]).map((k) => dayMap[k]).filter((v) => v !== undefined);
    }

    router.post(route('shift-categories.store'), {
        ...form,
        work_days_json: workDaysJson,
        weekend_days_json: weekendDaysJson,
        time_schedule_id: form.time_schedule_id || null,
    }, {
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
    <AppLayout :title="t('shifts.add_category')">
        <PageHeader
            :title="t('shifts.add_category')"
            :description="t('shifts.index_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('shift-categories.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="space-y-6 max-w-3xl" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('shifts.basic_info')" icon="fas fa-info-circle" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.name"
                        :label="t('shifts.name')"
                        name="name"
                        :error="errorFor('name')"
                        required
                        autofocus
                    />
                    <FormSelect
                        v-model="form.type"
                        :label="t('shifts.category_type')"
                        name="type"
                        :options="typeOptions"
                        :error="errorFor('type')"
                        required
                    />
                    <FormSelect
                        v-model="form.time_schedule_id"
                        :label="t('shifts.time_schedule')"
                        name="time_schedule_id"
                        :options="scheduleOptions"
                        :placeholder="t('shifts.select_time_schedule')"
                        :error="errorFor('time_schedule_id')"
                    />
                    <FormInput
                        v-model="form.color"
                        :label="t('shifts.color')"
                        name="color"
                        type="color"
                        :error="errorFor('color')"
                    />
                </div>
            </FormSection>

            <FormSection v-if="form.type === 'cyclic'" :title="t('shifts.cyclic_settings')" icon="fas fa-sync-alt" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.work_days"
                        :label="t('shifts.work_days')"
                        name="work_days"
                        type="number"
                        min="1"
                        :error="errorFor('work_days')"
                    />
                    <FormInput
                        v-model="form.rest_days"
                        :label="t('shifts.rest_days')"
                        name="rest_days"
                        type="number"
                        min="0"
                        :error="errorFor('rest_days')"
                    />
                </div>
            </FormSection>

            <FormSection v-if="form.type === 'weekly'" :title="t('shifts.weekly_settings')" icon="fas fa-calendar-week" :collapsible="true" :default-open="true">
                <div class="space-y-4">
                    <div>
                        <label class="block text-[13px] text-mistral-slate mb-2">{{ t('shifts.work_days') }}</label>
                        <div class="flex items-center gap-4 flex-wrap p-3 bg-mistral-surface rounded-lg">
                            <FormSwitch
                                v-for="day in dayKeys"
                                :key="'work_' + day.key"
                                v-model="form.work_days_json[day.key]"
                                :label="day.label"
                                :name="'work_' + day.key"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] text-mistral-slate mb-2">{{ t('shifts.weekend_days') }}</label>
                        <div class="flex items-center gap-4 flex-wrap p-3 bg-mistral-surface rounded-lg">
                            <FormSwitch
                                v-for="day in dayKeys"
                                :key="'weekend_' + day.key"
                                v-model="form.weekend_days_json[day.key]"
                                :label="day.label"
                                :name="'weekend_' + day.key"
                            />
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormSection v-if="form.type === 'hours'" :title="t('shifts.hours_settings')" icon="fas fa-hourglass-half" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.required_hours"
                        :label="t('shifts.required_hours')"
                        name="required_hours"
                        type="number"
                        step="0.01"
                        min="0"
                        :error="errorFor('required_hours')"
                    />
                    <FormSelect
                        v-model="form.period_type"
                        :label="t('shifts.period_type')"
                        name="period_type"
                        :options="periodTypeOptions"
                        :error="errorFor('period_type')"
                    />
                </div>
            </FormSection>

            <FormSection :title="t('shifts.options')" icon="fas fa-cog" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <FormSwitch v-model="form.overtime_enabled" :label="t('shifts.overtime_enabled')" name="overtime_enabled" />
                    <FormSwitch v-model="form.fingerprint_enabled" :label="t('shifts.fingerprint_enabled')" name="fingerprint_enabled" />
                    <FormSwitch v-model="form.work_on_holidays" :label="t('shifts.work_on_holidays')" name="work_on_holidays" />
                    <FormSwitch v-model="form.work_on_weekends" :label="t('shifts.work_on_weekends')" name="work_on_weekends" />
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('shift-categories.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
