<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/StatCard.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    kpis: { type: Object, default: () => ({}) },
    months: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
});

const year = ref(props.filters?.year ?? new Date().getFullYear());

function applyFilters() {
    router.get(
        route('attendance.reports.yearly'),
        { year: year.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('attendance.yearly_page.title')">
        <PageHeader
            :title="t('attendance.yearly_page.title')"
            :description="t('attendance.yearly_page.description')"
        />

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>

        <div class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
            <label class="flex items-center gap-2 text-[12px]">
                <span>{{ t('attendance.fields.year') }}</span>
                <input v-model.number="year" type="number" min="2000" max="2100" class="form-input max-w-[120px]" />
            </label>
            <Button variant="primary" icon="fas fa-search" @click="applyFilters">
                {{ t('common.search') }}
            </Button>
        </div>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-[var(--color-ink)]">
            {{ t('attendance.yearly_page.yearly_kpis') }}
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <StatCard :label="t('attendance.kpis.total')" :value="kpis.totals?.records || 0" color="info" icon="fas fa-database" />
            <StatCard :label="t('attendance.yearly_page.working_days')" :value="kpis.working_days || 0" color="info" icon="fas fa-calendar-day" />
            <StatCard :label="t('attendance.monthly_page.work_minutes')" :value="kpis.totals?.work_minutes || 0" color="success" icon="fas fa-briefcase" />
            <StatCard :label="t('attendance.monthly_page.overtime_minutes')" :value="kpis.totals?.overtime_minutes || 0" color="warning" icon="fas fa-hourglass-half" />
        </div>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-[var(--color-ink)]">
            {{ t('attendance.yearly_page.monthly_breakdown') }}
        </h3>
        <div class="card p-4 mb-6 overflow-x-auto">
            <table class="w-full text-right border-collapse text-[13px]">
                <thead>
                    <tr class="bg-[var(--color-surface-1)]">
                        <th class="px-2 py-2">{{ t('attendance.fields.month') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.monthly_page.records') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.kpis.present') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.kpis.absent') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.kpis.late') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.monthly_page.work_minutes') }}</th>
                        <th class="px-2 py-2">{{ t('attendance.monthly_page.overtime_minutes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="m in months" :key="m.month" class="border-b border-[var(--color-hairline)]">
                        <td class="px-2 py-2" dir="ltr">{{ String(m.month).padStart(2, '0') }}</td>
                        <td class="px-2 py-2">{{ m.records }}</td>
                        <td class="px-2 py-2">{{ m.present }}</td>
                        <td class="px-2 py-2">{{ m.absent }}</td>
                        <td class="px-2 py-2">{{ m.late }}</td>
                        <td class="px-2 py-2">{{ m.work_minutes }}</td>
                        <td class="px-2 py-2">{{ m.overtime_minutes }}</td>
                    </tr>
                    <tr v-if="months.length === 0">
                        <td colspan="7" class="text-center text-[var(--color-ink-muted)] py-4">—</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="card p-4">
                <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                    {{ t('attendance.yearly_page.user_table') }}
                </h3>
                <table class="w-full text-right border-collapse text-[13px]">
                    <thead>
                        <tr class="bg-[var(--color-surface-1)]">
                            <th class="px-2 py-2">{{ t('attendance.fields.user') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.present_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.absent_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.late_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.monthly_page.work_minutes') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.monthly_page.overtime_minutes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.user_id" class="border-b border-[var(--color-hairline)]">
                            <td class="px-2 py-2">{{ u.name }}</td>
                            <td class="px-2 py-2">{{ u.present_days }}</td>
                            <td class="px-2 py-2">{{ u.absent_days }}</td>
                            <td class="px-2 py-2">{{ u.late_days }}</td>
                            <td class="px-2 py-2">{{ u.work_minutes }}</td>
                            <td class="px-2 py-2">{{ u.overtime_minutes }}</td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td colspan="6" class="text-center text-[var(--color-ink-muted)] py-4">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card p-4">
                <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                    {{ t('attendance.yearly_page.department_table') }}
                </h3>
                <table class="w-full text-right border-collapse text-[13px]">
                    <thead>
                        <tr class="bg-[var(--color-surface-1)]">
                            <th class="px-2 py-2">{{ t('attendance.fields.department') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.employees') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.present_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.absent_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.late_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.monthly_page.overtime_minutes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in departments" :key="d.department_id" class="border-b border-[var(--color-hairline)]">
                            <td class="px-2 py-2">{{ d.department_name || '—' }}</td>
                            <td class="px-2 py-2">{{ d.employees }}</td>
                            <td class="px-2 py-2">{{ d.present_days }}</td>
                            <td class="px-2 py-2">{{ d.absent_days }}</td>
                            <td class="px-2 py-2">{{ d.late_days }}</td>
                            <td class="px-2 py-2">{{ d.overtime_minutes }}</td>
                        </tr>
                        <tr v-if="departments.length === 0">
                            <td colspan="6" class="text-center text-[var(--color-ink-muted)] py-4">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
