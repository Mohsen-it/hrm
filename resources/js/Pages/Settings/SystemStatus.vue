<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePageTitle } from '@/composables/usePageTitle';
import { PageHeader, Button, Card, StatCard, Badge, DataTable, Tabs, Alert, EmptyState } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    status: { type: Object, default: () => ({}) },
    history: { type: Array, default: () => [] },
    logs: { type: Object, default: () => ({ files: {}, errors: [] }) },
    health: { type: Object, default: () => ({}) },
});

const activeTab = ref('history');

const tabs = computed(() => [
    { value: 'history', label: t('settings.system_history_tab') },
    { value: 'errors', label: t('settings.system_errors_tab') },
    { value: 'health', label: t('settings.system_health_tab') },
]);

const historyColumns = computed(() => [
    { key: 'event', label: t('settings.system_event'), cellClass: 'text-center' },
    { key: 'created_at', label: t('settings.system_time'), sortable: true },
    { key: 'reason', label: t('settings.system_reason') },
    { key: 'host', label: t('settings.system_host') },
]);

const historyData = computed(() => ({ data: props.history || [] }));

function eventVariant(event) {
    return event === 'boot' ? 'active' : 'pending';
}

function formatBytes(bytes) {
    if (bytes === null || bytes === undefined) return '—';
    const units = ['B', 'KB', 'MB', 'GB'];
    let v = Number(bytes);
    let u = 0;
    while (v >= 1024 && u < units.length - 1) { v /= 1024; u++; }
    return `${v.toFixed(1)} ${units[u]}`;
}

function refresh() {
    router.reload({ only: ['status', 'history', 'logs', 'health'] });
}

usePageTitle(t('settings.system_status_title'));
</script>

