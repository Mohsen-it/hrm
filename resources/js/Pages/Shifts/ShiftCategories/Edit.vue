<script setup>
import { reactive, ref, computed } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormSwitch from '@/Components/ui/FormSwitch.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    category: { type: Object, required: true },
    timeSchedules: { type: Array, default: () => [] },
});

const normalizeWorkDaysJson = (value) => {
    const defaults = { sunday: false, monday: false, tuesday: false, wednesday: false, thursday: false, friday: false, saturday: false };
    if (!value) return defaults;
    if (Array.isArray(value)) {
        const result = { ...defaults };
        const dayKeyMap = { 0: 'sunday', 1: 'monday', 2: 'tuesday', 3: 'wednesday', 4: 'thursday', 5: 'friday', 6: 'saturday' };
        value.forEach((dayNum) => { const key = dayKeyMap[Number(dayNum)]; if (key) result[key] = true; });
        return result;
    }
    if (typeof value === 'object') return { ...defaults, ...value };
    return defaults;
};

const form = reactive({
    name: props.category?.name || '',
    type: props.category?.type || 'cyclic',
    work_days: props.category?.work_days ?? '',
    rest_days: props.category?.rest_days ?? '',
    work_days_json: normalizeWorkDaysJson(props.category?.work_days_json),
    weekend_days_json: normalizeWorkDaysJson(props.category?.weekend_days_json),
    required_hours: props.category?.required_hours ?? '',
    period_type: props.category?.period_type || 'daily',
    overtime_enabled: props.category?.overtime_enabled ?? false,
    fingerprint_enabled: props.category?.fingerprint_enabled ?? false,
    work_on_holidays: props.category?.work_on_holidays ?? false,
    work_on_weekends: props.category?.work_on_weekends ?? false,
    color: props.category?.color || '#fa520f',
    time_schedule_id: props.category?.time_schedule_id || '',
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

    router.put(route('shift-categories.update', props.category.id), {
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
    <Head :title="t('shifts.edit_category')" />
    <AppLayout :title="t('shifts.edit_category')">
        <PageHeader
            :title="t('shifts.edit_category')"
            :description="category.name"
        >
            <template #actions>
                <Button variant="secondary" :href="route('shift-categories.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit" class="max-w-3xl">
            <section>
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.basic_info') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.name"
                        :label="t('shifts.name')"
                        name="name"
                        :error="errorFor('name')"
                        required
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
            </section>

            <section v-if="form.type === 'cyclic'" class="mt-6">
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.cyclic_settings') }}</h3>
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
            </section>

            <section v-if="form.type === 'weekly'" class="mt-6">
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.weekly_settings') }}</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[13px] text-mistral-slate mb-2">{{ t('shifts.work_days') }}</label>
                        <div class="flex items-center gap-4 flex-wrap p-3 bg-mistral-surface rounded-md">
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
                        <div class="flex items-center gap-4 flex-wrap p-3 bg-mistral-surface rounded-md">
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
            </section>

            <section v-if="form.type === 'hours'" class="mt-6">
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.hours_settings') }}</h3>
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
            </section>

            <section class="mt-6">
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.options') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-4 bg-mistral-surface rounded-md">
                    <FormSwitch v-model="form.overtime_enabled" :label="t('shifts.overtime_enabled')" name="overtime_enabled" />
                    <FormSwitch v-model="form.fingerprint_enabled" :label="t('shifts.fingerprint_enabled')" name="fingerprint_enabled" />
                    <FormSwitch v-model="form.work_on_holidays" :label="t('shifts.work_on_holidays')" name="work_on_holidays" />
                    <FormSwitch v-model="form.work_on_weekends" :label="t('shifts.work_on_weekends')" name="work_on_weekends" />
                </div>
            </section>

            <div class="mt-6 flex items-center justify-start gap-2">
                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                    {{ t('common.save') }}
                </Button>
                <Button variant="secondary" :href="route('shift-categories.index')">
                    {{ t('common.cancel') }}
                </Button>
            </div>
        </Card>
    </AppLayout>
</template>
