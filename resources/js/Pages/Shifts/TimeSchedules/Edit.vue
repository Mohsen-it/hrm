<script setup>
import { reactive, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormSwitch from '@/Components/ui/FormSwitch.vue';
import IconButton from '@/Components/ui/IconButton.vue';
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
    router.put(route('time-schedules.update', props.schedule.id), { ...form, breaks: breaks.value }, {
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
    <Head :title="t('shifts.edit_schedule')" />
    <AppLayout :title="t('shifts.edit_schedule')">
        <PageHeader
            :title="t('shifts.edit_schedule')"
            :description="schedule.name"
        >
            <template #actions>
                <Button variant="secondary" :href="route('time-schedules.index')">{{ t('common.back') }}</Button>
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
            </section>

            <section class="mt-6">
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.margins') }}</h3>
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
                </div>
            </section>

            <section class="mt-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[14px] text-mistral-ink font-medium">{{ t('shifts.breaks') }}</h3>
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

                <p v-if="breaks.length === 0" class="text-[13px] text-mistral-muted mb-3 italic">
                    {{ t('shifts.no_breaks') }}
                </p>
            </section>

            <div class="mt-6 flex items-center justify-start gap-2">
                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                    {{ t('common.save') }}
                </Button>
                <Button variant="secondary" :href="route('time-schedules.index')">
                    {{ t('common.cancel') }}
                </Button>
            </div>
        </Card>
    </AppLayout>
</template>
