<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import StatCard from '@/Components/StatCard.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';
import { useRealtimeAttendance } from '@/composables/useRealtimeAttendance';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    filters: { type: Object, default: () => ({}) },
    live: { type: Array, default: () => [] },
    missing: { type: Array, default: () => [] },
    anomalies: { type: Array, default: () => [] },
    health: { type: Object, default: () => ({}) },
});

const date = ref(props.filters?.date ?? new Date().toISOString().slice(0, 10));
const liveData = ref([...(props.live || [])]);
const missingData = ref([...(props.missing || [])]);
const anomaliesData = ref([...(props.anomalies || [])]);
const healthData = ref({ ...(props.health || {}) });
const punchFeed = ref([]);

const { isConnected, lastPunch, punchCount } = useRealtimeAttendance({
    channel: 'attendance.live',
    event: 'punch.received',
    autoRefresh: true,
    onPunch: (data) => {
        const entry = {
            id: 'punch_' + Date.now() + '_' + punchCount.value,
            punch_time: data.punched_at,
            punch_type: data.punch_type,
            source: data.device?.name || 'device',
            processed: true,
            user: data.user,
            device_user_id: data.user?.employee_code,
        };
        punchFeed.value.unshift(entry);
        if (punchFeed.value.length > 100) {
            punchFeed.value = punchFeed.value.slice(0, 100);
        }
    },
});

const flashSuccess = computed(() => page.props.flash?.success);

const statusVariant = (status) => {
    return {
        present: 'active',
        late: 'pending',
        early_leave: 'info',
        missing_punch: 'absent',
    }[status] || 'inactive';
};

const punchTypeLabel = (type) => {
    return {
        check_in: t('attendance.fields.check_in', 'دخول'),
        check_out: t('attendance.fields.check_out', 'خروج'),
    }[type] || type;
};

const punchTypeVariant = (type) => {
    return {
        check_in: 'active',
        check_out: 'info',
    }[type] || 'inactive';
};

const sourceLabel = (source) => {
    return {
        device_push: 'جهاز بصمة',
        device: 'جهاز',
        adms: 'ADMS',
        manual: 'يدوي',
        api: 'API',
    }[source] || source;
};

