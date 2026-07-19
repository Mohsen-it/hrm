<script setup>
import { reactive, computed } from 'vue'
import { FormInput, FormSelect, FormSwitch, Button, Card, FormSection, FormActions } from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    category: { type: Object, default: null },
    timeSchedules: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
    processing: { type: Boolean, default: false },
    withActions: { type: Boolean, default: true },
})

const emit = defineEmits(['submit'])

const defaultWorkDays = () => ({
    sunday: false,
    monday: false,
    tuesday: false,
    wednesday: false,
    thursday: false,
    friday: false,
    saturday: false,
})

const dayKeyMap = { 0: 'sunday', 1: 'monday', 2: 'tuesday', 3: 'wednesday', 4: 'thursday', 5: 'friday', 6: 'saturday' }

const normalizeWorkDaysJson = (value) => {
    if (!value) return defaultWorkDays()
    if (Array.isArray(value)) {
        const result = defaultWorkDays()
        value.forEach((dayNum) => {
            const key = dayKeyMap[Number(dayNum)]
            if (key) result[key] = true
        })
        return result
    }
    if (typeof value === 'object') return { ...defaultWorkDays(), ...value }
    return defaultWorkDays()
}

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
    color: props.category?.color || 'var(--color-mistral-primary)',
    time_schedule_id: props.category?.time_schedule_id || '',
    is_dynamic: props.category?.is_dynamic ?? false,
    anchor_start_date: props.category?.anchor_start_date || '',
})

const typeOptions = computed(() => [
    { value: 'cyclic', label: t('shifts.cyclic') },
    { value: 'weekly', label: t('shifts.weekly') },
    { value: 'hours', label: t('shifts.hours') },
])

const periodTypeOptions = computed(() => [
    { value: 'daily', label: t('shifts.daily') },
    { value: 'weekly', label: t('shifts.weekly_label') },
    { value: 'monthly', label: t('shifts.monthly') },
])

const dayKeys = [
    { key: 'sunday', label: t('shifts.sunday') },
    { key: 'monday', label: t('shifts.monday') },
    { key: 'tuesday', label: t('shifts.tuesday') },
    { key: 'wednesday', label: t('shifts.wednesday') },
    { key: 'thursday', label: t('shifts.thursday') },
    { key: 'friday', label: t('shifts.friday') },
    { key: 'saturday', label: t('shifts.saturday') },
]

const scheduleOptions = computed(() => {
    const items = Array.isArray(props.timeSchedules) ? props.timeSchedules : (props.timeSchedules?.data || [])
    return items.map((s) => ({ value: s.id, label: s.name }))
})

