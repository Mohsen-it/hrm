<script setup>
import { ref, computed } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
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
    trend: { type: Array, default: () => [] },
    departmentComparison: { type: Array, default: () => [] },
    topLate: { type: Array, default: () => [] },
});

const from = ref(props.filters?.from ?? new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10));
const to = ref(props.filters?.to ?? new Date().toISOString().slice(0, 10));
const date = ref(props.filters?.date ?? new Date().toISOString().slice(0, 10));

function applyFilters() {
    router.get(
        route('attendance.reports.index'),
        { from: from.value, to: to.value, date: date.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const trendMax = computed(() => {
    return Math.max(1, ...props.trend.map((d) => (d.present || 0) + (d.absent || 0) + (d.late || 0)));
});

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('attendance.reports_page.title')">
        <PageHeader
            :title="t('attendance.reports_page.title')"
            :description="t('attendance.reports_page.index_description')"
        />

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>

        <div class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
            <label class="flex items-center gap-2 text-[12px]">
                <span>{{ t('attendance.fields.date') }}</span>
                <input v-model="date" type="date" class="form-input max-w-[170px]" />
            </label>
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

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-[var(--color-ink)]">
            {{ t('attendance.reports_page.daily_kpis') }}
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <StatCard :label="t('attendance.kpis.present')" :value="kpis.present || 0" color="success" icon="fas fa-user-check" />
            <StatCard :label="t('attendance.kpis.late')" :value="kpis.late || 0" color="warning" icon="fas fa-clock" />
            <StatCard :label="t('attendance.kpis.absent')" :value="kpis.absent || 0" color="danger" icon="fas fa-user-times" />
            <StatCard :label="t('attendance.kpis.early_leave')" :value="kpis.early_leave || 0" color="info" icon="fas fa-sign-out-alt" />
            <StatCard :label="t('attendance.kpis.missing_punch')" :value="kpis.missing_punch || 0" color="warning" icon="fas fa-exclamation-triangle" />
            <StatCard :label="t('attendance.kpis.total')" :value="kpis.total || 0" color="info" icon="fas fa-users" />
        </div>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-[var(--color-ink)]">
            {{ t('attendance.reports_page.daily_trend') }}
        </h3>
        <div class="card p-4 mb-6">
            <div v-if="trend.length === 0" class="text-center text-[var(--color-ink-muted)] py-8">
                —
            </div>
            <div v-else class="grid grid-cols-7 gap-2 items-end" style="min-height:160px;">
                <div
                    v-for="d in trend"
                    :key="d.date"
                    class="flex flex-col items-center gap-1"
                >
                    <div
                        class="w-full rounded-t"
                        :style="{
                            height: ((d.present + d.absent + d.late) / trendMax * 140) + 'px',
                            background: 'var(--color-primary)',
                            opacity: 0.7,
                        }"
                        :title="`${d.date}: ${d.present} / ${d.late} / ${d.absent}`"
                    ></div>
                    <span class="text-[10px] text-[var(--color-ink-subtle)]" dir="ltr">
                        {{ d.date.slice(5) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="card p-4">
                <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                    {{ t('attendance.reports_page.department_comparison') }}
                </h3>
                <table class="w-full text-right border-collapse text-[13px]">
                    <thead>
                        <tr class="bg-[var(--color-surface-1)]">
                            <th class="px-2 py-2">{{ t('attendance.fields.department') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.employees') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.present_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.late_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.absent_days') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.overtime_minutes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in departmentComparison" :key="d.department_id" class="border-b border-[var(--color-hairline)]">
                            <td class="px-2 py-2">{{ d.department_name || '—' }}</td>
                            <td class="px-2 py-2">{{ d.employees }}</td>
                            <td class="px-2 py-2">{{ d.present_days }}</td>
                            <td class="px-2 py-2">{{ d.late_days }}</td>
                            <td class="px-2 py-2">{{ d.absent_days }}</td>
                            <td class="px-2 py-2">{{ d.overtime_minutes }}</td>
                        </tr>
                        <tr v-if="departmentComparison.length === 0">
                            <td colspan="6" class="text-center text-[var(--color-ink-muted)] py-4">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card p-4">
                <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                    {{ t('attendance.reports_page.top_late') }}
                </h3>
                <table class="w-full text-right border-collapse text-[13px]">
                    <thead>
                        <tr class="bg-[var(--color-surface-1)]">
                            <th class="px-2 py-2">{{ t('attendance.reports_page.employee_name') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.late_minutes') }}</th>
                            <th class="px-2 py-2">{{ t('attendance.reports_page.absent_days') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in topLate" :key="row.user_id" class="border-b border-[var(--color-hairline)]">
                            <td class="px-2 py-2">
                                <Link :href="route('attendance.reports.user', row.user_id)" class="text-[var(--color-primary)] font-semibold">
                                    {{ row.name || ('#' + row.user_id) }}
                                </Link>
                            </td>
                            <td class="px-2 py-2">{{ row.late_minutes }}</td>
                            <td class="px-2 py-2">{{ row.absent_days }}</td>
                        </tr>
                        <tr v-if="topLate.length === 0">
                            <td colspan="3" class="text-center text-[var(--color-ink-muted)] py-4">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