function applyFilters() {
    router.get(
        route('attendance.live.index'),
        { date: date.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function runDailyScan() {
    router.post(route('attendance.live.daily-scan'), { date: date.value }, {
        preserveScroll: true,
    });
}

onMounted(() => {
    liveData.value = props.live || [];
    missingData.value = props.missing || [];
    anomaliesData.value = props.anomalies || [];
    healthData.value = props.health || {};
});
</script>

<template>
    <AppLayout :title="t('attendance.live_page.title')">
        <PageHeader
            :title="t('attendance.live_page.title')"
            :description="t('attendance.live_page.description')"
        >
            <template #actions>
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1 px-2 py-1 rounded text-[11px]"
                        :class="isConnected ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                    >
                        <span class="w-2 h-2 rounded-full" :class="isConnected ? 'bg-green-500 animate-pulse' : 'bg-red-500'"></span>
                        {{ isConnected ? 'Live' : 'Offline' }}
                    </span>
                    <Button variant="primary" icon="fas fa-bolt" @click="runDailyScan">
                        {{ t('attendance.actions.daily_scan') }}
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>

        <div class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
            <label class="flex items-center gap-2 text-[12px]">
                <span>{{ t('attendance.fields.date') }}</span>
                <input v-model="date" type="date" class="form-input max-w-[170px]" />
            </label>
            <Button variant="primary" icon="fas fa-search" @click="applyFilters">
                {{ t('common.search') }}
            </Button>
        </div>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-[var(--color-ink)]">
            {{ t('attendance.live_page.health') }}
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <StatCard :label="t('attendance.kpis.live_sessions')" :value="healthData.live_sessions || 0" color="info" icon="fas fa-bolt" />
            <StatCard :label="t('attendance.kpis.missing_checkouts')" :value="healthData.missing_checkouts || 0" color="warning" icon="fas fa-sign-out-alt" />
            <StatCard :label="t('attendance.kpis.unprocessed_raw_logs')" :value="healthData.unprocessed_raw_logs || 0" color="info" icon="fas fa-database" />
            <StatCard :label="t('attendance.kpis.anomalies')" :value="healthData.anomalies || 0" color="danger" icon="fas fa-exclamation-triangle" />
        </div>

        <div class="card p-4 mb-6">
            <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                <i class="fas fa-fingerprint text-[var(--color-primary)]"></i>
                {{ t('attendance.live_page.live_punch_feed', 'سجل البصمات المباشر') }}
                <span class="text-[11px] text-[var(--color-ink-muted)] mr-2" v-if="punchCount > 0">
                    ({{ punchCount }})
                </span>
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="border-b border-[var(--color-hairline)]">
                            <th class="text-right py-2 px-3 font-semibold text-[var(--color-ink-subtle)]">
                                {{ t('attendance.fields.check_in_at', 'الوقت') }}
                            </th>
                            <th class="text-right py-2 px-3 font-semibold text-[var(--color-ink-subtle)]">
                                {{ t('attendance.fields.user', 'الموظف') }}
                            </th>
                            <th class="text-right py-2 px-3 font-semibold text-[var(--color-ink-subtle)]">
                                {{ t('attendance.fields.punch_type', 'النوع') }}
                            </th>
                            <th class="text-right py-2 px-3 font-semibold text-[var(--color-ink-subtle)]">
                                {{ t('attendance.fields.source', 'المصدر') }}
                            </th>
                            <th class="text-right py-2 px-3 font-semibold text-[var(--color-ink-subtle)]">
                                {{ t('attendance.fields.status', 'الحالة') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="punch in punchFeed"
                            :key="punch.id"
                            class="border-b border-[var(--color-hairline)] hover:bg-[var(--color-surface-alt)]"
                        >
                            <td class="py-2 px-3 whitespace-nowrap" dir="ltr">
                                {{ punch.punch_time || '—' }}
                            </td>
                            <td class="py-2 px-3">
                                <div class="font-semibold">{{ punch.user?.name || punch.device_user_id || '—' }}</div>
                                <div class="text-[11px] text-[var(--color-ink-muted)]">{{ punch.user?.employee_code || '' }}</div>
                            </td>
                            <td class="py-2 px-3">
                                <Badge
                                    :text="punchTypeLabel(punch.punch_type)"
                                    :variant="punchTypeVariant(punch.punch_type)"
                                />
                            </td>
                            <td class="py-2 px-3">
                                <span class="text-[var(--color-ink-subtle)]">{{ sourceLabel(punch.source) }}</span>
                            </td>
                            <td class="py-2 px-3">
                                <Badge
                                    v-if="punch.processed"
                                    :text="t('common.processed', 'معالج')"
                                    variant="active"
                                />
                                <Badge
                                    v-else
                                    :text="t('common.pending', 'قيد الانتظار')"
                                    variant="pending"
                                />
                            </td>
                        </tr>
                        <tr v-if="punchFeed.length === 0">
                            <td colspan="5" class="py-6 text-center text-[var(--color-ink-muted)] text-[13px]">
                                {{ t('attendance.messages.empty_punch_feed', 'لا توجد بصمات مسجلة بعد.') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="card p-4">
                <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                    <i class="fas fa-bolt text-[var(--color-primary)]"></i>
                    {{ t('attendance.live_page.live_sessions') }}
                </h3>
                <ul class="divide-y divide-[var(--color-hairline)]">
                    <li
                        v-for="s in liveData"
                        :key="s.id"
                        class="py-2 flex items-center justify-between gap-2"
                    >
                        <div>
                            <div class="font-semibold text-[13px]">
                                {{ s.user?.name || ('#' + s.id) }}
                            </div>
                            <div class="text-[11px] text-[var(--color-ink-subtle)]" dir="ltr">
                                {{ s.check_in_at || '—' }}
                            </div>
                        </div>
                        <Badge
                            :text="t(`attendance.status.${s.status}`, s.status)"
                            :variant="statusVariant(s.status)"
                        />
                    </li>
                    <li v-if="liveData.length === 0" class="py-6 text-center text-[var(--color-ink-muted)] text-[13px]">
                        {{ t('attendance.messages.empty_live') }}
                    </li>
                </ul>
            </div>

            <div class="card p-4">
                <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                    <i class="fas fa-sign-out-alt text-[var(--color-warning)]"></i>
                    {{ t('attendance.live_page.missing_checkouts') }}
                </h3>
                <ul class="divide-y divide-[var(--color-hairline)]">
                    <li
                        v-for="s in missingData"
                        :key="s.id"
                        class="py-2 flex items-center justify-between gap-2"
                    >
                        <div>
                            <div class="font-semibold text-[13px]">
                                {{ s.user?.name || ('#' + s.id) }}
                            </div>
                            <div class="text-[11px] text-[var(--color-ink-subtle)]" dir="ltr">
                                {{ s.check_in_at || '—' }}
                            </div>
                        </div>
                        <Badge
                            :text="t('attendance.live_page.open_minutes')"
                            variant="warning"
                        />
                    </li>
                    <li v-if="missingData.length === 0" class="py-6 text-center text-[var(--color-ink-muted)] text-[13px]">
                        —
                    </li>
                </ul>
            </div>

            <div class="card p-4">
                <h3 class="text-[16px] font-semibold mb-3 text-[var(--color-ink)]">
                    <i class="fas fa-exclamation-triangle text-[var(--color-danger)]"></i>
                    {{ t('attendance.live_page.anomalies') }}
                </h3>
                <ul class="divide-y divide-[var(--color-hairline)]">
                    <li
                        v-for="a in anomaliesData"
                        :key="a.id"
                        class="py-2 flex items-center justify-between gap-2"
                    >
                        <div>
                            <div class="font-semibold text-[13px]">
                                {{ a.user?.name || ('#' + a.id) }}
                            </div>
                            <div class="text-[11px] text-[var(--color-ink-subtle)]" dir="ltr">
                                {{ a.summary_date || '—' }}
                            </div>
                        </div>
                        <Badge
                            :text="t(`attendance.status.${a.status}`, a.status)"
                            :variant="statusVariant(a.status)"
                        />
                    </li>
                    <li v-if="anomaliesData.length === 0" class="py-6 text-center text-[var(--color-ink-muted)] text-[13px]">
                        —
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