function onSubmit() {
    if (props.processing) return

    let workDaysJson = form.work_days_json
    let weekendDaysJson = form.weekend_days_json

    if (form.type === 'weekly' && workDaysJson && typeof workDaysJson === 'object' && !Array.isArray(workDaysJson)) {
        const dayMap = { sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6 }
        workDaysJson = Object.keys(workDaysJson)
            .filter((k) => workDaysJson[k])
            .map((k) => dayMap[k])
            .filter((v) => v !== undefined)
    }

    if (form.type === 'weekly' && weekendDaysJson && typeof weekendDaysJson === 'object' && !Array.isArray(weekendDaysJson)) {
        const dayMap = { sunday: 0, monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6 }
        weekendDaysJson = Object.keys(weekendDaysJson)
            .filter((k) => weekendDaysJson[k])
            .map((k) => dayMap[k])
            .filter((v) => v !== undefined)
    }

    const payload = {
        name: form.name,
        type: form.type,
        work_days: form.work_days,
        rest_days: form.rest_days,
        work_days_json: workDaysJson,
        weekend_days_json: weekendDaysJson,
        required_hours: form.required_hours,
        period_type: form.period_type,
        overtime_enabled: form.overtime_enabled,
        fingerprint_enabled: form.fingerprint_enabled,
        work_on_holidays: form.work_on_holidays,
        work_on_weekends: form.work_on_weekends,
        color: form.color,
        time_schedule_id: form.time_schedule_id || null,
        is_dynamic: form.is_dynamic,
        anchor_start_date: form.anchor_start_date || null,
    }
    emit('submit', payload)
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="onSubmit">
        <FormSection :title="t('shifts.basic_info')">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                    v-model="form.name"
                    :label="t('shifts.name')"
                    name="name"
                    :error="errors?.name"
                    required
                />

                <FormSelect
                    v-model="form.type"
                    :label="t('shifts.category_type')"
                    name="type"
                    :options="typeOptions"
                    :error="errors?.type"
                    required
                />

                <FormSelect
                    v-model="form.time_schedule_id"
                    :label="t('shifts.time_schedule')"
                    name="time_schedule_id"
                    :options="scheduleOptions"
                    :placeholder="t('shifts.select_time_schedule')"
                    :error="errors?.time_schedule_id"
                />

                <FormInput
                    v-model="form.color"
                    :label="t('shifts.color')"
                    name="color"
                    type="color"
                    :error="errors?.color"
                />
            </div>
        </FormSection>

        <FormSection v-if="form.type === 'cyclic'" :title="t('shifts.cyclic_settings')">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                    v-model="form.work_days"
                    :label="t('shifts.work_days')"
                    name="work_days"
                    type="number"
                    min="1"
                    :error="errors?.work_days"
                />
                <FormInput
                    v-model="form.rest_days"
                    :label="t('shifts.rest_days')"
                    name="rest_days"
                    type="number"
                    min="0"
                    :error="errors?.rest_days"
                />
            </div>
            <div class="mt-4 p-4 bg-mistral-surface rounded-lg space-y-4">
                <FormSwitch
                    v-model="form.is_dynamic"
                    :label="t('shifts.is_dynamic')"
                    name="is_dynamic"
                />
                <FormInput
                    v-if="form.is_dynamic"
                    v-model="form.anchor_start_date"
                    :label="t('shifts.anchor_start_date')"
                    name="anchor_start_date"
                    type="date"
                    :error="errors?.anchor_start_date"
                />
            </div>
        </FormSection>

        <FormSection v-if="form.type === 'weekly'" :title="t('shifts.weekly_settings')">
            <div class="space-y-4">
                <div>
                    <label class="block text-[13px] text-mistral-slate mb-2">
                        {{ t('shifts.work_days') }}
                    </label>
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
                    <label class="block text-[13px] text-mistral-slate mb-2">
                        {{ t('shifts.weekend_days') }}
                    </label>
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

        <FormSection v-if="form.type === 'hours'" :title="t('shifts.hours_settings')">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                    v-model="form.required_hours"
                    :label="t('shifts.required_hours')"
                    name="required_hours"
                    type="number"
                    step="0.01"
                    min="0"
                    :error="errors?.required_hours"
                />
                <FormSelect
                    v-model="form.period_type"
                    :label="t('shifts.period_type')"
                    name="period_type"
                    :options="periodTypeOptions"
                    :error="errors?.period_type"
                />
            </div>
        </FormSection>

        <FormSection :title="t('shifts.options')">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-4 bg-mistral-surface rounded-lg">
                <FormSwitch
                    v-model="form.overtime_enabled"
                    :label="t('shifts.overtime_enabled')"
                    name="overtime_enabled"
                />
                <FormSwitch
                    v-model="form.fingerprint_enabled"
                    :label="t('shifts.fingerprint_enabled')"
                    name="fingerprint_enabled"
                />
                <FormSwitch
                    v-model="form.work_on_holidays"
                    :label="t('shifts.work_on_holidays')"
                    name="work_on_holidays"
                />
                <FormSwitch
                    v-model="form.work_on_weekends"
                    :label="t('shifts.work_on_weekends')"
                    name="work_on_weekends"
                />
            </div>
        </FormSection>

        <FormActions
            v-if="withActions"
            :save-label="t('common.save')"
            :cancel-label="t('common.cancel')"
            :cancel-href="route('shift-categories.index')"
            :saving="processing"
        />
    </form>
</template>
