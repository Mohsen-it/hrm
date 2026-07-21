<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, StatCard, Badge, FormInput, Alert, DataTable } from '@/Components/ui';
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
        unassigned: 'warning',
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

const feedColumns = computed(() => [
    { key: 'punch_time', label: t('attendance.fields.check_in_at', 'الوقت') },
    { key: 'user', label: t('attendance.fields.user', 'الموظف') },
    { key: 'punch_type', label: t('attendance.fields.punch_type', 'النوع') },
    { key: 'source', label: t('attendance.fields.source', 'المصدر') },
    { key: 'processed', label: t('attendance.fields.status', 'الحالة') },
]);

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

function exportLive() {
    window.location.href = route('attendance.live.export', { date: date.value });
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
                        class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px]"
                        :class="isConnected ? 'bg-mistral-success/10 text-mistral-success' : 'bg-mistral-danger/10 text-mistral-danger'"
                    >
                        <span class="w-2 h-2 rounded-full" :class="isConnected ? 'bg-mistral-success animate-pulse' : 'bg-mistral-danger'"></span>
                        {{ isConnected ? 'Live' : 'Offline' }}
                    </span>
                    <Button variant="secondary" icon="fas fa-download" @click="exportLive">
                        {{ t('common.export') }}
                    </Button>
                    <Button variant="primary" icon="fas fa-bolt" @click="runDailyScan">
                        {{ t('attendance.actions.daily_scan') }}
                    </Button>
                </div>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <Card variant="base" padding="none" class="mb-4">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-3 flex-wrap">
                    <FormInput
                        v-model="date"
                        type="date"
                        :label="t('attendance.fields.date')"
                        class="max-w-[170px]"
                    />
                    <Button variant="primary" icon="fas fa-search" @click="applyFilters" class="self-end">
                        {{ t('common.search') }}
                    </Button>
                </div>
            </div>
        </Card>

        <h3 class="text-[16px] font-semibold mt-4 mb-2 text-mistral-ink">
            {{ t('attendance.live_page.health') }}
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <StatCard :label="t('attendance.kpis.live_sessions')" :value="healthData.live_sessions || 0" color="info" icon="fas fa-bolt" />
            <StatCard :label="t('attendance.kpis.missing_checkouts')" :value="healthData.missing_checkouts || 0" color="warning" icon="fas fa-sign-out-alt" />
            <StatCard :label="t('attendance.kpis.unprocessed_raw_logs')" :value="healthData.unprocessed_raw_logs || 0" color="info" icon="fas fa-database" />
            <StatCard :label="t('attendance.kpis.anomalies')" :value="healthData.anomalies || 0" color="danger" icon="fas fa-exclamation-triangle" />
        </div>

        <Card variant="base" padding="none" class="mb-6">
            <div class="p-5 sm:p-6">
                <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                    <i class="fas fa-fingerprint text-mistral-primary"></i>
                    {{ t('attendance.live_page.live_punch_feed', 'سجل البصمات المباشر') }}
                    <span class="text-[11px] text-mistral-steel mr-2" v-if="punchCount > 0">
                        ({{ punchCount }})
                    </span>
                </h3>
                <DataTable
                    :columns="feedColumns"
                    :data="{ data: punchFeed, links: [] }"
                    :selectable="false"
                    :enable-search="false"
                    :enable-filters="false"
                    :enable-pagination="false"
                    :enable-export="false"
                    :enable-density="false"
                    :enable-column-visibility="false"
                    storage-key="attendance-live-feed"
                >
                    <template #cell-punch_time="{ row }">
                        <span dir="ltr" class="text-[12px]">{{ row.punch_time || '—' }}</span>
                    </template>
                    <template #cell-user="{ row }">
                        <div>
                            <div class="font-semibold text-mistral-ink">{{ row.user?.name || row.device_user_id || '—' }}</div>
                            <div class="text-[11px] text-mistral-stone">{{ row.user?.employee_code || '' }}</div>
                        </div>
                    </template>
                    <template #cell-punch_type="{ row }">
                        <Badge
                            :text="punchTypeLabel(row.punch_type)"
                            :variant="punchTypeVariant(row.punch_type)"
                        />
                    </template>
                    <template #cell-source="{ row }">
                        <span class="text-mistral-steel">{{ sourceLabel(row.source) }}</span>
                    </template>
                    <template #cell-processed="{ row }">
                        <Badge
                            v-if="row.processed"
                            :text="t('common.processed', 'معالج')"
                            variant="active"
                        />
                        <Badge
                            v-else
                            :text="t('common.pending', 'قيد الانتظار')"
                            variant="pending"
                        />
                    </template>
                </DataTable>
            </div>
        </Card>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                        <i class="fas fa-bolt text-mistral-primary"></i>
                        {{ t('attendance.live_page.live_sessions') }}
                    </h3>
                    <ul class="divide-y divide-mistral-hairline-soft">
                        <li
                            v-for="s in liveData"
                            :key="s.id"
                            class="py-2 flex items-center justify-between gap-2"
                        >
                            <div>
                                <div class="font-semibold text-[13px]">
                                    {{ s.user?.name || ('#' + s.id) }}
                                </div>
                                <div class="text-[11px] text-mistral-steel" dir="ltr">
                                    {{ s.check_in_at || '—' }}
                                </div>
                            </div>
                            <Badge
                                :text="t(`attendance.status.${s.status}`, s.status)"
                                :variant="statusVariant(s.status)"
                            />
                        </li>
                        <li v-if="liveData.length === 0" class="py-6 text-center text-mistral-steel text-[13px]">
                            {{ t('attendance.messages.empty_live') }}
                        </li>
                    </ul>
                </div>
            </Card>

            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                        <i class="fas fa-sign-out-alt text-mistral-warning"></i>
                        {{ t('attendance.live_page.missing_checkouts') }}
                    </h3>
                    <ul class="divide-y divide-mistral-hairline-soft">
                        <li
                            v-for="s in missingData"
                            :key="s.id"
                            class="py-2 flex items-center justify-between gap-2"
                        >
                            <div>
                                <div class="font-semibold text-[13px]">
                                    {{ s.user?.name || ('#' + s.id) }}
                                </div>
                                <div class="text-[11px] text-mistral-steel" dir="ltr">
                                    {{ s.check_in_at || '—' }}
                                </div>
                            </div>
                            <Badge
                                :text="t('attendance.live_page.open_minutes')"
                                variant="warning"
                            />
                        </li>
                        <li v-if="missingData.length === 0" class="py-6 text-center text-mistral-steel text-[13px]">
                            —
                        </li>
                    </ul>
                </div>
            </Card>

            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                        <i class="fas fa-exclamation-triangle text-mistral-danger"></i>
                        {{ t('attendance.live_page.anomalies') }}
                    </h3>
                    <ul class="divide-y divide-mistral-hairline-soft">
                        <li
                            v-for="a in anomaliesData"
                            :key="a.id"
                            class="py-2 flex items-center justify-between gap-2"
                        >
                            <div>
                                <div class="font-semibold text-[13px]">
                                    {{ a.user?.name || ('#' + a.id) }}
                                </div>
                                <div class="text-[11px] text-mistral-steel" dir="ltr">
                                    {{ a.summary_date || '—' }}
                                </div>
                            </div>
                            <Badge
                                :text="t(`attendance.status.${a.status}`, a.status)"
                                :variant="statusVariant(a.status)"
                            />
                        </li>
                        <li v-if="anomaliesData.length === 0" class="py-6 text-center text-mistral-steel text-[13px]">
                            —
                        </li>
                    </ul>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
