<script setup>
import { computed, ref, watch } from 'vue';
import {
    FormModal,
    FormCheckbox,
    FormSelect,
    FormInput,
    Button,
    Badge,
    Alert,
} from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    device: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'close']);

const isOpen = computed(() => props.modelValue);

const pushUsers = ref(true);
const pushFingerprints = ref(true);
const pushFacePhotos = ref(false);

const selectMode = ref('missing');
const branchId = ref('');
const specificIds = ref('');

const isRunning = ref(false);
const progress = ref(0);
const currentStep = ref('');
const currentMessage = ref('');
const currentStatus = ref('');
const stepsLog = ref([]);
const result = ref(null);
const errorMessage = ref('');

const isPreviewing = ref(false);
const previewResult = ref(null);
const previewError = ref('');

let eventSource = null;

const branchOptions = computed(() => [
    { value: '', label: t('fingerprint_devices.quick_push_select_branch') },
    ...props.branches.map((b) => ({ value: b.id, label: b.branch_name })),
]);

const summary = computed(() => result.value?.summary || {});
const isDone = computed(() => result.value !== null);
const isFailed = computed(() => result.value?.success === false);
const totalErrorCount = computed(
    () => (result.value?.errors || []).length,
);

watch(isOpen, (v) => {
    if (!v) reset();
});

function reset() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    isRunning.value = false;
    progress.value = 0;
    currentStep.value = '';
    currentMessage.value = '';
    currentStatus.value = '';
    stepsLog.value = [];
    result.value = null;
    errorMessage.value = '';
    isPreviewing.value = false;
    previewResult.value = null;
    previewError.value = '';
}

function buildOptions() {
    const options = {
        push_users: pushUsers.value,
        push_fingerprints: pushFingerprints.value,
        push_face_photos: pushFacePhotos.value,
        select_mode: selectMode.value,
    };

    if (selectMode.value === 'branch' && branchId.value) {
        options.branch_id = Number(branchId.value);
    } else if (selectMode.value === 'specific') {
        const ids = specificIds.value
            .split(/[,\s]+/)
            .map((s) => s.trim())
            .filter(Boolean);
        if (ids.length === 0) {
            return { error: t('fingerprint_devices.quick_push_specific_empty') };
        }
        if (ids.every((id) => /^\d+$/.test(id))) {
            options.user_ids = ids.map((id) => Number(id));
            options.select_mode = 'specific';
        } else {
            return { error: t('fingerprint_devices.quick_push_specific_empty') };
        }
    }

    return { options };
}

async function runPreview() {
    if (isPreviewing.value || isRunning.value) return;

    const built = buildOptions();
    if (built.error) {
        previewError.value = built.error;
        previewResult.value = null;
        return;
    }
    if (!built.options.push_users && !built.options.push_fingerprints && !built.options.push_face_photos) {
        previewError.value = t('fingerprint_devices.quick_push_choose_one');
        previewResult.value = null;
        return;
    }

    previewError.value = '';
    isPreviewing.value = true;

    try {
        const params = new URLSearchParams();
        params.set('push_users', built.options.push_users ? '1' : '0');
        params.set('push_fingerprints', built.options.push_fingerprints ? '1' : '0');
        params.set('push_face_photos', built.options.push_face_photos ? '1' : '0');
        params.set('select_mode', built.options.select_mode || 'all');
        if (built.options.branch_id) params.set('branch_id', String(built.options.branch_id));
        if (Array.isArray(built.options.user_ids) && built.options.user_ids.length) {
            params.set('user_ids', built.options.user_ids.join(','));
        }

        const url = route('fingerprint-devices.push-preview', { id: props.device.id }) + '?' + params.toString();
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const contentType = response.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');

        if (!isJson) {
            const htmlSnippet = (await response.text()).slice(0, 150).replace(/\s+/g, ' ');
            const finalStatus = response.status;
            const finalUrl = response.url;
            const wasRedirected = response.redirected;
            const finalType = response.headers.get('content-type') || '';

            if (finalStatus === 419) {
                throw new Error(t('fingerprint_devices.preview_csrf_expired') + ` [419 / ${finalUrl}]`);
            }
            if (wasRedirected || finalStatus === 401 || finalStatus === 302) {
                const debugInfo = `[${finalStatus}${wasRedirected ? '→' + finalUrl : ''} / type=${finalType}]`;
                throw new Error(t('fingerprint_devices.preview_session_expired') + ' ' + debugInfo);
            }
            if (finalStatus === 403) {
                throw new Error(t('fingerprint_devices.preview_no_permission') + ` [403]`);
            }
            throw new Error(t('fingerprint_devices.preview_unexpected_response', { status: finalStatus, snippet: htmlSnippet }));
        }

        if (!response.ok) {
            const body = await response.json();
            const msg = body?.message
                || (body?.errors ? Object.values(body.errors).flat().join(', ') : null)
                || `HTTP ${response.status}`;
            throw new Error(msg);
        }

        previewResult.value = await response.json();
    } catch (err) {
        previewError.value = err?.message || 'Network error';
        previewResult.value = null;
    } finally {
        isPreviewing.value = false;
    }
}

