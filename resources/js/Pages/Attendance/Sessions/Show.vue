<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    session: { type: Object, required: true },
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
    }[status] || 'inactive';
};

const sessionTypeVariant = (type) => {
    return {
        normal: 'active',
        overtime: 'overtime',
        make_up: 'info',
    }[type] || 'inactive';
};

const fields = computed(() => [
    { label: t('attendance.fields.user'), value: props.session.user ? `${props.session.user.name} (${props.session.user.employee_code || ''})` : '—' },
    { label: t('attendance.fields.shift'), value: props.session.shift ? props.session.shift.shift_name : '—' },
    { label: t('attendance.fields.attendance_date'), value: props.session.attendance_date || '—' },
    { label: t('attendance.fields.check_in_at'), value: props.session.check_in_at || '—' },
    { label: t('attendance.fields.check_out_at'), value: props.session.check_out_at || '—' },
    { label: t('attendance.fields.expected_check_in'), value: props.session.expected_check_in || '—' },
    { label: t('attendance.fields.expected_check_out'), value: props.session.expected_check_out || '—' },
    { label: t('attendance.fields.work_minutes'), value: props.session.work_human || `0 ${t('attendance.units.minutes_short')}` },
    { label: t('attendance.fields.break_minutes'), value: `${props.session.break_minutes || 0} ${t('attendance.units.minutes')}` },
    { label: t('attendance.fields.late_minutes'), value: props.session.late_human || `0 ${t('attendance.units.minutes_short')}` },
    { label: t('attendance.fields.early_leave_minutes'), value: `${props.session.early_leave_minutes || 0} ${t('attendance.units.minutes')}` },
    { label: t('attendance.fields.overtime_minutes'), value: props.session.overtime_human || `0 ${t('attendance.units.minutes_short')}` },
    { label: t('attendance.fields.source'), value: t(`attendance.source.${props.session.source}`, props.session.source) },
    { label: t('attendance.fields.notes'), value: props.session.notes || '—' },
]);
</script>

<template>
    <AppLayout :title="t('attendance.session')">
        <PageHeader
            :title="t('attendance.session') + ' #' + session.id"
            :description="t('attendance.show_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('attendance.sessions.index')">
                    {{ t('attendance.actions.back') }}
                </Button>
                <Button variant="primary" icon="fas fa-edit" :href="route('attendance.sessions.edit', session.id)">
                    {{ t('attendance.actions.edit') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Card variant="base" padding="none" class="lg:col-span-2">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                        {{ t('attendance.session') }}
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
                                :text="t(`attendance.status.${session.status}`, session.status)"
                                :variant="statusVariant(session.status)"
                            />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[12px] text-mistral-steel">
                                {{ t('attendance.fields.session_type') }}
                            </span>
                            <Badge
                                :text="t(`attendance.session_type.${session.session_type}`, session.session_type)"
                                :variant="sessionTypeVariant(session.session_type)"
                            />
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[12px] text-mistral-steel">
                                {{ t('attendance.fields.is_open') }}
                            </span>
                            <span class="text-[13px] font-semibold">
                                {{ session.is_open ? t('common.yes') : t('common.no') }}
                            </span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-[12px] text-mistral-steel">
                                {{ t('attendance.fields.is_complete') }}
                            </span>
                            <span class="text-[13px] font-semibold">
                                {{ session.is_complete ? t('common.yes') : t('common.no') }}
                            </span>
                        </div>
                        <div v-if="session.raw_log" class="flex items-center justify-between">
                            <span class="text-[12px] text-mistral-steel">
                                {{ t('attendance.fields.raw_log') }}
                            </span>
                            <Button
                                variant="link"
                                :href="route('attendance.raw-logs.show', session.raw_log.id)"
                            >
                                #{{ session.raw_log.id }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
