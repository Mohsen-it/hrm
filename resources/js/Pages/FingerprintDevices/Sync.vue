<script setup>
import { computed, ref, onBeforeUnmount, watch } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Alert from '@/Components/ui/Alert.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormCheckbox from '@/Components/ui/FormCheckbox.vue';
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
    // Pull (existing)
    info: true,
    users: true,
    fingerprints: true,
    face_photos: true,
    attendance: true,
    clear_local_cache: false,
    // Push (new)
    push_users: true,
    push_fingerprints: true,
    push_face_photos: false,
    select_mode: 'all',
});
const isRunning = ref(false);
const result = ref(props.lastResult || null);
const errorMessage = ref('');

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
    if (status === 'ok' || status === 'completed') return 'active';
    if (status === 'failed') return 'absent';
    if (status === 'skipped') return 'inactive';
    if (status === 'partial') return 'pending';
    return 'pending';
};

const stepIcon = (name) => {
    const map = {
        info: 'fas fa-info-circle',
        users: 'fas fa-users',
        fingerprints: 'fas fa-fingerprint',
        face_photos: 'fas fa-camera',
        attendance: 'fas fa-clock',
        pull_users: 'fas fa-cloud-download-alt',
        pull_fingerprints: 'fas fa-cloud-download-alt',
        pull_attendance: 'fas fa-cloud-download-alt',
        pull_face_photos: 'fas fa-cloud-download-alt',
        pull: 'fas fa-cloud-download-alt',
        push_users: 'fas fa-cloud-upload-alt',
        push_fingerprints: 'fas fa-cloud-upload-alt',
        push_face_photos: 'fas fa-cloud-upload-alt',
        push: 'fas fa-cloud-upload-alt',
    };
    return map[name] || 'fas fa-cog';
};

