<script setup>
import { Head } from '@inertiajs/vue3';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormSelect, FormSwitch, ErrorSummary, FormSection, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    groupId: { type: Number, required: true },
    users: { type: Array, default: () => [] },
});

const form = useForm({
    emp_id: null,
    enable_attendance: true,
    enable_schedule: true,
    enable_overtime: false,
    enable_holiday: true,
    enable_compensatory: false,
});

const submit = () => {
    form.post(route('attendance.groups.assign-employee', props.groupId));
};
</script>

<template>
    <Head :title="t('attendance.actions.assign_employee', 'تعيين موظف')" />
    <AppLayout :title="t('attendance.actions.assign_employee', 'تعيين موظف')">
        <PageHeader
            :title="t('attendance.actions.assign_employee', 'تعيين موظف')"
            :description="t('attendance.assign_employee_description', 'إضافة موظف لفئة الحضور')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('attendance.groups.show', groupId)">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="form.errors" />

            <FormSection :title="t('attendance.actions.assign_employee', 'تعيين موظف')" :description="t('attendance.assign_employee_description', 'إضافة موظف لفئة الحضور')">
                <div class="max-w-4xl">
                    <FormSelect
                        v-model="form.emp_id"
                        :label="t('attendance.fields.employee')"
                        :options="users"
                        :error="form.errors.emp_id"
                        required
                    />

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <FormSwitch
                            v-model="form.enable_attendance"
                            :label="t('attendance.fields.enable_attendance')"
                        />
                        <FormSwitch
                            v-model="form.enable_schedule"
                            :label="t('attendance.fields.enable_schedule')"
                        />
                        <FormSwitch
                            v-model="form.enable_overtime"
                            :label="t('attendance.fields.enable_overtime')"
                        />
                        <FormSwitch
                            v-model="form.enable_holiday"
                            :label="t('attendance.fields.enable_holiday')"
                        />
                        <FormSwitch
                            v-model="form.enable_compensatory"
                            :label="t('attendance.fields.enable_compensatory')"
                        />
                    </div>
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('attendance.groups.show', groupId)"
                :saving="form.processing"
            />
        </form>
    </AppLayout>
</template>
