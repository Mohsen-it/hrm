<script setup>
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const form = useForm({
    code: '',
    name: '',
    company_id: null,
});

const submit = () => {
    form.post(route('attendance.groups.store'));
};
</script>

<template>
    <Head :title="t('attendance.create_attendance_group', 'إنشاء فئة حضور')" />
    <AppLayout :title="t('attendance.create_attendance_group', 'إنشاء فئة حضور')">
        <PageHeader
            :title="t('attendance.create_attendance_group', 'إنشاء فئة حضور')"
            :description="t('attendance.attendance_group_description', 'إضافة فئة حضور جديدة')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('attendance.groups.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="space-y-6 max-w-4xl" @submit.prevent="submit">
            <ErrorSummary :errors="form.errors" />

            <FormSection :title="t('attendance.create_attendance_group', 'فئة الحضور')" icon="fas fa-users" :collapsible="true" :default-open="true">
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
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('attendance.groups.index')"
                :saving="form.processing"
            />
        </form>
    </AppLayout>
</template>
