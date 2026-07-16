<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/StatCard.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    userId: { type: [String, Number], required: true },
    filters: { type: Object, default: () => ({}) },
    report: { type: Object, default: () => ({ totals: {}, by_status: {}, sessions: [] }) },
    overtime: { type: Object, default: () => ({ by_day: [] }) },
});

const from = ref(props.filters?.from ?? new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10));
const to = ref(props.filters?.to ?? new Date().toISOString().slice(0, 10));

function applyFilters() {
    router.get(
        route('attendance.reports.user', props.userId),
        { from: from.value, to: to.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const statusVariant = (status) => {
    return {
        present: 'active',
        late: 'pending',
        early_leave: 'info',
        missing_punch: 'absent',
    }[status] || 'inactive';
};
</script>

<template>
    <AppLayout :title="t('attendance.user_report') + ' #' + userId">
        <PageHeader
            :title="t('attendance.user_report') + ' #' + userId"
            :description="`${report.from} → ${report.to}`"
        >
            <template #actions>
                <Link :href="route('attendance.reports.index')" class="btn btn-ghost">
                    <i class="fas fa-arrow-right"></i>
                    <span>{{ t('attendance.actions.back') }}</span>
                </Link>
            </template>
        </PageHeader>

        <div class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
            <label class="flex items-center gap-2 text-[12px]">
                <span>{{ t('attendance.fields.from') }}</span>
                <input v-model="from" type="date" class="form-input max-w-[170px]" />
            </label>
            <label class="flex items-center gap-2 text-[12px]">
                <span>{{ t('attendance.fields.to') }}</span>
                <input v-model="to" type="date" class="form-input max-w-[170px]" />
            </label>
            <Button variant="primary" icon="fas fa-search" @click="applyFilters">
                {{ t('common.search') }}
            </Button>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <StatCard :title="t('attendance.fields.work_minutes')" :value="report.totals?.work_minutes || 0" color="success" icon="fas fa-briefcase" />
            <StatCard :title="t('attendance.fields.late_minutes')" :value="report.totals?.late_minutes || 0" color="warning" icon="fas fa-clock" />
            <StatCard :title="t('attendance.fields.overtime_minutes')" :value="report.totals?.overtime_minutes || 0" color="info" icon="fas fa-hourglass-half" />
            <StatCard :title="t('attendance.reports_page.absent_days')" :value="report.totals?.days_absent || 0" color="danger" icon="fas fa-user-times" />
        </div>

        <div class="card p-4 mb-4">
            <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                {{ t('attendance.fields.status') }}
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-2 text-[12px]">
                <div v-for="(v, k) in (report.by_status || {})" :key="k" class="p-2 rounded bg-[var(--color-surface-1)]">
                    <div class="font-semibold">{{ t(`attendance.status.${k}`, k) }}</div>
                    <div>{{ v }}</div>
                </div>
            </div>
        </div>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-[var(--color-ink)]">
            {{ t('attendance.sessions') }}
        </h3>
        <div class="card p-4 overflow-x-auto">
            <table class="w-full text-right border-collapse text-[13px]">
                <thead>
                    <tr class="bg-[var(--color-surface-1)]">
                        <th class="px-2 py-2">{{ t('attendance.fields.attendance_date') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.fields.check_in_at') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.fields.check_out_at') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.fields.work_human') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.fields.late_human') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.fields.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in report.sessions" :key="s.id" class="border-b border-[var(--color-hairline)]">
                        <td class="px-2 py-2" dir="ltr">{{ s.attendance_date }}</td>
                        <td class="px-2 py-2" dir="ltr">{{ s.check_in_at || '—' }}</td>
                        <td class="px-2 py-2" dir="ltr">{{ s.check_out_at || '—' }}</td>
                        <td class="px-2 py-2">{{ s.work_minutes || 0 }}m</td>
                        <td class="px-2 py-2">{{ s.late_minutes || 0 }}m</td>
                        <td class="px-2 py-2">
                            <Badge
                                :text="t(`attendance.status.${s.status}`, s.status)"
                                :variant="statusVariant(s.status)"
                            />
                        </td>
                    </tr>
                    <tr v-if="!report.sessions || report.sessions.length === 0">
                        <td colspan="6" class="text-center text-[var(--color-ink-muted)] py-4">—</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
