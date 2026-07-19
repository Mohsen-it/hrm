<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { PageHeader, Card, Badge, DataTable, FormInput } from '@/Components/ui'
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    departmentId: { type: Number, required: true },
    date: { type: String, default: () => new Date().toISOString().split('T')[0] },
    statuses: { type: Array, default: () => [] },
})

const selectedDate = ref(props.date)

function loadDate() {
    router.get(route('schedule-calendar.department', props.departmentId), { date: selectedDate.value }, { preserveState: true, preserveScroll: true })
}

const columns = [
    { key: 'name', label: t('shifts.employee_name') || 'Name', sortable: true, filterable: true },
    { key: 'employee_code', label: t('shifts.code') || 'Code', sortable: true, filterable: true },
    { key: 'is_expected', label: t('shifts.expected_today') || 'Expected' },
    { key: 'has_punch', label: t('shifts.has_punch') || 'Has Punch' },
]
</script>

<template>
    <AppLayout :title="t('shifts.department_calendar')">
        <PageHeader :title="t('shifts.department_calendar')" :description="date">
            <template #actions>
                <FormInput
                    v-model="selectedDate"
                    type="date"
                    name="selected_date"
                    @change="loadDate"
                />
            </template>
        </PageHeader>

        <CalendarLegend class="mb-4" />

        <Card variant="base" padding="none">
            <DataTable
                :columns="columns"
                :data="{ data: statuses }"
                storage-key="department-calendar"
                @search="(q) => loadDate()"
                @page-change="(p) => loadDate()"
                @per-page-change="(p) => loadDate()"
            >
                <template #cell-is_expected="{ item }">
                    <Badge :variant="item.is_expected ? 'success' : 'default'">
                        {{ item.is_expected ? t('shifts.expected') : t('shifts.not_expected') }}
                    </Badge>
                </template>
                <template #cell-has_punch="{ item }">
                    <Badge :variant="item.is_expected && !item.has_punch ? 'danger' : item.has_punch ? 'success' : 'default'">
                        {{ item.has_punch ? t('shifts.present') : item.is_expected ? t('shifts.absent') : '—' }}
                    </Badge>
                </template>
            </DataTable>
        </Card>
    </AppLayout>
</template>
