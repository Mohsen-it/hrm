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
import { PageHeader, Button, Card, FormInput, FormSwitch, FormSection, FormActions, IconButton, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

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

function addBreak() {
    breaks.value.push({ break_start: '', duration: 0 });
}

function removeBreak(index) {
    breaks.value.splice(index, 1);
}

function submit() {
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
                <div class="p-4 mb-4 bg-mistral-cream-soft border border-mistral-primary/20 rounded-lg text-sm text-mistral-ink leading-relaxed">
                    <p class="font-semibold mb-2">ما هذا القسم؟</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>اسم الجدول:</strong> اسم يُميّز هذا الجدول عن غيره (مثال: دورية إدارية، دورية مصانع).</li>
                        <li><strong>وقت الحضور:</strong> الوقت الرسمي لبدء الدوام. أي بصمة بعد هذا الوقت + هامش التأخير تُحسب كتأخير.</li>
                        <li><strong>وقت الانصراف:</strong> الوقت الرسمي لانتهاء الدوام. أي بصمة قبل هذا الوقت - هامش المغادرة تُحسب كانصراف مبكر.</li>
                        <li><strong>دوام متواصل:</strong> فعّله إذا كان الدوام يمتد لأكثر من يوم (مثل 48 ساعة متواصلة ثم 48 ساعة راحة). عند التفعيل، يُحسب الانصراف صباح أول يوم راحة.</li>
                    </ul>
                </div>
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
                <div class="p-4 mb-4 bg-mistral-cream-soft border border-mistral-primary/20 rounded-lg text-sm text-mistral-ink leading-relaxed">
                    <p class="font-semibold mb-2">ما هذا القسم؟</p>
                    <p class="mb-2">هذه الإعدادات تحدد السماحية الزمنية للموظف عند التأخير أو المغادرة المبكرة.</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li><strong>هامش التأخير:</strong>عدد الدقائق المسموح بالتأخير فيها بعد وقت الحضور دون احتسابها كمخالفة. مثال: إذا كان الحضور 8:00 والهامش 30. فأي بصمة بين 8:00 و 8:30 تُحسب كحضور عادي (ليست تأخيراً).</li>
                        <li><strong>هامش المغادرة المبكرة:</strong>عدد الدقائق المسموح بالمغادرة فيها قبل وقت الانصراف دون احتسابها كمخالفة. مثال: إذا كان الانصراف 3:00 والهامش 30. فأي بصمة بين 2:30 و 3:00 تُحسب كخروج عادي (ليست مغادرة مبكرة).</li>
                        <li><strong>بداية نافذة الدخول:</strong>كم دقيقة قبل وقت الحضور تبدأ نافذة قبول البصمة (البصمة قبلها لا تُسجل).</li>
                        <li><strong>نهاية نافذة الدخول:</strong>كم دقيقة بعد وقت الحضور تنتهي نافذة قبول بصمة الدخول.</li>
                        <li><strong>بداية نافذة الخروج:</strong>كم دقيقة بعد وقت الانصراف تبدأ نافذة قبول بصمة الخروج.</li>
                        <li><strong>نهاية نافذة الخروج:</strong>كم دقيقة بعد الانصراف تنتهي نافذة قبول بصمة الخروج (مهم للدوام المتواصل).</li>
                    </ul>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.late_margin"
                        :label="t('shifts.late_margin')"
                        name="late_margin"
                        type="number"
                        min="0"
                        :hint="t('shifts.minutes')"
                        :error="errorFor('late_margin')"
                    />
                    <FormInput
                        v-model="form.early_margin"
                        :label="t('shifts.early_margin')"
                        name="early_margin"
                        type="number"
                        min="0"
                        :hint="t('shifts.minutes')"
                        :error="errorFor('early_margin')"
                    />
                    <FormInput
                        v-model="form.in_ahead_margin"
                        :label="t('shifts.in_ahead_margin')"
                        name="in_ahead_margin"
                        type="number"
                        min="0"
                        :hint="t('shifts.minutes')"
                        :error="errorFor('in_ahead_margin')"
                    />
                    <FormInput
                        v-model="form.in_above_margin"
                        :label="t('shifts.in_above_margin')"
                        name="in_above_margin"
                        type="number"
                        min="0"
                        :hint="t('shifts.minutes')"
                        :error="errorFor('in_above_margin')"
                    />
                    <FormInput
                        v-model="form.out_ahead_margin"
                        :label="t('shifts.out_ahead_margin')"
                        name="out_ahead_margin"
                        type="number"
                        min="0"
                        :hint="t('shifts.minutes')"
                        :error="errorFor('out_ahead_margin')"
                    />
                    <FormInput
                        v-model="form.out_above_margin"
                        :label="t('shifts.out_above_margin')"
                        name="out_above_margin"
                        type="number"
                        min="0"
                        :hint="t('shifts.minutes')"
                        :error="errorFor('out_above_margin')"
                    />
                </div>
            </FormSection>

            <FormSection :title="t('shifts.breaks')" icon="fas fa-coffee" :collapsible="true" :default-open="true">
                <template #header-actions>
                    <Button type="button" variant="secondary" size="sm" icon="fas fa-plus" @click="addBreak">
                        {{ t('shifts.add_break') }}
                    </Button>
                </template>

                <div class="p-4 mb-4 bg-mistral-cream-soft border border-mistral-primary/20 rounded-lg text-sm text-mistral-ink leading-relaxed">
                    <p class="font-semibold mb-1">ما هذا القسم؟</p>
                    <p>حدد أوقات الاستراحات خلال الدوام. الاستراحة تُخصَم من ساعات العمل الرسمية ولا تُحسب كوقت عمل. يمكنك إضافة عدة استراحات أو ترك هذا القسم فارغاً إذا لم تكن هناك استراحات رسمية.</p>
                </div>

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
