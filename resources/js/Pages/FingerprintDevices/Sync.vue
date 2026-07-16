<script setup>
import { computed, ref, onBeforeUnmount, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    devices: { type: Array, default: () => [] },
    selectedDeviceId: { type: Number, default: 0 },
    selectedDevice: { type: Object, default: null },
    lastResult: { type: Object, default: null },
});

const deviceId = ref(props.selectedDeviceId || props.devices[0]?.id || 0);
const options = ref({
    info: true,
    users: true,
    fingerprints: true,
    attendance: true,
    clear_local_cache: false,
});
const isRunning = ref(false);
const result = ref(props.lastResult || null);
const errorMessage = ref('');

// Progress state
const progress = ref(0);
const currentStep = ref('');
const currentMessage = ref('');
const currentStatus = ref('');
const stepsLog = ref([]);

let eventSource = null;

const deviceOptions = computed(() =>
    props.devices.map((d) => ({
        value: d.id,
        label: `${d.name} — ${d.ip_address}:${d.port}`,
    })),
);

const currentDevice = computed(() => props.devices.find((d) => d.id === deviceId.value) || null);

const statusVariant = (status) => {
    const map = {
        online: 'active',
        offline: 'inactive',
        maintenance: 'pending',
        deactivated: 'inactive',
    };
    return map[status] || 'inactive';
};

const stepVariant = (status) => {
    if (status === 'ok') return 'active';
    if (status === 'failed') return 'absent';
    if (status === 'skipped') return 'inactive';
    return 'pending';
};

const stepIcon = (name) => {
    const map = {
        info: 'fas fa-info-circle',
        users: 'fas fa-users',
        fingerprints: 'fas fa-fingerprint',
        attendance: 'fas fa-clock',
    };
    return map[name] || 'fas fa-cog';
};

const stepColor = (name) => {
    const map = {
        info: 'var(--color-info)',
        users: 'var(--color-primary)',
        fingerprints: 'var(--color-success)',
        attendance: 'var(--color-warning)',
    };
    return map[name] || 'var(--color-ink-muted)';
};

function pickDevice() {
    if (!deviceId.value) return;
    router.get(
        route('fingerprint-devices.sync', { device_id: deviceId.value }),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function runSync() {
    if (!deviceId.value || isRunning.value) return;

    isRunning.value = true;
    errorMessage.value = '';
    result.value = null;
    progress.value = 0;
    currentStep.value = '';
    currentMessage.value = '';
    currentStatus.value = '';
    stepsLog.value = [];

    // Use fetch with POST + SSE-like streaming via ReadableStream
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch(route('fingerprint-devices.sync.stream'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
            device_id: deviceId.value,
            options: options.value,
        }),
    }).then((response) => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        function processChunk({ done, value }) {
            if (done) {
                isRunning.value = false;
                return;
            }

            buffer += decoder.decode(value, { stream: true });
            const lines = buffer.split('\n');
            buffer = lines.pop() || '';

            let eventType = '';
            let eventData = '';

            for (const line of lines) {
                if (line.startsWith('event: ')) {
                    eventType = line.slice(7).trim();
                } else if (line.startsWith('data: ')) {
                    eventData = line.slice(6);
                } else if (line === '' && eventType && eventData) {
                    handleSSE(eventType, eventData);
                    eventType = '';
                    eventData = '';
                }
            }

            return reader.read().then(processChunk);
        }

        return reader.read().then(processChunk);
    }).catch((err) => {
        errorMessage.value = err?.message || 'Network error';
        isRunning.value = false;
    });
}

function handleSSE(event, dataRaw) {
    let data;
    try {
        data = JSON.parse(dataRaw);
    } catch {
        return;
    }

    switch (event) {
        case 'start':
            currentMessage.value = data.message || 'جاري بدء المزامنة...';
            break;

        case 'progress':
            currentStep.value = data.step;
            currentMessage.value = data.message;
            currentStatus.value = data.status;
            progress.value = data.percent || 0;

            // Add to steps log
            const existingIdx = stepsLog.value.findIndex((s) => s.name === data.step);
            if (existingIdx >= 0) {
                stepsLog.value[existingIdx].status = data.status;
                stepsLog.value[existingIdx].message = data.message;
            } else {
                stepsLog.value.push({
                    name: data.step,
                    status: data.status,
                    message: data.message,
                });
            }
            break;

        case 'done':
            result.value = data;
            progress.value = 100;
            currentMessage.value = 'اكتملت المزامنة';
            isRunning.value = false;
            break;

        case 'error':
            errorMessage.value = data.message || 'Unknown error';
            isRunning.value = false;
            break;
    }
}

