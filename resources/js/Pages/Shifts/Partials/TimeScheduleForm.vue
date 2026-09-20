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

function handleSubmit() {
    if (props.processing) return
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
            <div class="p-4 mb-4 bg-mistral-info-bg border border-mistral-info rounded-lg text-sm text-mistral-info leading-relaxed">
                <p class="font-semibold mb-1">معلومات أساسية</p>
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
            <div class="p-4 mb-4 bg-mistral-warning-bg border border-mistral-warning rounded-lg text-sm text-mistral-warning leading-relaxed">
                <p class="font-semibold mb-1">هوامش التأخير والانصراف المبكر</p>
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
                    type="number"
                    min="0"
                    :hint="t('shifts.minutes')"
                    :error="errors?.in_ahead_margin"
                />

                <FormInput
                    v-model="form.in_above_margin"
                    :label="t('shifts.in_above_margin')"
                    name="in_above_margin"
                    type="number"
                    min="0"
                    :hint="t('shifts.minutes')"
                    :error="errors?.in_above_margin"
                />

                <FormInput
                    v-model="form.out_ahead_margin"
                    :label="t('shifts.out_ahead_margin')"
                    name="out_ahead_margin"
                    type="number"
                    min="0"
                    :hint="t('shifts.minutes')"
                    :error="errors?.out_ahead_margin"
                />

                <FormInput
                    v-model="form.out_above_margin"
                    :label="t('shifts.out_above_margin')"
                    name="out_above_margin"
                    type="number"
                    min="0"
                    :hint="t('shifts.minutes')"
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

            <div class="p-4 mb-4 bg-mistral-success-bg border border-mistral-success rounded-lg text-sm text-mistral-success leading-relaxed">
                <p class="font-semibold mb-1">الاستراحات</p>
                <p>حدد أوقات الاستراحات خلال الدوام. الاستراحة تُخصَم من ساعات العمل الرسمية ولا تُحسب كوقت عمل. يمكنك إضافة عدة استراحات أو ترك هذا القسم فارغاً إذا لم تكن هناك استراحات رسمية.</p>
            </div>

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