<template>
    <PageHeader :title="t('settings.system_status_title')" :description="t('settings.system_status_description')">
        <template #actions>
            <Button variant="secondary" :href="route('settings.index')">
                {{ t('common.back') }}
            </Button>
            <Button variant="primary" icon="fas fa-rotate" @click="refresh">
                {{ t('settings.system_refresh') }}
            </Button>
        </template>
    </PageHeader>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <StatCard
            :label="t('settings.system_uptime')"
            :value="status.uptime_human || '—'"
            :trend="status.stack_boot_at || ''"
            icon="fas fa-server"
            color="success"
        />
        <StatCard
            :label="t('settings.system_boot_at')"
            :value="status.stack_boot_at || '—'"
            icon="fas fa-play"
            color="primary"
        />
        <StatCard
            :label="t('settings.system_last_shutdown')"
            :value="status.last_shutdown_at || t('settings.system_no_shutdown_yet')"
            icon="fas fa-power-off"
            :color="status.last_shutdown_clean === false ? 'danger' : 'info'"
        />
        <StatCard
            :label="t('settings.system_boots_count')"
            :value="status.boots_count ?? 0"
            :trend="status.unclean_count ? t('settings.system_unclean_count', { count: status.unclean_count }) : ''"
            :trend-direction="status.unclean_count ? 'down' : 'up'"
            icon="fas fa-rotate"
            color="warning"
        />
    </div>

    <Alert
        v-if="status.last_shutdown_clean === false"
        type="warning"
        :message="t('settings.system_unclean_warning')"
        class="mb-4"
    />

    <Card variant="base" padding="none" class="mb-4">
        <div class="p-5 sm:p-6 flex flex-wrap items-center gap-2">
            <Badge
                :text="health.db_ok ? t('settings.system_db_ok') : t('settings.system_db_fail')"
                :variant="health.db_ok ? 'active' : 'danger'"
                dot
            />
            <Badge
                :text="health.cache_ok ? t('settings.system_cache_ok') : t('settings.system_cache_fail')"
                :variant="health.cache_ok ? 'active' : 'danger'"
                dot
            />
            <Badge :text="`PHP ${status.php_version || '—'}`" variant="info" />
            <Badge :text="`Laravel ${status.laravel_version || '—'}`" variant="info" />
            <Badge v-if="status.hostname" :text="status.hostname" variant="cream" icon="fas fa-computer" />
            <Badge
                v-if="health.disk_free_bytes !== null && health.disk_total_bytes"
                :text="t('settings.system_disk_free', { free: formatBytes(health.disk_free_bytes) })"
                variant="info"
                icon="fas fa-hard-drive"
            />
        </div>
    </Card>

    <Tabs v-model="activeTab" :tabs="tabs" class="mb-4" />

    <div v-if="activeTab === 'history'">
        <DataTable
            :columns="historyColumns"
            :data="historyData"
            :enable-pagination="false"
            :enable-search="false"
            :enable-filters="false"
            :enable-export="false"
            :enable-density="false"
            :enable-column-visibility="false"
            :selectable="false"
            storage-key="system-history"
        >
            <template #cell-event="{ row }">
                <Badge :text="row.event === 'boot' ? t('settings.system_boot') : t('settings.system_shutdown')" :variant="eventVariant(row.event)" dot />
                <Badge v-if="row.unclean_previous" :text="t('settings.system_unclean')" variant="danger" class="ms-1" />
            </template>
            <template #cell-reason="{ row }">
                <span class="text-[12px] text-mistral-ink">{{ row.reason || '—' }}</span>
            </template>
            <template #cell-host="{ row }">
                <span class="text-[12px] font-mono text-mistral-steel">{{ row.hostname || '—' }}{{ row.pid ? ` · PID ${row.pid}` : '' }}</span>
            </template>
        </DataTable>
    </div>

    <div v-if="activeTab === 'errors'">
        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[13px] font-semibold text-mistral-ink">
                        {{ t('settings.system_recent_errors', { count: (logs.errors || []).length }) }}
                    </h3>
                    <span class="text-[11px] text-mistral-steel">{{ t('settings.system_errors_hint') }}</span>
                </div>
                <EmptyState
                    v-if="!(logs.errors || []).length"
                    icon="fas fa-circle-check"
                    :title="t('settings.system_no_errors')"
                />
                <div v-else class="space-y-2 max-h-[520px] overflow-y-auto" dir="ltr">
                    <div
                        v-for="(e, i) in logs.errors"
                        :key="i"
                        class="rounded-md border border-mistral-hairline bg-mistral-surface px-3 py-2 text-start"
                    >
                        <span class="inline-block rounded bg-mistral-primary/10 text-mistral-primary text-[10px] font-mono px-1.5 py-0.5 me-2">{{ e.source }}</span>
                        <code class="text-[11px] font-mono text-mistral-ink break-all whitespace-pre-wrap">{{ e.line }}</code>
                    </div>
                </div>
            </div>
        </Card>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
            <Card v-for="(f, name) in (logs.files || {})" :key="name" variant="base">
                <div class="text-[12px] font-semibold text-mistral-ink font-mono">{{ name }}</div>
                <div class="text-[11px] text-mistral-steel mt-1">
                    {{ f.exists ? t('settings.system_log_lines', { count: f.lines.length, size: formatBytes(f.size) }) : t('settings.system_log_missing') }}
                </div>
            </Card>
        </div>
    </div>

    <div v-if="activeTab === 'health'">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard :label="t('settings.system_db')" :value="health.db_ok ? t('common.active') : t('common.inactive')" icon="fas fa-database" :color="health.db_ok ? 'success' : 'danger'" />
            <StatCard :label="t('settings.system_cache')" :value="`${health.cache_driver || '—'}`" icon="fas fa-bolt" :color="health.cache_ok ? 'success' : 'danger'" />
            <StatCard :label="t('settings.system_queue')" :value="`${health.queue_driver || '—'}`" icon="fas fa-layer-group" color="info" />
            <StatCard :label="t('settings.system_logs_size')" :value="formatBytes(health.logs_size_bytes)" icon="fas fa-file-lines" color="warning" />
        </div>
        <Alert v-if="health.db_error" type="danger" :message="health.db_error" class="mt-4" />
    </div>
</template>
