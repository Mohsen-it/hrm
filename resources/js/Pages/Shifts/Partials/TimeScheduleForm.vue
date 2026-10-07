<script setup>
import { computed, reactive, ref } from 'vue'
import { FormInput, FormSwitch, Button, IconButton, FormSection, FormActions, ContextHelp } from '@/Components/ui'
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
    in_ahead_margin: props.schedule?.in_ahead_margin ?? 0,
    in_above_margin: props.schedule?.in_above_margin ?? 0,
    out_ahead_margin: props.schedule?.out_ahead_margin ?? 0,
    out_above_margin: props.schedule?.out_above_margin ?? 0,
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

function num(value) {
    const n = Number(value)
    return Number.isFinite(n) && n > 0 ? n : 0
}

function toMinutes(time) {
    if (!time || !/^\d{1,2}:\d{2}/.test(time)) return null
    const [h, m] = time.split(':').map(Number)
    return h * 60 + m
}

function fmtClock(mins) {
    const m = ((mins % 1440) + 1440) % 1440
    return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0')
}

function fmtDuration(mins) {
    const n = Math.max(0, Math.round(Number(mins) || 0))
    return Math.floor(n / 60) + ':' + String(n % 60).padStart(2, '0')
}

function minutesHint(value) {
    return t('shifts.minutes') + ' · = ' + fmtDuration(value) + ' ' + t('shifts.hours_short')
}

function edgeHint(value, edge) {
    const base = t('shifts.minutes') + ' · = ' + fmtDuration(value) + ' ' + t('shifts.hours_short')
    return edge === null ? base : base + ' · ' + edge
}

const inMinutes = computed(() => toMinutes(form.in_time))
const outMinutes = computed(() => toMinutes(form.out_time))

const inStart = computed(() => (inMinutes.value === null ? null : fmtClock(inMinutes.value - num(form.in_ahead_margin))))
const inEnd = computed(() => (inMinutes.value === null ? null : fmtClock(inMinutes.value + num(form.in_above_margin))))
const outStart = computed(() => (outMinutes.value === null ? null : fmtClock(outMinutes.value - num(form.out_ahead_margin))))
const outEnd = computed(() => (outMinutes.value === null ? null : fmtClock(outMinutes.value + num(form.out_above_margin))))

const showPreview = computed(() => inStart.value !== null && outStart.value !== null)

// Window edges are picked as clock times but stored as minute offsets from
// the duty time (the engine also derives the next-day departure window from
// integer margins, so absolute strings must never be persisted).
const windowErrors = reactive({ in_ahead: '', in_above: '', out_ahead: '', out_above: '' })

function clockToMargin(clock, anchorMin, ahead, key) {
    const picked = toMinutes(clock)
    if (picked === null) {
        // Cleared input behaves like the old empty number field: zero margin.
        windowErrors[key] = ''
        return 0
    }
    if (anchorMin === null) {
        windowErrors[key] = ''
        return 0
    }
    const margin = ahead ? anchorMin - picked : picked - anchorMin
    if (margin < 0) {
        windowErrors[key] = t('shifts.window_time_invalid')
        return null
    }
    windowErrors[key] = ''
    return margin
}

function edgeModel(anchorComputed, marginKey, ahead, errorKey) {
    return computed({
        get: () => {
            const anchor = anchorComputed.value
            if (anchor === null) return ''
            const margin = num(form[marginKey])
            return fmtClock(ahead ? anchor - margin : anchor + margin)
        },
        set: (clock) => {
            const anchor = anchorComputed.value
            const margin = clockToMargin(clock, anchor, ahead, errorKey)
            if (margin !== null) form[marginKey] = margin
        },
    })
}

const inStartClock = edgeModel(inMinutes, 'in_ahead_margin', true, 'in_ahead')
const inEndClock = edgeModel(inMinutes, 'in_above_margin', false, 'in_above')
const outStartClock = edgeModel(outMinutes, 'out_ahead_margin', true, 'out_ahead')
const outEndClock = edgeModel(outMinutes, 'out_above_margin', false, 'out_above')

