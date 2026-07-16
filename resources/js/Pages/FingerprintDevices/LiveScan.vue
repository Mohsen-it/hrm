<script setup>
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    recentPunches: { type: Array, default: () => [] },
    deviceStats: { type: Object, default: () => ({}) },
});

const punches = ref([...(props.recentPunches || [])]);
const stats = ref({ ...(props.deviceStats || {}) });
const serverTime = ref('');
const lastUpdate = ref(null);
const isPaused = ref(false);
let pollHandle = null;

const POLL_INTERVAL = 3000;

const statCards = computed(() => [
    { label: t('fingerprint_devices.total_devices'), value: stats.value.total ?? 0, color: 'var(--color-primary)', icon: 'fas fa-fingerprint' },
    { label: t('fingerprint_devices.online'), value: stats.value.online ?? 0, color: 'var(--color-success)', icon: 'fas fa-wifi' },
    { label: t('fingerprint_devices.offline'), value: stats.value.offline ?? 0, color: 'var(--color-danger)', icon: 'fas fa-power-off' },
    { label: t('fingerprint_devices.maintenance'), value: stats.value.maintenance ?? 0, color: 'var(--color-warning)', icon: 'fas fa-wrench' },
]);

function formatTime(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        if (Number.isNaN(d.getTime())) return iso;
        return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch {
        return iso;
    }
}

function formatRelative(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    const diff = (Date.now() - d.getTime()) / 1000;
    if (diff < 60) return `${Math.max(0, Math.floor(diff))}s`;
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
}

async function fetchSnapshot() {
    if (isPaused.value) return;
    try {
        const res = await fetch(route('fingerprint-devices.live-scan.snapshot', { limit: 30 }), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        if (Array.isArray(data.punches)) {
            punches.value = data.punches;
        }
        if (data.stats) {
            stats.value = data.stats;
        }
        serverTime.value = data.server_time;
        lastUpdate.value = new Date();
    } catch (e) {
        // network blip — silent
    }
}

onMounted(() => {
    lastUpdate.value = new Date();
    pollHandle = setInterval(fetchSnapshot, POLL_INTERVAL);
});

onUnmounted(() => {
    if (pollHandle) clearInterval(pollHandle);
});

function punchVariant(type) {
    return type === 'check_in' ? 'active' : 'pending';
}
</script>

<template>
    <AppLayout :title="t('fingerprint_devices.live_scan')">
        <PageHeader
            :title="t('fingerprint_devices.live_scan')"
            :description="t('fingerprint_devices.live_scan_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('fingerprint_devices.title') }}</Button>
                <button
                    type="button"
                    class="btn"
                    :class="isPaused ? 'btn-warning' : 'btn-secondary'"
                    @click="isPaused = !isPaused"
                >
                    <i :class="isPaused ? 'fas fa-play' : 'fas fa-pause'"></i>
                    <span>{{ isPaused ? t('common.continue') || 'Continue' : t('common.pause') || 'Pause' }}</span>
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div
                v-for="(card, idx) in statCards"
                :key="idx"
                class="card p-3 flex items-center gap-3"
            >
                <div
                    class="w-10 h-10 rounded-md flex items-center justify-center shrink-0"
                    :style="{ backgroundColor: card.color + '15', color: card.color }"
                >
                    <i :class="card.icon" class="text-[18px]"></i>
                </div>
                <div>
                    <p class="text-[20px] font-bold text-[var(--color-ink)] leading-tight">{{ card.value }}</p>
                    <p class="text-[11px] text-[var(--color-ink-muted)]">{{ card.label }}</p>
                </div>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="flex items-center justify-between p-3 border-b border-[var(--color-hairline)]">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-block w-2 h-2 rounded-full"
                        :class="isPaused ? 'bg-[var(--color-warning)]' : 'bg-[var(--color-success)] animate-pulse'"
                    />
                    <span class="text-[13px] text-[var(--color-ink-muted)]">
                        {{ isPaused ? t('common.paused') || 'Paused' : t('fingerprint_devices.live_scan_hint') }}
                    </span>
                </div>
                <div class="text-[12px] text-[var(--color-ink-subtle)]">
                    <span v-if="lastUpdate">{{ formatTime(lastUpdate.toISOString()) }}</span>
                </div>
            </div>

            <div v-if="punches.length === 0" class="p-12 text-center">
                <i class="fas fa-fingerprint text-[40px] text-[var(--color-ink-subtle)] mb-3"></i>
                <p class="text-[14px] text-[var(--color-ink-muted)]">{{ t('fingerprint_devices.live_scan_empty') }}</p>
            </div>

            <ul v-else class="divide-y divide-[var(--color-hairline)]">
                <li
                    v-for="(punch, idx) in punches"
                    :key="punch.session_id + ':' + idx"
                    class="p-3 flex items-center gap-3 hover:bg-[var(--color-surface-1)] transition-colors"
                >
                    <div
                        class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                        :style="{
                            backgroundColor: (punch.punch_type === 'check_in' ? 'var(--color-success)' : 'var(--color-warning)') + '15',
                            color: punch.punch_type === 'check_in' ? 'var(--color-success)' : 'var(--color-warning)',
                        }"
                    >
                        <i :class="punch.punch_type === 'check_in' ? 'fas fa-sign-in-alt' : 'fas fa-sign-out-alt'"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[14px] font-medium text-[var(--color-ink)] truncate">
                            {{ punch.user?.name || '—' }}
                        </p>
                        <p class="text-[12px] text-[var(--color-ink-muted)] truncate">
                            <span v-if="punch.user?.employee_code">{{ punch.user.employee_code }} · </span>
                            {{ punch.device?.name || punch.device?.serial_number || '—' }}
                        </p>
                    </div>
                    <div class="text-end shrink-0">
                        <Badge
                            :text="punch.punch_type === 'check_in' ? t('fingerprint_devices.live_punch_in') : t('fingerprint_devices.live_punch_out')"
                            :variant="punchVariant(punch.punch_type)"
                        />
                        <p class="text-[12px] text-[var(--color-ink-muted)] mt-1">
                            {{ formatTime(punch.punched_at) }}
                            <span class="text-[var(--color-ink-subtle)]">({{ formatRelative(punch.punched_at) }})</span>
                        </p>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
