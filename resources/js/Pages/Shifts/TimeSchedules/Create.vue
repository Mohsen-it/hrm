<script setup>
import { reactive, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSwitch, FormSection, FormActions, IconButton, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = reactive({
    name: '',
    in_time: '',
    out_time: '',
    is_multi_day: false,
    late_margin: 0,
    early_margin: 0,
});

const breaks = ref([]);

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
    router.post(route('time-schedules.store'), { ...form, breaks: breaks.value }, {
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
    <Head :title="t('shifts.add_schedule')" />
    <AppLayout :title="t('shifts.add_schedule')">
        <PageHeader
            :title="t('shifts.add_schedule')"
            :description="t('shifts.time_schedules_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('time-schedules.index')">{{ t('common.back') }}</Button>
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
            </FormSection>

            <FormSection :title="t('shifts.breaks')" icon="fas fa-coffee" :collapsible="true" :default-open="true">
                <template #header-actions>
                    <Button type="button" variant="secondary" size="sm" icon="fas fa-plus" @click="addBreak">
                        {{ t('shifts.add_break') }}
                    </Button>
                </template>

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
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('time-schedules.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