const hasWindowErrors = computed(() => Object.values(windowErrors).some(Boolean))
function handleSubmit() {
    if (props.processing || hasWindowErrors.value) return
    emit('submit', {
        name: form.name,
        in_time: form.in_time,
        out_time: form.out_time,
        is_multi_day: form.is_multi_day,
        late_margin: form.late_margin,
        early_margin: form.early_margin,
        in_ahead_margin: Number(form.in_ahead_margin) || 0,
        in_above_margin: Number(form.in_above_margin) || 0,
        out_ahead_margin: Number(form.out_ahead_margin) || 0,
        out_above_margin: Number(form.out_above_margin) || 0,
        breaks: breaks.value,
    })
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <FormSection :title="t('shifts.basic_info')">
            <ContextHelp>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>{{ t('shifts.help_ts_basic_name_t') }}</strong> {{ t('shifts.help_ts_basic_name_d') }}</li>
                    <li><strong>{{ t('shifts.help_ts_basic_in_t') }}</strong> {{ t('shifts.help_ts_basic_in_d') }}</li>
                    <li><strong>{{ t('shifts.help_ts_basic_out_t') }}</strong> {{ t('shifts.help_ts_basic_out_d') }}</li>
                    <li><strong>{{ t('shifts.help_ts_basic_multiday_t') }}</strong> {{ t('shifts.help_ts_basic_multiday_d') }}</li>
                </ul>
            </ContextHelp>
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
            <ContextHelp>
                <p class="mb-2">{{ t('shifts.help_ts_margins_intro') }}</p>
                <ul class="list-disc list-inside space-y-1">
                    <li><strong>{{ t('shifts.help_ts_margins_late_t') }}</strong> {{ t('shifts.help_ts_margins_late_d') }}</li>
                    <li><strong>{{ t('shifts.help_ts_margins_early_t') }}</strong> {{ t('shifts.help_ts_margins_early_d') }}</li>
                    <li><strong>{{ t('shifts.help_ts_margins_win_in_start_t') }}</strong> {{ t('shifts.help_ts_margins_win_in_start_d') }}</li>
                    <li><strong>{{ t('shifts.help_ts_margins_win_in_end_t') }}</strong> {{ t('shifts.help_ts_margins_win_in_end_d') }}</li>
                    <li><strong>{{ t('shifts.help_ts_margins_win_out_start_t') }}</strong> {{ t('shifts.help_ts_margins_win_out_start_d') }}</li>
                    <li><strong>{{ t('shifts.help_ts_margins_win_out_end_t') }}</strong> {{ t('shifts.help_ts_margins_win_out_end_d') }}</li>
                </ul>
            </ContextHelp>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <FormInput
                    v-model="form.late_margin"
                    :label="t('shifts.late_margin')"
                    name="late_margin"
                    type="number"
                    min="0"
                    :hint="minutesHint(form.late_margin)"
                    :error="errors?.late_margin"
                />

                <FormInput
                    v-model="form.early_margin"
                    :label="t('shifts.early_margin')"
                    name="early_margin"
                    type="number"
                    min="0"
                    :hint="minutesHint(form.early_margin)"
                    :error="errors?.early_margin"
                />

                <FormInput
                    v-model="inStartClock"
                    :label="t('shifts.in_ahead_margin')"
                    name="in_ahead_margin"
                    type="time"
                    :hint="edgeHint(form.in_ahead_margin, inStart)"
                    :error="errors?.in_ahead_margin || windowErrors.in_ahead"
                />

                <FormInput
                    v-model="inEndClock"
                    :label="t('shifts.in_above_margin')"
                    name="in_above_margin"
                    type="time"
                    :hint="edgeHint(form.in_above_margin, inEnd)"
                    :error="errors?.in_above_margin || windowErrors.in_above"
                />

                <FormInput
                    v-model="outStartClock"
                    :label="t('shifts.out_ahead_margin')"
                    name="out_ahead_margin"
                    type="time"
                    :hint="edgeHint(form.out_ahead_margin, outStart)"
                    :error="errors?.out_ahead_margin || windowErrors.out_ahead"
                />

                <FormInput
                    v-model="outEndClock"
                    :label="t('shifts.out_above_margin')"
                    name="out_above_margin"
                    type="time"
                    :hint="edgeHint(form.out_above_margin, outEnd)"
                    :error="errors?.out_above_margin || windowErrors.out_above"
                />
            </div>

            <div v-if="showPreview" class="mt-4 p-3 bg-mistral-surface rounded-lg text-[13px] leading-6">
                <div class="font-semibold mb-1">{{ t('shifts.window_preview') }}</div>
                <div><span class="text-mistral-muted">{{ t('shifts.check_in_window') }}:</span> <span dir="ltr">{{ inStart }} – {{ inEnd }}</span></div>
                <div><span class="text-mistral-muted">{{ t('shifts.check_out_window') }}:</span> <span dir="ltr">{{ outStart }} – {{ outEnd }}</span></div>
            </div>
        </FormSection>

        <FormSection :title="t('shifts.breaks')">
            <template #actions>
                <Button type="button" variant="secondary" size="sm" icon="fas fa-plus" @click="addBreak">
                    {{ t('shifts.add_break') }}
                </Button>
            </template>

            <ContextHelp :collapsible="false">
                <p>{{ t('shifts.help_ts_breaks') }}</p>
            </ContextHelp>

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
