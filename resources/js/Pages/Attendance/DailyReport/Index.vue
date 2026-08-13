<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, StatCard, FormInput, FormSelect, DataTable, Badge } from '@/Components/ui';

const props = defineProps({
    report: { type: Object, default: () => ({ rows: [], stats: {} }) },
    filters: { type: Object, default: () => ({}) },
    branches: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
});

const date = ref(props.filters.date || new Date().toISOString().slice(0, 10));
const cutoffTime = ref(props.filters.cutoff_time || '09:00');
const branchId = ref(props.filters.branch_id || '');
const departmentId = ref(props.filters.department_id || '');
const userId = ref(props.filters.user_id || '');
const status = ref(props.filters.status || '');
const statusOptions = [
    { value: '', label: 'كل الحالات' }, { value: 'absent', label: 'الغياب' },
    { value: 'late', label: 'التأخير' }, { value: 'leave', label: 'الإجازات' },
    { value: 'mission', label: 'المهمات' }, { value: 'no_fingerprint', label: 'غير المسجلين بالبصمة' },
    { value: 'incomplete', label: 'عدم الالتزام بالبصمة' }, { value: 'holiday', label: 'إجازة رسمية' },
];
const data = computed(() => ({ data: props.report.rows || [], links: [] }));
const columns = [
    { key: 'name', label: 'الاسم الثلاثي' }, { key: 'employee_code', label: 'رمز الموظف' },
    { key: 'department_name', label: 'القسم' }, { key: 'status_label', label: 'الحالة' },
    { key: 'check_in', label: 'الدخول' }, { key: 'check_out', label: 'الخروج' },
    { key: 'late_minutes', label: 'دقائق التأخر' }, { key: 'notes', label: 'الملاحظات' },
];

function filterParams() { return { date: date.value, cutoff_time: cutoffTime.value, branch_id: branchId.value || undefined, department_id: departmentId.value || undefined, user_id: userId.value || undefined, status: status.value || undefined }; }
function applyFilters() { router.get(route('attendance.daily-summaries.daily-report'), filterParams(), { preserveState: true, replace: true }); }
function exportReport() { window.location.href = route('attendance.daily-summaries.daily-report.export', filterParams()); }
function badgeVariant(status) { return ({ present: 'active', late: 'warning', absent: 'absent', leave: 'info', mission: 'primary', incomplete: 'warning', no_fingerprint: 'neutral', rest: 'neutral', holiday: 'neutral' }[status] || 'neutral'); }
</script>

<template>
    <AppLayout title="التقرير اليومي">
        <PageHeader title="التقرير اليومي" description="ملخص الحضور والغياب والإجازات والمهمات وحالات البصمة حسب اليوم المحدد">
            <template #actions><Button variant="primary" icon="fas fa-file-word" @click="exportReport">تصدير Word</Button></template>
        </PageHeader>

        <Card variant="cream-soft" class="mb-5">
            <div class="flex flex-wrap items-end gap-4">
                <FormInput v-model="date" type="date" label="تاريخ التقرير" />
                <FormInput v-model="cutoffTime" type="time" label="ساعة اعتبار التأخر" />
                <FormSelect v-model="branchId" label="الفرع" :options="[{ value: '', label: 'كل الفروع' }, ...branches]" />
                <FormSelect v-model="departmentId" label="القسم" :options="[{ value: '', label: 'كل الأقسام' }, ...departments]" />
                <FormSelect v-model="userId" label="الموظف" :options="[{ value: '', label: 'كل الموظفين' }, ...users]" />
                <FormSelect v-model="status" label="نوع التقرير" :options="statusOptions" />
                <Button variant="primary" icon="fas fa-search" @click="applyFilters">عرض التقرير</Button>
            </div>
        </Card>

        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-5">
            <StatCard label="الإجمالي" :value="report.stats.total || 0" color="info" icon="fas fa-users" />
            <StatCard label="حاضرون" :value="report.stats.present || 0" color="success" icon="fas fa-user-check" />
            <StatCard label="متأخرون" :value="report.stats.late || 0" color="warning" icon="fas fa-clock" />
            <StatCard label="غياب" :value="report.stats.absent || 0" color="danger" icon="fas fa-user-xmark" />
            <StatCard label="إجازات" :value="report.stats.leave || 0" color="info" icon="fas fa-umbrella-beach" />
            <StatCard label="مهمات سفر" :value="report.stats.mission || 0" color="warning" icon="fas fa-briefcase" />
            <StatCard label="بصمة ناقصة" :value="report.stats.incomplete || 0" color="warning" icon="fas fa-fingerprint" />
        </div>

        <Card variant="base" padding="none" class="overflow-hidden">
            <div class="px-5 py-3 text-[12px] text-mistral-steel border-b border-mistral-hairline-soft">
                عدم الالتزام بالبصمة يظهر فقط للموظف المتوقع دوامه، بعد انتهاء نافذة الخروج المحددة في جدول الوقت المرتبط بالدورية.
            </div>
            <DataTable :columns="columns" :data="data" storage-key="attendance-daily-report" :enable-search="true" :enable-filters="false" :enable-export="false" :enable-pagination="false">
                <template #cell-status_label="{ row }"><Badge :text="row.status_label" :variant="badgeVariant(row.status)" dot /></template>
                <template #cell-check_in="{ row }"><span dir="ltr">{{ row.check_in || '—' }}</span></template>
                <template #cell-check_out="{ row }"><span dir="ltr">{{ row.check_out || '—' }}</span></template>
                <template #cell-notes="{ row }"><span class="text-[12px] text-mistral-steel">{{ row.notes || '—' }}</span></template>
            </DataTable>
        </Card>
    </AppLayout>
</template>
