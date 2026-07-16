<script setup>
import { reactive, ref } from 'vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormSwitch from '@/Components/ui/FormSwitch.vue';
import Button from '@/Components/ui/Button.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    schedule: { type: Object, default: null },
    errors: { type: Object, default: () => ({}) },
    processing: { type: Boolean, default: false },
    withActions: { type: Boolean, default: true },
});

const emit = defineEmits(['submit']);

const form = reactive({
    name: props.schedule?.name || '',
    in_time: props.schedule?.in_time ? String(props.schedule.in_time).slice(0, 5) : '',
    out_time: props.schedule?.out_time ? String(props.schedule.out_time).slice(0, 5) : '',
    is_multi_day: props.schedule?.is_multi_day ?? false,
    late_margin: props.schedule?.late_margin ?? 0,
    early_margin: props.schedule?.early_margin ?? 0,
});

const breaks = ref(
    (props.schedule?.breaks && Array.isArray(props.schedule.breaks))
        ? props.schedule.breaks.map((b) => ({
            break_start: b.break_start ? String(b.break_start).slice(0, 5) : '',
            duration: b.duration ?? 0,
        }))
        : [],
);

function addBreak() {
    breaks.value.push({ break_start: '', duration: 0 });
}

function removeBreak(index) {
    breaks.value.splice(index, 1);
}

function handleSubmit() {
    if (props.processing) return;
    emit('submit', {
        name: form.name,
        in_time: form.in_time,
        out_time: form.out_time,
        is_multi_day: form.is_multi_day,
        late_margin: form.late_margin,
        early_margin: form.early_margin,
        breaks: breaks.value,
    });
}
</script>

<template>
    <form @submit.prevent="handleSubmit" class="space-y-6">
        <section>
            <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">
                {{ t('shifts.basic_info') }}
            </h3>
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
        </section>

        <section>
            <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">
                {{ t('shifts.margins') }}
            </h3>
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
            </div>
        </section>

        <section>
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-[14px] text-mistral-ink font-medium">
                    {{ t('shifts.breaks') }}
                </h3>
                <Button type="button" variant="secondary" size="sm" icon="fas fa-plus" @click="addBreak">
                    {{ t('shifts.add_break') }}
                </Button>
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

            <p
                v-if="breaks.length === 0"
                class="text-[13px] text-mistral-muted mb-3 italic"
            >
                {{ t('shifts.no_breaks') }}
            </p>
        </section>

        <div
            v-if="withActions"
            class="flex items-center justify-end gap-2 pt-4 border-t border-mistral-hairline"
        >
            <slot name="cancel">
                <Button variant="secondary" :href="route('time-schedules.index')">
                    {{ t('common.cancel') }}
                </Button>
            </slot>
            <Button
                type="submit"
                variant="primary"
                :loading="processing"
                icon="fas fa-save"
            >
                {{ t('common.save') }}
            </Button>
        </div>
    </form>
</template>