const stepColorClass = (name) => {
    if (name.startsWith('push')) return 'text-mistral-primary';
    if (name.startsWith('pull')) return 'text-mistral-info';
    const map = {
        info: 'text-mistral-info',
        users: 'text-mistral-primary',
        fingerprints: 'text-mistral-success',
        face_photos: 'text-mistral-info',
        attendance: 'text-mistral-warning',
    };
    return map[name] || 'text-mistral-steel';
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

function runPush() {
    if (!deviceId.value || isRunning.value) return;

    isRunning.value = true;
    errorMessage.value = '';
    result.value = null;
    progress.value = 0;
    currentStep.value = '';
    currentMessage.value = '';
    currentStatus.value = '';
    stepsLog.value = [];

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch(route('fingerprint-devices.sync.push-stream'), {
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
            options: {
                push_users: options.value.push_users,
                push_fingerprints: options.value.push_fingerprints,
                push_face_photos: options.value.push_face_photos,
                select_mode: options.value.select_mode,
            },
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

function runBidirectional() {
    if (!deviceId.value || isRunning.value) return;

    isRunning.value = true;
    errorMessage.value = '';
    result.value = null;
    progress.value = 0;
    currentStep.value = '';
    currentMessage.value = '';
    currentStatus.value = '';
    stepsLog.value = [];

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch(route('fingerprint-devices.sync.bidirectional'), {
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
            options: {
                pull: {
                    info: options.value.info,
                    users: options.value.users,
                    fingerprints: options.value.fingerprints,
                    face_photos: options.value.face_photos,
                    attendance: options.value.attendance,
                },
                push: {
                    push_users: options.value.push_users,
                    push_fingerprints: options.value.push_fingerprints,
                    select_mode: options.value.select_mode,
                },
            },
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
            currentMessage.value = 'اكتملت العملية';
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

function retryFailed() {
    if (!result.value?.sync_log_id) return;
    router.post(
        route('fingerprint-devices.sync.retry-failed', { logId: result.value.sync_log_id }),
        {},
        { preserveState: true, preserveScroll: true },
    );
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
            <Card variant="base" padding="none" class="lg:col-span-1">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[15px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                        <i class="fas fa-sliders-h text-mistral-primary"></i>
                        {{ t('fingerprint_devices.sync_settings') }}
                    </h3>

                    <div class="mb-4">
                        <label class="block text-[12px] font-semibold text-mistral-steel mb-1">
                            {{ t('fingerprint_devices.device_name') }}
                        </label>
                        <FormSelect
                            v-model="deviceId"
                            :options="deviceOptions"
                            :disabled="isRunning || !devices.length"
                            option-value="value"
                            option-label="label"
                        />
                    </div>

                    <div v-if="currentDevice" class="mb-4 flex items-center gap-2 text-[12px] text-mistral-steel">
                        <span>{{ currentDevice.serial_number }}</span>
                        <Badge :text="t('fingerprint_devices.' + currentDevice.status)" :variant="statusVariant(currentDevice.status)" />
                    </div>

                    <!-- ===== Pull section ===== -->
                    <div class="mb-4">
                        <h4 class="text-[13px] font-bold text-mistral-info mb-2 flex items-center gap-2">
                            <i class="fas fa-cloud-download-alt"></i>
                            {{ t('fingerprint_devices.sync_section_pull') }}
                        </h4>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-[13px] text-mistral-ink">
                                <FormCheckbox v-model="options.info" :disabled="isRunning" />
                                <i class="fas fa-info-circle text-mistral-info w-4"></i>
                                <span>{{ t('fingerprint_devices.sync_step_info') }}</span>
                            </label>
                            <label class="flex items-center gap-2 text-[13px] text-mistral-ink">
                                <FormCheckbox v-model="options.users" :disabled="isRunning" />
                                <i class="fas fa-users text-mistral-primary w-4"></i>
                                <span>{{ t('fingerprint_devices.sync_step_users') }}</span>
                            </label>
                            <label class="flex items-center gap-2 text-[13px] text-mistral-ink">
                                <FormCheckbox v-model="options.fingerprints" :disabled="isRunning" />
                                <i class="fas fa-fingerprint text-mistral-success w-4"></i>
                                <span>{{ t('fingerprint_devices.sync_step_fingerprints') }}</span>
                            </label>
                            <label class="flex items-center gap-2 text-[13px] text-mistral-ink">
                                <FormCheckbox v-model="options.face_photos" :disabled="isRunning" />
                                <i class="fas fa-camera text-mistral-info w-4"></i>
                                <span>{{ t('fingerprint_devices.sync_step_face_photos') }}</span>
                            </label>
                            <label class="flex items-center gap-2 text-[13px] text-mistral-ink">
                                <FormCheckbox v-model="options.attendance" :disabled="isRunning" />
                                <i class="fas fa-clock text-mistral-warning w-4"></i>
                                <span>{{ t('fingerprint_devices.sync_step_attendance') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- ===== Push section (new) ===== -->
                    <div class="mb-4">
                        <h4 class="text-[13px] font-bold text-mistral-primary mb-2 flex items-center gap-2">
                            <i class="fas fa-cloud-upload-alt"></i>
                            {{ t('fingerprint_devices.sync_section_push') }}
                        </h4>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-[13px] text-mistral-ink">
                                <FormCheckbox v-model="options.push_users" :disabled="isRunning || !currentDevice?.can_push_users" />
                                <i class="fas fa-user-plus text-mistral-primary w-4"></i>
                                <span>{{ t('fingerprint_devices.sync_step_push_users') }}</span>
                            </label>
                            <label class="flex items-center gap-2 text-[13px] text-mistral-ink">
                                <FormCheckbox v-model="options.push_fingerprints" :disabled="isRunning || !currentDevice?.can_push_fingerprints" />
                                <i class="fas fa-fingerprint text-mistral-primary w-4"></i>
                                <span>{{ t('fingerprint_devices.sync_step_push_fingerprints') }}</span>
                            </label>
                            <label v-if="currentDevice?.can_push_face_photos" class="flex items-center gap-2 text-[13px] text-mistral-ink">
                                <FormCheckbox v-model="options.push_face_photos" :disabled="isRunning" />
                                <i class="fas fa-camera text-mistral-primary w-4"></i>
                                <span>{{ t('fingerprint_devices.sync_step_push_face_photos') }}</span>
                            </label>
                        </div>

                        <div class="mt-3">
                            <label class="block text-[12px] font-semibold text-mistral-steel mb-1">
                                {{ t('fingerprint_devices.sync_select_mode') }}
                            </label>
                            <FormSelect
                                v-model="options.select_mode"
                                :options="[
                                    { value: 'all', label: t('fingerprint_devices.sync_select_users_all') },
                                    { value: 'specific', label: t('fingerprint_devices.sync_select_users_specific') },
                                    { value: 'branch', label: t('fingerprint_devices.sync_select_users_by_branch') },
                                    { value: 'missing', label: t('fingerprint_devices.sync_select_users_missing') },
                                ]"
                                :disabled="isRunning"
                                option-value="value"
                                option-label="label"
                            />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-[12px] text-mistral-steel mb-4">
                        <FormCheckbox v-model="options.clear_local_cache" :disabled="isRunning" />
                        <span>{{ t('fingerprint_devices.sync_clear_local') }}</span>
                    </label>

                    <div class="space-y-2">
                        <Button
                            v-if="!isRunning"
                            variant="primary"
                            icon="fas fa-exchange-alt"
                            block
                            :disabled="!deviceId"
                            @click="runBidirectional"
                        >
                            {{ t('fingerprint_devices.sync_bidirectional') }}
                        </Button>

                        <Button
                            v-if="!isRunning"
                            variant="secondary"
                            icon="fas fa-cloud-download-alt"
                            block
                            :disabled="!deviceId"
                            @click="runSync"
                        >
                            {{ t('fingerprint_devices.sync_run') }}
                        </Button>

                        <Button
                            v-if="!isRunning"
                            variant="dark"
                            icon="fas fa-cloud-upload-alt"
                            block
                            :disabled="!deviceId || (!options.push_users && !options.push_fingerprints && !options.push_face_photos)"
                            @click="runPush"
                        >
                            {{ t('fingerprint_devices.sync_push_run') }}
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
                </div>
            </Card>

            <Card variant="base" padding="none" class="lg:col-span-2 min-h-[400px]">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[15px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                        <i class="fas fa-chart-line text-mistral-primary"></i>
                        {{ t('fingerprint_devices.sync_results') }}
                    </h3>

                    <div v-if="isRunning" class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[13px] font-semibold text-mistral-ink">
                                {{ currentMessage || t('fingerprint_devices.sync_progress') }}
                            </span>
                            <span class="text-[13px] font-bold text-mistral-primary">
                                {{ progress }}%
                            </span>
                        </div>

                        <div class="w-full h-3 bg-mistral-surface rounded-full overflow-hidden mb-4">
                            <div
                                class="h-full rounded-full transition-all duration-500 ease-out"
                                :class="{
                                    'bg-mistral-primary': currentStatus !== 'failed',
                                    'bg-mistral-danger': currentStatus === 'failed',
                                }"
                                :style="{ width: progress + '%' }"
                            ></div>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="(step, idx) in stepsLog"
                                :key="step.name"
                                class="flex items-center gap-3 p-2 rounded-lg"
                                :class="{
                                    'bg-mistral-surface': step.status === 'ok' || step.status === 'completed',
                                    'bg-mistral-danger/10': step.status === 'failed',
                                    'bg-mistral-warning/10': step.status === 'partial',
                                    'bg-mistral-surface': step.status === 'running' || step.status === 'pending',
                                }"
                            >
                                <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0">
                                    <i
                                        v-if="step.status === 'running'"
                                        class="fas fa-spinner fa-spin text-[12px]"
                                        :class="stepColorClass(step.name)"
                                    ></i>
                                    <i
                                        v-else-if="step.status === 'ok' || step.status === 'completed'"
                                        class="fas fa-check text-[12px] text-mistral-success"
                                    ></i>
                                    <i
                                        v-else-if="step.status === 'failed'"
                                        class="fas fa-times text-[12px] text-mistral-danger"
                                    ></i>
                                    <i
                                        v-else-if="step.status === 'partial'"
                                        class="fas fa-exclamation-triangle text-[12px] text-mistral-warning"
                                    ></i>
                                    <i
                                        v-else-if="step.status === 'skipped'"
                                        class="fas fa-minus text-[12px] text-mistral-steel"
                                    ></i>
                                    <i
                                        v-else
                                        class="fas fa-clock text-[12px] text-mistral-stone"
                                    ></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[12px] font-semibold text-mistral-ink capitalize">
                                        {{ step.name }}
                                    </p>
                                    <p v-if="step.message" class="text-[11px] text-mistral-steel truncate">
                                        {{ step.message }}
                                    </p>
                                </div>
                                <Badge :text="step.status" :variant="stepVariant(step.status)" />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="!result" class="flex flex-col items-center justify-center py-16 text-center">
                        <i class="fas fa-cloud-download-alt text-[40px] text-mistral-stone mb-3"></i>
                        <p class="text-[14px] text-mistral-steel">
                            {{ t('fingerprint_devices.sync_empty') }}
                        </p>
                    </div>

                    <div v-else>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
                            <StatCard
                                :label="t('fingerprint_devices.sync_pushed_users')"
                                :value="formatNumber(result.summary?.pushed_users)"
                                icon="fas fa-user-plus"
                                color="primary"
                            />
                            <StatCard
                                :label="t('fingerprint_devices.sync_pushed_fingerprints')"
                                :value="formatNumber(result.summary?.pushed_fingerprints)"
                                icon="fas fa-fingerprint"
                                color="success"
                            />
                            <StatCard
                                :label="t('fingerprint_devices.sync_failed_count')"
                                :value="formatNumber((result.summary?.failed_users || 0) + (result.summary?.failed_fingerprints || 0))"
                                icon="fas fa-times-circle"
                                color="danger"
                            />
                            <StatCard
                                :label="t('fingerprint_devices.sync_duration')"
                                :value="formatDuration(result.duration_seconds)"
                                icon="fas fa-clock"
                                color="info"
                            />
                        </div>

                        <ul v-if="result.steps && result.steps.length" class="space-y-2 mb-5">
                            <li
                                v-for="step in result.steps"
                                :key="step.name"
                                class="flex items-center justify-between p-3 rounded-lg border border-mistral-hairline-soft bg-mistral-surface"
                            >
                                <div class="flex items-center gap-3 min-w-0">
                                    <Badge :text="step.status" :variant="stepVariant(step.status)" />
                                    <div class="min-w-0">
                                        <p class="text-[13px] font-medium text-mistral-ink capitalize">
                                            {{ step.name }}
                                        </p>
                                        <p v-if="step.message" class="text-[11px] text-mistral-steel truncate">
                                            {{ step.message }}
                                        </p>
                                    </div>
                                </div>
                                <div v-if="step.data" class="text-end text-[10px] text-mistral-stone shrink-0">
                                    <pre class="whitespace-pre-wrap text-left">{{ JSON.stringify(step.data, null, 0) }}</pre>
                                </div>
                            </li>
                        </ul>

                        <div
                            v-if="result.errors && result.errors.length"
                            class="mb-4"
                        >
                            <Alert
                                type="warning"
                                :message="t('fingerprint_devices.sync_partial_errors', { count: result.errors.length })"
                            />
                            <ul class="mt-2 text-[11px] text-mistral-steel space-y-1 max-h-32 overflow-auto mb-3">
                                <li v-for="(e, i) in result.errors" :key="i">• {{ e }}</li>
                            </ul>
                            <Button
                                v-if="result.sync_log_id"
                                variant="secondary"
                                size="sm"
                                icon="fas fa-redo"
                                @click="retryFailed"
                            >
                                {{ t('fingerprint_devices.sync_retry_failed') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