function cancelSync() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    isRunning.value = false;
    progress.value = 0;
}

function formatNumber(n) {
    if (n === null || n === undefined) return '0';
    return Number(n).toLocaleString();
}

function formatDuration(s) {
    if (!s) return '—';
    if (s < 60) return `${s}s`;
    const m = Math.floor(s / 60);
    const rem = Math.round(s - m * 60);
    return `${m}m ${rem}s`;
}

onBeforeUnmount(() => {
    if (eventSource) {
        eventSource.close();
    }
});

watch(deviceId, (v) => {
    if (v && v !== props.selectedDeviceId) {
        pickDevice();
    }
});
</script>

<template>
    <AppLayout :title="t('fingerprint_devices.sync_title')">
        <PageHeader
            :title="t('fingerprint_devices.sync_title')"
            :description="t('fingerprint_devices.sync_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div v-if="errorMessage" class="mb-4">
            <Alert type="danger" :message="errorMessage" />
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="card p-6 lg:col-span-1">
                <h3 class="text-[15px] font-semibold text-[var(--color-ink)] mb-4 flex items-center gap-2">
                    <i class="fas fa-sliders-h text-[var(--color-primary)]"></i>
                    {{ t('fingerprint_devices.sync_settings') }}
                </h3>

                <div class="mb-4">
                    <label class="block text-[12px] font-semibold text-[var(--color-ink-muted)] mb-1">
                        {{ t('fingerprint_devices.device_name') }}
                    </label>
                    <select v-model="deviceId" class="form-input" :disabled="isRunning || !devices.length">
                        <option v-if="!devices.length" value="0">—</option>
                        <option v-for="opt in deviceOptions" :key="opt.value" :value="opt.value">
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <div v-if="currentDevice" class="mb-4 flex items-center gap-2 text-[12px] text-[var(--color-ink-muted)]">
                    <span>{{ currentDevice.serial_number }}</span>
                    <Badge :text="t('fingerprint_devices.' + currentDevice.status)" :variant="statusVariant(currentDevice.status)" />
                </div>

                <div class="space-y-2 mb-5">
                    <label class="flex items-center gap-2 text-[13px] text-[var(--color-ink)]">
                        <input
                            v-model="options.info"
                            type="checkbox"
                            class="form-checkbox"
                            :disabled="isRunning"
                        />
                        <i class="fas fa-info-circle text-[var(--color-info)] w-4"></i>
                        <span>{{ t('fingerprint_devices.sync_step_info') }}</span>
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-[var(--color-ink)]">
                        <input
                            v-model="options.users"
                            type="checkbox"
                            class="form-checkbox"
                            :disabled="isRunning"
                        />
                        <i class="fas fa-users text-[var(--color-primary)] w-4"></i>
                        <span>{{ t('fingerprint_devices.sync_step_users') }}</span>
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-[var(--color-ink)]">
                        <input
                            v-model="options.fingerprints"
                            type="checkbox"
                            class="form-checkbox"
                            :disabled="isRunning"
                        />
                        <i class="fas fa-fingerprint text-[var(--color-success)] w-4"></i>
                        <span>{{ t('fingerprint_devices.sync_step_fingerprints') }}</span>
                    </label>
                    <label class="flex items-center gap-2 text-[13px] text-[var(--color-ink)]">
                        <input
                            v-model="options.attendance"
                            type="checkbox"
                            class="form-checkbox"
                            :disabled="isRunning"
                        />
                        <i class="fas fa-clock text-[var(--color-warning)] w-4"></i>
                        <span>{{ t('fingerprint_devices.sync_step_attendance') }}</span>
                    </label>
                </div>

                <label class="flex items-center gap-2 text-[12px] text-[var(--color-ink-muted)] mb-4">
                    <input
                        v-model="options.clear_local_cache"
                        type="checkbox"
                        class="form-checkbox"
                        :disabled="isRunning"
                    />
                    <span>{{ t('fingerprint_devices.sync_clear_local') }}</span>
                </label>

                <Button
                    v-if="!isRunning"
                    variant="primary"
                    icon="fas fa-cloud-download-alt"
                    block
                    :disabled="!deviceId"
                    @click="runSync"
                >
                    {{ t('fingerprint_devices.sync_run') }}
                </Button>

                <Button
                    v-else
                    variant="danger"
                    icon="fas fa-stop"
                    block
                    @click="cancelSync"
                >
                    {{ t('fingerprint_devices.sync_cancel') }}
                </Button>
            </div>

            <div class="card p-6 lg:col-span-2 min-h-[400px]">
                <h3 class="text-[15px] font-semibold text-[var(--color-ink)] mb-4 flex items-center gap-2">
                    <i class="fas fa-chart-line text-[var(--color-primary)]"></i>
                    {{ t('fingerprint_devices.sync_results') }}
                </h3>

                <!-- Progress Bar Section -->
                <div v-if="isRunning" class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[13px] font-semibold text-[var(--color-ink)]">
                            {{ currentMessage || t('fingerprint_devices.sync_progress') }}
                        </span>
                        <span class="text-[13px] font-bold text-[var(--color-primary)]">
                            {{ progress }}%
                        </span>
                    </div>

                    <!-- Progress bar -->
                    <div class="w-full h-3 bg-[var(--color-surface-2)] rounded-full overflow-hidden mb-4">
                        <div
                            class="h-full rounded-full transition-all duration-500 ease-out"
                            :class="{
                                'bg-[var(--color-primary)]': currentStatus !== 'failed',
                                'bg-[var(--color-danger)]': currentStatus === 'failed',
                            }"
                            :style="{ width: progress + '%' }"
                        ></div>
                    </div>

                    <!-- Steps timeline -->
                    <div class="space-y-2">
                        <div
                            v-for="(step, idx) in stepsLog"
                            :key="step.name"
                            class="flex items-center gap-3 p-2 rounded-md"
                            :class="{
                                'bg-[var(--color-surface-1)]': step.status === 'ok',
                                'bg-[var(--color-danger-bg)]': step.status === 'failed',
                                'bg-[var(--color-surface-2)]': step.status === 'running' || step.status === 'pending',
                            }"
                        >
                            <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0">
                                <i
                                    v-if="step.status === 'running'"
                                    class="fas fa-spinner fa-spin text-[12px]"
                                    :style="{ color: stepColor(step.name) }"
                                ></i>
                                <i
                                    v-else-if="step.status === 'ok'"
                                    class="fas fa-check text-[12px] text-[var(--color-success)]"
                                ></i>
                                <i
                                    v-else-if="step.status === 'failed'"
                                    class="fas fa-times text-[12px] text-[var(--color-danger)]"
                                ></i>
                                <i
                                    v-else-if="step.status === 'skipped'"
                                    class="fas fa-minus text-[12px] text-[var(--color-ink-muted)]"
                                ></i>
                                <i
                                    v-else
                                    class="fas fa-clock text-[12px] text-[var(--color-ink-subtle)]"
                                ></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[12px] font-semibold text-[var(--color-ink)] capitalize">
                                    {{ step.name }}
                                </p>
                                <p v-if="step.message" class="text-[11px] text-[var(--color-ink-muted)] truncate">
                                    {{ step.message }}
                                </p>
                            </div>
                            <Badge :text="step.status" :variant="stepVariant(step.status)" />
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else-if="!result" class="flex flex-col items-center justify-center py-16 text-center">
                    <i class="fas fa-cloud-download-alt text-[40px] text-[var(--color-ink-subtle)] mb-3"></i>
                    <p class="text-[14px] text-[var(--color-ink-muted)]">
                        {{ t('fingerprint_devices.sync_empty') }}
                    </p>
                </div>

                <!-- Results -->
                <div v-else>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                        <div class="rounded-md bg-[var(--color-surface-1)] p-3 border border-[var(--color-hairline)]">
                            <p class="text-[11px] text-[var(--color-ink-muted)]">
                                {{ t('fingerprint_devices.sync_total_users') }}
                            </p>
                            <p class="text-[20px] font-bold text-[var(--color-ink)]">
                                {{ formatNumber(result.totals?.users_matched + result.totals?.users_unmatched) }}
                            </p>
                            <p class="text-[10px] text-[var(--color-ink-subtle)]">
                                {{ formatNumber(result.totals?.users_matched) }} matched /
                                {{ formatNumber(result.totals?.users_unmatched) }} not
                            </p>
                        </div>
                        <div class="rounded-md bg-[var(--color-surface-1)] p-3 border border-[var(--color-hairline)]">
                            <p class="text-[11px] text-[var(--color-ink-muted)]">
                                {{ t('fingerprint_devices.sync_total_fingerprints') }}
                            </p>
                            <p class="text-[20px] font-bold text-[var(--color-ink)]">
                                {{ formatNumber(result.totals?.fingerprints_saved) }}
                            </p>
                            <p class="text-[10px] text-[var(--color-ink-subtle)]">
                                pulled: {{ formatNumber(result.totals?.fingerprints_pulled) }}
                            </p>
                        </div>
                        <div class="rounded-md bg-[var(--color-surface-1)] p-3 border border-[var(--color-hairline)]">
                            <p class="text-[11px] text-[var(--color-ink-muted)]">
                                {{ t('fingerprint_devices.sync_total_attendance') }}
                            </p>
                            <p class="text-[20px] font-bold text-[var(--color-ink)]">
                                {{ formatNumber(result.totals?.attendance_saved) }}
                            </p>
                            <p class="text-[10px] text-[var(--color-ink-subtle)]">
                                sessions: {{ formatNumber(result.totals?.attendance_sessions) }}
                            </p>
                        </div>
                        <div class="rounded-md bg-[var(--color-surface-1)] p-3 border border-[var(--color-hairline)]">
                            <p class="text-[11px] text-[var(--color-ink-muted)]">
                                {{ t('fingerprint_devices.sync_duration') }}
                            </p>
                            <p class="text-[20px] font-bold text-[var(--color-ink)]">
                                {{ formatDuration(result.duration_seconds) }}
                            </p>
                            <p class="text-[10px] text-[var(--color-ink-subtle)]">
                                {{ result.started_at }}
                            </p>
                        </div>
                    </div>

                    <ul class="space-y-2 mb-5">
                        <li
                            v-for="step in result.steps || []"
                            :key="step.name"
                            class="flex items-center justify-between p-3 rounded-md border border-[var(--color-hairline)] bg-[var(--color-surface-1)]"
                        >
                            <div class="flex items-center gap-3 min-w-0">
                                <Badge :text="step.status" :variant="stepVariant(step.status)" />
                                <div class="min-w-0">
                                    <p class="text-[13px] font-medium text-[var(--color-ink)] capitalize">
                                        {{ step.name }}
                                    </p>
                                    <p v-if="step.message" class="text-[11px] text-[var(--color-ink-muted)] truncate">
                                        {{ step.message }}
                                    </p>
                                </div>
                            </div>
                            <div v-if="step.data" class="text-end text-[10px] text-[var(--color-ink-subtle)] shrink-0">
                                <pre class="whitespace-pre-wrap text-left">{{ JSON.stringify(step.data, null, 0) }}</pre>
                            </div>
                        </li>
                    </ul>

                    <div
                        v-if="result.unmatched_users && result.unmatched_users.length"
                        class="mb-4 p-3 rounded-md border border-[var(--color-warning)] bg-[var(--color-warning-bg)]"
                    >
                        <p class="text-[13px] font-semibold text-[var(--color-warning)] mb-2 flex items-center gap-2">
                            <i class="fas fa-exclamation-triangle"></i>
                            {{ t('fingerprint_devices.sync_unmatched_title') }}
                            ({{ result.unmatched_users.length }})
                        </p>
                        <ul class="text-[12px] text-[var(--color-ink-muted)] space-y-1 max-h-40 overflow-auto">
                            <li v-for="u in result.unmatched_users" :key="`${u.uid}-${u.user_id}`">
                                <span class="font-mono">{{ u.user_id || '—' }}</span>
                                — {{ u.name || '—' }}
                                <span class="text-[var(--color-ink-subtle)]"> ({{ u.reason }})</span>
                            </li>
                        </ul>
                    </div>

                    <div v-if="result.errors && result.errors.length" class="mb-4">
                        <Alert
                            type="warning"
                            :message="t('fingerprint_devices.sync_partial_errors', { count: result.errors.length })"
                        />
                        <ul class="mt-2 text-[11px] text-[var(--color-ink-muted)] space-y-1 max-h-32 overflow-auto">
                            <li v-for="(e, i) in result.errors" :key="i">• {{ e }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
