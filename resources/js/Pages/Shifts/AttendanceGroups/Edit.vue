<script setup>
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    group: { type: Object, required: true },
});

const form = useForm({
    code: props.group.code,
    name: props.group.name,
});

const submit = () => {
    form.put(route('attendance.groups.update', props.group.id));
};
</script>

<template>
    <Head :title="t('attendance.edit_attendance_group', 'تعديل فئة حضور')" />
    <AppLayout :title="t('attendance.edit_attendance_group', 'تعديل فئة حضور')">
        <PageHeader
            :title="t('attendance.edit_attendance_group', 'تعديل فئة حضور')"
            :description="t('attendance.attendance_group_description', 'تعديل بيانات فئة الحضور')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('attendance.groups.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="space-y-6 max-w-4xl" @submit.prevent="submit">
            <ErrorSummary :errors="form.errors" />

            <FormSection :title="t('attendance.edit_attendance_group', 'فئة الحضور')" icon="fas fa-users" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.code"
                        :label="t('attendance.fields.code')"
                        :error="form.errors.code"
                        required
                        autofocus
                    />

                    <FormInput
                        v-model="form.name"
                        :label="t('attendance.fields.name')"
                        :error="form.errors.name"
                        required
                    />
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.update')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('attendance.groups.index')"
                :saving="form.processing"
            />
        </form>
    </AppLayout>
</template>
