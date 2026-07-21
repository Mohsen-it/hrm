<script setup>
import { reactive, ref } from 'vue'
import { FormInput, FormSwitch, Button, IconButton, FormSection, FormActions } from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    schedule: { type: Object, default: null },
    errors: { type: Object, default: () => ({}) },
    processing: { type: Boolean, default: false },
    withActions: { type: Boolean, default: true },
})

const emit = defineEmits(['submit'])

const form = reactive({
    name: props.schedule?.name || '',
    in_time: props.schedule?.in_time ? String(props.schedule.in_time).slice(0, 5) : '',
    out_time: props.schedule?.out_time ? String(props.schedule.out_time).slice(0, 5) : '',
    is_multi_day: props.schedule?.is_multi_day ?? false,
    late_margin: props.schedule?.late_margin ?? 0,
    early_margin: props.schedule?.early_margin ?? 0,
    in_ahead_margin: props.schedule?.in_ahead_margin ? String(props.schedule.in_ahead_margin).slice(0, 5) : '',
    in_above_margin: props.schedule?.in_above_margin ? String(props.schedule.in_above_margin).slice(0, 5) : '',
    out_ahead_margin: props.schedule?.out_ahead_margin ? String(props.schedule.out_ahead_margin).slice(0, 5) : '',
    out_above_margin: props.schedule?.out_above_margin ? String(props.schedule.out_above_margin).slice(0, 5) : '',
})

const breaks = ref(
    (props.schedule?.breaks && Array.isArray(props.schedule.breaks))
        ? props.schedule.breaks.map((b) => ({
            break_start: b.break_start ? String(b.break_start).slice(0, 5) : '',
            duration: b.duration ?? 0,
        }))
        : [],
)

function addBreak() {
    breaks.value.push({ break_start: '', duration: 0 })
}

function removeBreak(index) {
    breaks.value.splice(index, 1)
}

function handleSubmit() {
    if (props.processing) return
    emit('submit', {
        name: form.name,
        in_time: form.in_time,
        out_time: form.out_time,
        is_multi_day: form.is_multi_day,
        late_margin: form.late_margin,
        early_margin: form.early_margin,
        in_ahead_margin: form.in_ahead_margin,
        in_above_margin: form.in_above_margin,
        out_ahead_margin: form.out_ahead_margin,
        out_above_margin: form.out_above_margin,
        breaks: breaks.value,
    })
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <FormSection :title="t('shifts.basic_info')">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                    v-model="form.name"
                    :label="t('shifts.name')"
                    name="name"
                    :error="errors?.name"
                    required
                />

                <FormInput
                    v-model="form.in_time"
                    :label="t('shifts.in_time')"
                    name="in_time"
                    type="time"
                    :error="errors?.in_time"
                    required
                />

                <FormInput
                    v-model="form.out_time"
                    :label="t('shifts.out_time')"
                    name="out_time"
                    type="time"
                    :error="errors?.out_time"
                    required
                />

                <div class="flex items-end pb-1">
                    <FormSwitch
                        v-model="form.is_multi_day"
                        :label="t('shifts.is_multi_day')"
                        name="is_multi_day"
                    />
                </div>
            </div>
        </FormSection>

        <FormSection :title="t('shifts.margins')">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                    v-model="form.late_margin"
                    :label="t('shifts.late_margin')"
                    name="late_margin"
                    type="number"
                    min="0"
                    :hint="t('shifts.minutes')"
                    :error="errors?.late_margin"
                />

                <FormInput
                    v-model="form.early_margin"
                    :label="t('shifts.early_margin')"
                    name="early_margin"
                    type="number"
                    min="0"
                    :hint="t('shifts.minutes')"
                    :error="errors?.early_margin"
                />

                <FormInput
                    v-model="form.in_ahead_margin"
                    :label="t('shifts.in_ahead_margin')"
                    name="in_ahead_margin"
                    type="time"
                    :error="errors?.in_ahead_margin"
                />

                <FormInput
                    v-model="form.in_above_margin"
                    :label="t('shifts.in_above_margin')"
                    name="in_above_margin"
                    type="time"
                    :error="errors?.in_above_margin"
                />

                <FormInput
                    v-model="form.out_ahead_margin"
                    :label="t('shifts.out_ahead_margin')"
                    name="out_ahead_margin"
                    type="time"
                    :error="errors?.out_ahead_margin"
                />

                <FormInput
                    v-model="form.out_above_margin"
                    :label="t('shifts.out_above_margin')"
                    name="out_above_margin"
                    type="time"
                    :error="errors?.out_above_margin"
                />
            </div>
        </FormSection>

        <FormSection :title="t('shifts.breaks')">
            <template #actions>
                <Button type="button" variant="secondary" size="sm" icon="fas fa-plus" @click="addBreak">
                    {{ t('shifts.add_break') }}
                </Button>
            </template>

            <div
                v-for="(brk, index) in breaks"
                :key="index"
                class="flex items-end gap-3 mb-2 p-3 bg-mistral-surface rounded-lg"
            >
                <FormInput
                    v-model="brk.break_start"
                    :label="t('shifts.break_start')"
                    :name="'break_start_' + index"
                    type="time"
                />
                <FormInput
                    v-model="brk.duration"
                    :label="t('shifts.duration_minutes')"
                    :name="'break_duration_' + index"
                    type="number"
                    min="0"
                />
                <IconButton
                    icon="fas fa-trash"
                    variant="ghost"
                    :aria-label="t('common.delete')"
                    @click="removeBreak(index)"
                />
            </div>

            <p
                v-if="breaks.length === 0"
                class="text-[13px] text-mistral-muted mb-3 italic"
            >
                {{ t('shifts.no_breaks') }}
            </p>
        </FormSection>

        <FormActions
            v-if="withActions"
            :save-label="t('common.save')"
            :cancel-label="t('common.cancel')"
            :cancel-href="route('time-schedules.index')"
            :saving="processing"
        />
    </form>
</template>
