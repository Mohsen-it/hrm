<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { reactive, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import { PageHeader, Button, Card, FormInput, FormSwitch, FormSection, FormActions, IconButton, ErrorSummary, ContextHelp } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';
import { useScheduleWindows } from '@/composables/useScheduleWindows';

const { t } = useTranslations();

const props = defineProps({
    schedule: { type: Object, required: true },
});

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
});

const breaks = ref(
    (props.schedule?.breaks && Array.isArray(props.schedule.breaks))
        ? props.schedule.breaks.map((b) => ({
            break_start: b.break_start ? String(b.break_start).slice(0, 5) : '',
            duration: b.duration ?? 0,
        }))
        : [],
);

const errors = ref({});
const processing = ref(false);

const errorFor = (key) => errors.value[key] || '';

const {
    minutesHint, edgeHint, inStart, inEnd, outStart, outEnd, showPreview,
    windowErrors, hasWindowErrors, lateClock, earlyClock,
    inStartClock, inEndClock, outStartClock, outEndClock,
} = useScheduleWindows(form);

function addBreak() {
    breaks.value.push({ break_start: '', duration: 0 });
}

function removeBreak(index) {
    breaks.value.splice(index, 1);
}

function submit() {
    if (hasWindowErrors.value) return
    processing.value = true;
    errors.value = {};
    router.put(route('time-schedules.update', props.schedule.id), {
        ...form,
        in_ahead_margin: Number(form.in_ahead_margin) || 0,
        in_above_margin: Number(form.in_above_margin) || 0,
        out_ahead_margin: Number(form.out_ahead_margin) || 0,
        out_above_margin: Number(form.out_above_margin) || 0,
        breaks: breaks.value,
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


usePageTitle(t('shifts.edit_schedule'));
</script>

<template>
    <Head :title="t('shifts.edit_schedule')" />
    
        <PageHeader
            :title="t('shifts.edit_schedule')"
            :description="schedule.name"
        >
            <template #actions>
                <Button variant="secondary" :href="route('time-schedules.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6 max-w-3xl" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('shifts.basic_info')" icon="fas fa-info-circle" :collapsible="true" :default-open="true">
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
                        :error="errorFor('name')"
                        required
                        autofocus
                    />
                    <FormInput
                        v-model="form.in_time"
                        :label="t('shifts.in_time')"
                        name="in_time"
                        type="time"
                        :error="errorFor('in_time')"
                        required
                    />
                    <FormInput
                        v-model="form.out_time"
                        :label="t('shifts.out_time')"
                        name="out_time"
                        type="time"
                        :error="errorFor('out_time')"
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

            <FormSection :title="t('shifts.margins')" icon="fas fa-arrows-alt-h" :collapsible="true" :default-open="true">
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
                        v-model="lateClock"
                        :label="t('shifts.late_until')"
                        name="late_margin"
                        type="time"
                        :hint="minutesHint(form.late_margin)"
                        :error="errorFor('late_margin') || windowErrors.late"
                    />
                    <FormInput
                        v-model="earlyClock"
                        :label="t('shifts.early_from')"
                        name="early_margin"
                        type="time"
                        :hint="minutesHint(form.early_margin)"
                        :error="errorFor('early_margin') || windowErrors.early"
                    />
                    <FormInput
                        v-model="inStartClock"
                        :label="t('shifts.in_ahead_margin')"
                        name="in_ahead_margin"
                        type="time"
                        :hint="edgeHint(form.in_ahead_margin, inStart)"
                        :error="errorFor('in_ahead_margin') || windowErrors.in_ahead"
                    />
                    <FormInput
                        v-model="inEndClock"
                        :label="t('shifts.in_above_margin')"
                        name="in_above_margin"
                        type="time"
                        :hint="edgeHint(form.in_above_margin, inEnd)"
                        :error="errorFor('in_above_margin') || windowErrors.in_above"
                    />
                    <FormInput
                        v-model="outStartClock"
                        :label="t('shifts.out_ahead_margin')"
                        name="out_ahead_margin"
                        type="time"
                        :hint="edgeHint(form.out_ahead_margin, outStart)"
                        :error="errorFor('out_ahead_margin') || windowErrors.out_ahead"
                    />
                    <FormInput
                        v-model="outEndClock"
                        :label="t('shifts.out_above_margin')"
                        name="out_above_margin"
                        type="time"
                        :hint="edgeHint(form.out_above_margin, outEnd)"
                        :error="errorFor('out_above_margin') || windowErrors.out_above"
                    />
                </div>

                <div v-if="showPreview" class="mt-4 p-3 bg-mistral-surface rounded-lg text-[13px] leading-6">
                    <div class="font-semibold mb-1">{{ t('shifts.window_preview') }}</div>
                    <div><span class="text-mistral-muted">{{ t('shifts.check_in_window') }}:</span> <span dir="ltr">{{ inStart }} – {{ inEnd }}</span></div>
                    <div><span class="text-mistral-muted">{{ t('shifts.check_out_window') }}:</span> <span dir="ltr">{{ outStart }} – {{ outEnd }}</span></div>
                </div>
            </FormSection>

            <FormSection :title="t('shifts.breaks')" icon="fas fa-coffee" :collapsible="true" :default-open="true">
                <template #header-actions>
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
                    class="flex items-end gap-3 mb-2 p-3 bg-mistral-surface rounded-md"
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

                <p v-if="breaks.length === 0" class="text-[13px] text-mistral-muted italic">
                    {{ t('shifts.no_breaks') }}
                </p>
            </FormSection>

            <FormActions
                :save-label="t('common.update')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('time-schedules.index')"
                :saving="processing"
            />
        </form>
    </template>
