<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Badge from '@/Components/ui/Badge.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    departmentId: { type: Number, required: true },
    date: { type: String, default: () => new Date().toISOString().split('T')[0] },
    statuses: { type: Array, default: () => [] },
});

const selectedDate = ref(props.date);

function loadDate() {
    router.get(route('schedule-calendar.department', props.departmentId), { date: selectedDate.value }, { preserveState: true, preserveScroll: true });
}

const columns = [
    { key: 'name', label: t('shifts.employee_name') || 'Name' },
    { key: 'employee_code', label: t('shifts.code') || 'Code' },
    { key: 'is_expected', label: t('shifts.expected_today') || 'Expected' },
    { key: 'has_punch', label: t('shifts.has_punch') || 'Has Punch' },
];
</script>

<template>
    <AppLayout title="Department Calendar">
        <PageHeader :title="`تقويم القسم - ${date}`">
            <template #actions>
                <input type="date" v-model="selectedDate" @change="loadDate" class="form-input rounded-lg border-gray-300" />
            </template>
        </PageHeader>

        <CalendarLegend />

        <div class="mt-4">
            <DataTable :columns="columns" :data="{ data: statuses }">
                <template #cell-is_expected="{ item }">
                    <Badge :variant="item.is_expected ? 'success' : 'default'">
                        {{ item.is_expected ? '✓ متوقع' : '✗ غير متوقع' }}
                    </Badge>
                </template>
                <template #cell-has_punch="{ item }">
                    <Badge :variant="item.is_expected && !item.has_punch ? 'danger' : item.has_punch ? 'success' : 'default'">
                        {{ item.has_punch ? '✓ موجود' : item.is_expected ? '✗ غائب' : '—' }}
                    </Badge>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