function cancelPreview() {
    previewResult.value = null;
    previewError.value = '';
}

function close() {
    if (isRunning.value) return;
    emit('update:modelValue', false);
    emit('close');
}

function buildPayload() {
    return buildOptions();
}

function startPush() {
    if (isRunning.value) return;

    const built = buildPayload();
    if (built.error) {
        errorMessage.value = built.error;
        return;
    }
    const options = built.options;

    if (
        !options.push_users &&
        !options.push_fingerprints &&
        !options.push_face_photos
    ) {
        errorMessage.value = t('fingerprint_devices.quick_push_choose_one');
        return;
    }

    errorMessage.value = '';
    result.value = null;
    progress.value = 0;
    currentStep.value = '';
    currentMessage.value = '';
    currentStatus.value = '';
    stepsLog.value = [];
    isRunning.value = true;

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
            device_id: props.device.id,
            options,
        }),
    })
        .then((response) => {
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
                    if (line.startsWith('event: ')) eventType = line.slice(7).trim();
                    else if (line.startsWith('data: ')) eventData = line.slice(6);
                    else if (line === '' && eventType && eventData) {
                        handleSSE(eventType, eventData);
                        eventType = '';
                        eventData = '';
                    }
                }
                return reader.read().then(processChunk);
            }
            return reader.read().then(processChunk);
        })
        .catch((err) => {
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

    if (event === 'start') {
        currentMessage.value = data.message || t('fingerprint_devices.sync_progress');
    } else if (event === 'progress') {
        currentStep.value = data.step;
        currentMessage.value = data.message;
        currentStatus.value = data.status;
        progress.value = data.percent || 0;
        const idx = stepsLog.value.findIndex((s) => s.name === data.step);
        if (idx >= 0) {
            stepsLog.value[idx].status = data.status;
            stepsLog.value[idx].message = data.message;
        } else {
            stepsLog.value.push({ name: data.step, status: data.status, message: data.message });
        }
    } else if (event === 'done') {
        result.value = data;
        progress.value = 100;
        isRunning.value = false;
    } else if (event === 'error') {
        errorMessage.value = data.message || 'Unknown error';
        isRunning.value = false;
    }
}

function formatNumber(n) {
    if (n === null || n === undefined) return '0';
    return Number(n).toLocaleString();
}

function stepVariant(status) {
    if (status === 'ok' || status === 'completed') return 'active';
    if (status === 'failed') return 'absent';
    if (status === 'skipped') return 'inactive';
    return 'pending';
}

const selectModeOptions = computed(() => [
    { value: 'missing', label: t('fingerprint_devices.quick_push_scope_missing') },
    { value: 'all', label: t('fingerprint_devices.quick_push_scope_all') },
    { value: 'branch', label: t('fingerprint_devices.quick_push_scope_branch') },
    { value: 'specific', label: t('fingerprint_devices.quick_push_scope_specific') },
]);
</script>

<template>
    <FormModal v-model="isOpen" :title="t('fingerprint_devices.quick_push_title')" size="lg" @close="close">
        <div v-if="errorMessage" class="mb-4">
            <Alert type="danger" :message="errorMessage" />
        </div>

        <div v-if="!isDone" class="space-y-5">
            <!-- What to push -->
            <div>
                <h4 class="text-[13px] font-semibold text-mistral-ink mb-2 flex items-center gap-2">
                    <i class="fas fa-cloud-upload-alt text-mistral-primary"></i>
                    {{ t('fingerprint_devices.quick_push_what') }}
                </h4>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-[13px]">
                        <FormCheckbox v-model="pushUsers" :disabled="isRunning" />
                        <i class="fas fa-user-plus text-mistral-primary w-4"></i>
                        <span>{{ t('fingerprint_devices.sync_step_push_users') }}</span>
                    </label>
                    <label class="flex items-center gap-2 text-[13px]">
                        <FormCheckbox v-model="pushFingerprints" :disabled="isRunning" />
                        <i class="fas fa-fingerprint text-mistral-primary w-4"></i>
                        <span>{{ t('fingerprint_devices.sync_step_push_fingerprints') }}</span>
                    </label>
                    <label
                        v-if="device.can_push_face_photos"
                        class="flex items-center gap-2 text-[13px]"
                    >
                        <FormCheckbox v-model="pushFacePhotos" :disabled="isRunning" />
                        <i class="fas fa-camera text-mistral-primary w-4"></i>
                        <span>{{ t('fingerprint_devices.sync_step_push_face_photos') }}</span>
                    </label>
                </div>
            </div>

            <!-- Scope -->
            <div>
                <h4 class="text-[13px] font-semibold text-mistral-ink mb-2 flex items-center gap-2">
                    <i class="fas fa-users text-mistral-info"></i>
                    {{ t('fingerprint_devices.quick_push_scope') }}
                </h4>
                <FormSelect
                    v-model="selectMode"
                    :options="selectModeOptions"
                    :disabled="isRunning"
                />
                <div v-if="selectMode === 'branch'" class="mt-3">
                    <FormSelect
                        v-model="branchId"
                        :options="branchOptions"
                        :disabled="isRunning"
                    />
                </div>
                <div v-if="selectMode === 'specific'" class="mt-3">
                    <FormInput
                        v-model="specificIds"
                        :label="t('fingerprint_devices.quick_push_specific_label')"
                        :placeholder="t('fingerprint_devices.quick_push_specific_placeholder')"
                        :hint="t('fingerprint_devices.quick_push_specific_hint')"
                        :disabled="isRunning"
                    />
                </div>
                <p v-if="selectMode === 'missing'" class="text-[11px] text-mistral-steel mt-2">
                    {{ t('fingerprint_devices.quick_push_missing_hint') }}
                </p>
            </div>
        </div>

        <!-- Preview (read-only) -->
        <div v-if="!isRunning && !isDone && previewResult" class="mt-5">
            <div class="rounded-lg border border-mistral-primary/30 bg-mistral-primary/5 p-4">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-eye text-mistral-primary"></i>
                    <h4 class="text-[14px] font-bold text-mistral-ink">
                        {{ t('fingerprint_devices.push_preview_title') }}
                    </h4>
                    <Badge :text="t('fingerprint_devices.push_preview_subtitle')" variant="pending" />
                </div>
                <p class="text-[11px] text-mistral-steel mb-3">
                    <i class="fas fa-shield-alt me-1"></i>
                    {{ t('fingerprint_devices.preview_safety_note') }}
                </p>

                <!-- Warnings -->
                <div v-if="previewResult.warnings && previewResult.warnings.length" class="mb-3 space-y-2">
                    <div class="text-[12px] font-semibold text-mistral-ink mb-1">
                        <i class="fas fa-exclamation-triangle text-mistral-warning me-1"></i>
                        {{ t('fingerprint_devices.push_preview_section_warnings') }}
                    </div>
                    <Alert
                        v-for="w in previewResult.warnings"
                        :key="w.code"
                        type="warning"
                        :message="w.message"
                    />
                </div>
                <div v-else class="mb-3">
                    <Alert
                        type="success"
                        :message="t('fingerprint_devices.push_preview_no_warnings')"
                    />
                </div>

                <!-- Impact stats -->
                <div class="text-[12px] font-semibold text-mistral-ink mb-2">
                    <i class="fas fa-chart-bar text-mistral-info me-1"></i>
                    {{ t('fingerprint_devices.push_preview_section_impact') }}
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3">
                    <div class="rounded-lg border border-mistral-hairline-soft bg-white p-3">
                        <div class="text-[10px] text-mistral-stone">{{ t('fingerprint_devices.push_preview_candidates') }}</div>
                        <div class="text-[18px] font-bold text-mistral-ink">
                            {{ formatNumber(previewResult.totals.candidates) }}
                        </div>
                    </div>
                    <div class="rounded-lg border border-mistral-success/30 bg-mistral-success/5 p-3">
                        <div class="text-[10px] text-mistral-stone">
                            <i class="fas fa-plus-circle text-mistral-success me-1"></i>
                            {{ t('fingerprint_devices.push_preview_sample_add') }}
                        </div>
                        <div class="text-[18px] font-bold text-mistral-success">
                            {{ formatNumber(previewResult.totals.would_add_users) }}
                        </div>
                    </div>
                    <div class="rounded-lg border border-mistral-hairline-soft bg-mistral-surface p-3">
                        <div class="text-[10px] text-mistral-stone">
                            <i class="fas fa-check-circle text-mistral-stone me-1"></i>
                            {{ t('fingerprint_devices.push_preview_sample_skip') }}
                        </div>
                        <div class="text-[18px] font-bold text-mistral-steel">
                            {{ formatNumber(previewResult.totals.would_skip_existing_users) }}
                        </div>
                    </div>
                    <div class="rounded-lg border border-mistral-info/30 bg-mistral-info/5 p-3">
                        <div class="text-[10px] text-mistral-stone">
                            <i class="fas fa-fingerprint text-mistral-info me-1"></i>
                            {{ t('fingerprint_devices.push_preview_would_push_fingerprints') }}
                        </div>
                        <div class="text-[18px] font-bold text-mistral-info">
                            {{ formatNumber(previewResult.totals.would_push_fingerprints) }}
                        </div>
                        <div v-if="previewResult.totals.fingerprints_for_distinct_users" class="text-[10px] text-mistral-stone mt-1">
                            {{ t('fingerprint_devices.push_preview_fingerprints_for_distinct_users', { count: previewResult.totals.fingerprints_for_distinct_users }) }}
                        </div>
                    </div>
                </div>

                <p class="text-[11px] text-mistral-stone">
                    <i class="fas fa-server me-1"></i>
                    {{ t('fingerprint_devices.push_preview_device_user_count', { count: previewResult.totals.device_user_count }) }}
                    <span v-if="previewResult.totals.skipped_no_employee_code > 0" class="ms-2">
                        • {{ t('fingerprint_devices.push_preview_skipped_no_employee_code', { count: previewResult.totals.skipped_no_employee_code }) }}
                    </span>
                </p>

                <!-- Samples -->
                <details v-if="(previewResult.samples?.add?.length || previewResult.samples?.skip?.length)" class="mt-3">
                    <summary class="text-[12px] cursor-pointer text-mistral-steel hover:text-mistral-ink">
                        {{ t('fingerprint_devices.push_preview_section_samples') }}
                    </summary>
                    <div v-if="previewResult.samples.add.length" class="mt-2">
                        <div class="text-[11px] font-semibold text-mistral-success mb-1">
                            {{ t('fingerprint_devices.push_preview_sample_add') }}
                        </div>
                        <ul class="text-[11px] text-mistral-steel space-y-0.5 bg-white rounded-lg p-2 max-h-32 overflow-auto">
                            <li v-for="u in previewResult.samples.add" :key="'a-' + u.id">
                                • {{ u.name }} <span class="text-mistral-stone">({{ u.employee_code }})</span>
                            </li>
                        </ul>
                    </div>
                    <div v-if="previewResult.samples.skip.length" class="mt-2">
                        <div class="text-[11px] font-semibold text-mistral-stone mb-1">
                            {{ t('fingerprint_devices.push_preview_sample_skip') }}
                        </div>
                        <ul class="text-[11px] text-mistral-steel space-y-0.5 bg-white rounded-lg p-2 max-h-32 overflow-auto">
                            <li v-for="u in previewResult.samples.skip" :key="'s-' + u.id">
                                • {{ u.name }} <span class="text-mistral-stone">({{ u.employee_code }})</span>
                            </li>
                        </ul>
                    </div>
                </details>
            </div>
        </div>

        <!-- Progress -->
        <div v-if="isRunning" class="mt-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[13px] font-semibold text-mistral-ink">
                    {{ currentMessage || t('fingerprint_devices.sync_progress') }}
                </span>
                <span class="text-[13px] font-bold text-mistral-primary">
                    {{ progress }}%
                </span>
            </div>
            <div class="w-full h-3 bg-mistral-surface rounded-full overflow-hidden">
                <div
                    class="h-full rounded-full transition-all duration-500 ease-out"
                    :class="currentStatus === 'failed' ? 'bg-mistral-danger' : 'bg-mistral-primary'"
                    :style="{ width: progress + '%' }"
                ></div>
            </div>
            <div v-if="stepsLog.length" class="mt-3 space-y-1">
                <div
                    v-for="step in stepsLog"
                    :key="step.name"
                    class="flex items-center gap-2 text-[12px] text-mistral-steel"
                >
                    <Badge :text="step.status" :variant="stepVariant(step.status)" />
                    <span class="font-semibold capitalize">{{ step.name }}</span>
                    <span v-if="step.message" class="text-mistral-stone truncate">— {{ step.message }}</span>
                </div>
            </div>
        </div>

        <!-- Result -->
        <div v-else-if="isDone" class="mt-5">
            <Alert
                v-if="isFailed"
                type="danger"
                :message="t('fingerprint_devices.sync_failed_message', { error: errorMessage || t('fingerprint_devices.unknown_error') })"
            />
            <Alert
                v-else-if="totalErrorCount > 0"
                type="warning"
                :message="t('fingerprint_devices.sync_partial_errors', { count: totalErrorCount })"
            />
            <Alert
                v-else
                type="success"
                :message="t('fingerprint_devices.quick_push_done')"
            />

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                <div class="rounded-lg border border-mistral-hairline-soft p-3">
                    <div class="text-[11px] text-mistral-stone">{{ t('fingerprint_devices.sync_pushed_users') }}</div>
                    <div class="text-[20px] font-bold text-mistral-success">
                        {{ formatNumber(summary.pushed_users) }}
                    </div>
                </div>
                <div class="rounded-lg border border-mistral-hairline-soft p-3">
                    <div class="text-[11px] text-mistral-stone">{{ t('fingerprint_devices.sync_pushed_fingerprints') }}</div>
                    <div class="text-[20px] font-bold text-mistral-success">
                        {{ formatNumber(summary.pushed_fingerprints) }}
                    </div>
                </div>
                <div class="rounded-lg border border-mistral-hairline-soft p-3">
                    <div class="text-[11px] text-mistral-stone">{{ t('fingerprint_devices.sync_failed_count') }}</div>
                    <div class="text-[20px] font-bold text-mistral-danger">
                        {{ formatNumber((summary.failed_users || 0) + (summary.failed_fingerprints || 0)) }}
                    </div>
                </div>
                <div class="rounded-lg border border-mistral-hairline-soft p-3">
                    <div class="text-[11px] text-mistral-stone">{{ t('fingerprint_devices.sync_duration') }}</div>
                    <div class="text-[20px] font-bold text-mistral-info">
                        {{ formatNumber(result.duration_seconds) }}s
                    </div>
                </div>
            </div>

            <details v-if="result.errors && result.errors.length" class="mt-4">
                <summary class="text-[12px] cursor-pointer text-mistral-steel hover:text-mistral-ink">
                    {{ t('fingerprint_devices.quick_push_view_errors', { count: result.errors.length }) }}
                </summary>
                <ul class="mt-2 text-[11px] text-mistral-steel space-y-1 max-h-40 overflow-auto bg-mistral-surface rounded-lg p-3">
                    <li v-for="(e, i) in result.errors" :key="i">• {{ e }}</li>
                </ul>
            </details>
        </div>

        <template #footer>
            <div v-if="previewError" class="w-full mb-2">
                <Alert type="danger" :message="previewError" />
            </div>
            <Button variant="secondary" :disabled="isRunning" @click="previewResult ? cancelPreview() : close()">
                {{ isDone ? t('common.close') : (previewResult ? t('fingerprint_devices.push_preview_cancel') : t('common.cancel')) }}
            </Button>
            <Button
                v-if="!isDone && !previewResult"
                variant="dark"
                icon="fas fa-eye"
                :disabled="isRunning || isPreviewing"
                :loading="isPreviewing"
                @click="runPreview"
            >
                {{ t('fingerprint_devices.push_preview_run') }}
            </Button>
            <Button
                v-if="!isDone && previewResult"
                variant="secondary"
                icon="fas fa-redo"
                :disabled="isRunning || isPreviewing"
                :loading="isPreviewing"
                @click="runPreview"
            >
                {{ t('fingerprint_devices.push_preview_recompute') }}
            </Button>
            <Button
                v-if="!isDone"
                variant="primary"
                icon="fas fa-cloud-upload-alt"
                :disabled="isRunning"
                @click="startPush"
            >
                {{ previewResult ? t('fingerprint_devices.push_preview_confirm_after_preview') : t('fingerprint_devices.quick_push_confirm') }}
            </Button>
        </template>
    </FormModal>
</template>
