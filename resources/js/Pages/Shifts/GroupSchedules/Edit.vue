<script setup>
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    schedule: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
});

const form = useForm({
    group_id: props.schedule.group_id,
    shift_id: props.schedule.shift_id,
    start_date: props.schedule.start_date,
    end_date: props.schedule.end_date,
});

const submit = () => {
    form.put(route('attendance.group-schedules.update', props.schedule.id));
};
</script>

<template>
    <Head :title="t('attendance.edit_group_schedule', 'تعديل جدول فئة')" />
    <AppLayout :title="t('attendance.edit_group_schedule', 'تعديل جدول فئة')">
        <PageHeader
            :title="t('attendance.edit_group_schedule', 'تعديل جدول فئة')"
            :description="t('attendance.group_schedule_description', 'تعديل بيانات جدول الفئة')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('attendance.group-schedules.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="space-y-6 max-w-4xl" @submit.prevent="submit">
            <ErrorSummary :errors="form.errors" />

            <FormSection :title="t('attendance.edit_group_schedule', 'جدول الفئة')" icon="fas fa-calendar-alt" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        v-model="form.group_id"
                        :label="t('attendance.fields.group')"
                        :options="groups"
                        :error="form.errors.group_id"
                        required
                        autofocus
                    />

                    <FormSelect
                        v-model="form.shift_id"
                        :label="t('attendance.fields.shift')"
                        :options="shifts"
                        :error="form.errors.shift_id"
                        required
                    />

                    <FormInput
                        v-model="form.start_date"
                        :label="t('attendance.fields.start_date')"
                        type="date"
                        :error="form.errors.start_date"
                        required
                    />

                    <FormInput
                        v-model="form.end_date"
                        :label="t('attendance.fields.end_date')"
                        type="date"
                        :error="form.errors.end_date"
                        required
                    />
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.update')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('attendance.group-schedules.index')"
                :saving="form.processing"
            />
        </form>
    </AppLayout>
</template>
