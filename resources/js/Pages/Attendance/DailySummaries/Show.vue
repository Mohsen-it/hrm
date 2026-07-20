<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    summary: { type: Object, required: true },
});

const statusVariant = (status) => {
    return {
        present: 'active',
        late: 'pending',
        early_leave: 'info',
        missing_punch: 'absent',
        absent: 'inactive',
        holiday: 'vacation',
        vacation: 'vacation',
        weekend: 'info',
        rest: 'info',
        unassigned: 'warning',
    }[status] || 'inactive';
};

const fields = computed(() => [
    { label: t('attendance.fields.user'), value: props.summary.user ? `${props.summary.user.name} (${props.summary.user.employee_code || ''})` : '—' },
    { label: t('attendance.fields.shift'), value: props.summary.shift ? props.summary.shift.shift_name : '—' },
    { label: t('attendance.fields.summary_date'), value: props.summary.summary_date || '—' },
    { label: t('attendance.fields.first_check_in_at'), value: props.summary.first_check_in_at || '—' },
    { label: t('attendance.fields.last_check_out_at'), value: props.summary.last_check_out_at || '—' },
    { label: t('attendance.fields.expected_check_in'), value: props.summary.expected_check_in || '—' },
    { label: t('attendance.fields.expected_check_out'), value: props.summary.expected_check_out || '—' },
    { label: t('attendance.fields.sessions_count'), value: props.summary.sessions_count || 0 },
    { label: t('attendance.fields.work_human'), value: props.summary.work_human || '0m' },
    { label: t('attendance.fields.overtime_human'), value: props.summary.overtime_human || '0m' },
    { label: t('attendance.fields.late_human'), value: props.summary.late_human || '0m' },
    { label: t('attendance.fields.is_complete'), value: props.summary.is_complete ? t('common.yes') : t('common.no') },
    { label: t('attendance.fields.calculated_at'), value: props.summary.calculated_at || '—' },
    { label: t('attendance.fields.notes'), value: props.summary.notes || '—' },
]);
</script>

<template>
    <AppLayout :title="t('attendance.daily_summary')">
        <PageHeader
            :title="t('attendance.daily_summary') + ' #' + summary.id"
            :description="t('attendance.show_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('attendance.daily-summaries.index')">
                    {{ t('attendance.actions.back') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Card variant="base" padding="none" class="lg:col-span-2">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                        {{ t('attendance.daily_summary') }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="f in fields"
                            :key="f.label"
                            class="flex items-start justify-between gap-2 py-2 border-b border-mistral-hairline-soft"
                        >
                            <span class="text-[12px] text-mistral-steel">{{ f.label }}</span>
                            <span class="text-[13px] font-semibold text-mistral-ink" dir="ltr">
                                {{ f.value }}
                            </span>
                        </div>
                    </div>
                </div>
            </Card>

            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                        {{ t('attendance.fields.status') }}
                    </h3>
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[12px] text-mistral-steel">
                                {{ t('attendance.fields.status') }}
                            </span>
                            <Badge
                                :text="t(`attendance.status.${summary.status}`, summary.status)"
                                :variant="statusVariant(summary.status)"
                            />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[12px] text-mistral-steel">
                                {{ t('attendance.fields.session_type') }}
                            </span>
                            <span class="text-[13px] font-semibold">
                                {{ t(`attendance.session_type.${summary.session_type}`, summary.session_type) }}
                            </span>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
