<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    dashboard: { type: Object, required: true },
});

const activeTab = ref('overview');
const searchQuery = ref('');
const showSyncDetail = ref(false);
const selectedEmployee = ref(null);
const retryingFailed = ref(false);
const retryingDeviceId = ref(null);
const retryToast = ref(null);

const summary = computed(() => props.dashboard.summary);
const devices = computed(() => props.dashboard.devices);
const employees = computed(() => props.dashboard.employees);
const commandQueue = computed(() => props.dashboard.commandQueue);
const recentLogs = computed(() => props.dashboard.recentSyncLogs);
const failedCommands = computed(() => props.dashboard.failedCommands);

// Filter employees by search
const filteredEmployees = computed(() => {
    if (!searchQuery.value) return employees.value;
    const q = searchQuery.value.toLowerCase();
    return employees.value.filter(
        (e) =>
            e.name.toLowerCase().includes(q) ||
            e.employee_code.toLowerCase().includes(q),
    );
});

// Employees missing faces on some devices
const partiallySyncedEmployees = computed(() =>
    employees.value.filter((e) => !e.is_fully_synced && e.total_templates > 0),
);

// Employees with no faces at all
const noFaceEmployees = computed(() =>
    employees.value.filter((e) => e.total_templates === 0),
);

// Fully synced employees
const fullySyncedEmployees = computed(() =>
    employees.value.filter((e) => e.is_fully_synced),
);

// Devices with issues
const devicesWithIssues = computed(() =>
    devices.value.filter((d) => d.failed_commands > 0 || d.pending_commands > 0 || d.status === 'offline'),
);

// Status badge
function statusVariant(status) {
    const map = { online: 'active', offline: 'inactive', maintenance: 'pending', deactivated: 'inactive' };
    return map[status] || 'inactive';
}

function healthVariant(percent) {
    if (percent >= 95) return 'active';
    if (percent >= 70) return 'pending';
    return 'absent';
}

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'medium' });
}

function formatPercent(value) {
    if (value === null || value === undefined) return '0%';
    return `${value}%`;
}

function openEmployeeDetail(employee) {
    selectedEmployee.value = employee;
    showSyncDetail.value = true;
}

function getDeviceById(id) {
    return devices.value.find((d) => d.id === id);
}

async function retryAllFailed() {
    if (retryingFailed.value) return;
    retryingFailed.value = true;
    try {
        const res = await fetch(route('fingerprint-devices.face-sync.retry-failed'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
        });
        const data = await res.json();
        retryToast.value = { type: 'success', message: `Re-queued ${data.requeued} command(s) for retry` };
        router.reload({ only: ['dashboard'] });
    } catch (e) {
        retryToast.value = { type: 'error', message: 'Failed to retry commands' };
    } finally {
        retryingFailed.value = false;
        setTimeout(() => (retryToast.value = null), 4000);
    }
}

async function retryDeviceFailed(deviceId) {
    if (retryingDeviceId.value) return;
    retryingDeviceId.value = deviceId;
    try {
        const res = await fetch(route('fingerprint-devices.face-sync.retry-failed'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''),
            },
            body: JSON.stringify({ device_id: deviceId }),
        });
        const data = await res.json();
        retryToast.value = { type: 'success', message: `Re-queued ${data.requeued} command(s) for retry` };
        router.reload({ only: ['dashboard'] });
    } catch (e) {
        retryToast.value = { type: 'error', message: 'Failed to retry commands' };
    } finally {
        retryingDeviceId.value = null;
        setTimeout(() => (retryToast.value = null), 4000);
    }
}

usePageTitle(t('fingerprint_devices.face_sync_dashboard') || 'Face Sync Dashboard');
</script>

<template>
    <PageHeader
        :title="t('fingerprint_devices.face_sync_dashboard') || 'Face Sync Dashboard'"
        :description="t('fingerprint_devices.face_sync_dashboard_description') || 'Monitor face template sync status across all devices'"
    >
        <template #actions>
            <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.back') }}</Button>
            <Button variant="secondary" icon="fas fa-sync-alt" :href="route('fingerprint-devices.dashboard')">
                {{ t('fingerprint_devices.dashboard') || 'Device Dashboard' }}
            </Button>
        </template>
    </PageHeader>

    <!-- Retry toast -->
    <Transition
        enter-active-class="duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 -translate-y-2"
    >
        <div
            v-if="retryToast"
            class="fixed top-4 right-4 z-50 px-4 py-3 rounded-xl shadow-lg border text-[13px] font-medium"
            :class="
                retryToast.type === 'success'
                    ? 'bg-mistral-success/10 border-mistral-success/30 text-mistral-success'
                    : 'bg-mistral-danger/10 border-mistral-danger/30 text-mistral-danger'
            "
        >
            <i :class="retryToast.type === 'success' ? 'fas fa-check-circle mr-2' : 'fas fa-times-circle mr-2'"></i>
            {{ retryToast.message }}
        </div>
    </Transition>

    <!-- ===== GLOBAL SUMMARY STATS ===== -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <StatCard
            :label="t('fingerprint_devices.face_templates') || 'Face Templates'"
            :value="summary.total_face_templates"
            icon="fas fa-face-smile"
            color="primary"
        />
        <StatCard
            :label="t('fingerprint_devices.employees_with_faces') || 'Employees with Faces'"
            :value="`${summary.employees_with_faces} / ${summary.total_active_employees}`"
            icon="fas fa-users"
            color="success"
        />
        <StatCard
            :label="t('fingerprint_devices.coverage') || 'Coverage'"
            :value="formatPercent(summary.coverage_percent)"
            icon="fas fa-chart-pie"
            color="info"
        />
        <StatCard
            :label="t('fingerprint_devices.active_devices') || 'Active Devices'"
            :value="`${summary.online_devices} / ${summary.total_devices}`"
            icon="fas fa-server"
            color="primary"
        />
        <StatCard
            :label="t('fingerprint_devices.pending_commands') || 'Pending'"
            :value="summary.pending_face_commands"
            icon="fas fa-hourglass-half"
            color="warning"
        />
        <StatCard
            :label="t('fingerprint_devices.failed_commands') || 'Failed'"
            :value="summary.failed_face_commands"
            icon="fas fa-times-circle"
            color="danger"
        />
    </div>

    <!-- ===== TABS ===== -->
    <div class="flex gap-1 border-b border-mistral-hairline-soft mb-6">
        <button
            v-for="tab in [
                { key: 'overview', label: t('fingerprint_devices.tab_overview') || 'Overview', icon: 'fas fa-th-large' },
                { key: 'devices', label: t('fingerprint_devices.tab_devices') || 'Devices', icon: 'fas fa-server' },
                { key: 'employees', label: t('fingerprint_devices.tab_employees') || 'Employees', icon: 'fas fa-users' },
                { key: 'queue', label: t('fingerprint_devices.tab_queue') || 'Command Queue', icon: 'fas fa-list' },
            ]"
            :key="tab.key"
            class="px-4 py-2.5 text-[13px] font-medium border-b-2 transition-colors -mb-px"
            :class="
                activeTab === tab.key
                    ? 'border-mistral-primary text-mistral-primary'
                    : 'border-transparent text-mistral-steel hover:text-mistral-ink hover:border-mistral-hairline'
            "
            @click="activeTab = tab.key"
        >
            <i :class="tab.icon" class="mr-1.5"></i>
            {{ tab.label }}
        </button>
    </div>

    <!-- ===== OVERVIEW TAB ===== -->
    <div v-if="activeTab === 'overview'">
        <!-- Health alerts -->
        <div v-if="devicesWithIssues.length" class="mb-6">
            <Alert type="warning">
                <template #title>{{ t('fingerprint_devices.sync_issues_alert') || 'Sync Issues Detected' }}</template>
                <template #message>
                    {{ devicesWithIssues.length }} {{ t('fingerprint_devices.devices_with_issues') || 'device(s) have pending, failed, or offline status' }}.
                </template>
            </Alert>
        </div>

        <!-- Device overview grid -->
        <Card variant="base" padding="none" class="mb-6">
            <div class="p-5 sm:p-6">
                <h3 class="text-[16px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                    <i class="fas fa-server text-mistral-primary"></i>
                    {{ t('fingerprint_devices.device_sync_overview') || 'Device Sync Overview' }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div
                        v-for="device in devices"
                        :key="device.id"
                        class="p-4 rounded-xl border border-mistral-hairline-soft bg-mistral-surface hover:border-mistral-primary/30 transition-colors"
                    >
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <Link
                                    :href="route('fingerprint-devices.show', device.id)"
                                    class="text-[14px] font-semibold text-mistral-ink hover:text-mistral-primary transition-colors"
                                >
                                    {{ device.name }}
                                </Link>
                                <p class="text-[11px] text-mistral-steel mt-0.5">
                                    {{ device.ip_address }}:{{ device.port }}
                                </p>
                            </div>
                            <Badge :text="device.status" :variant="statusVariant(device.status)" />
                        </div>

                        <div class="grid grid-cols-3 gap-2 mb-3">
                            <div class="text-center">
                                <p class="text-[18px] font-bold text-mistral-primary">{{ device.face_count }}</p>
                                <p class="text-[10px] text-mistral-steel">{{ t('fingerprint_devices.faces') || 'Faces' }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-[18px] font-bold text-mistral-info">{{ device.employee_count }}</p>
                                <p class="text-[10px] text-mistral-steel">{{ t('fingerprint_devices.employees') || 'Employees' }}</p>
                            </div>
                            <div class="text-center">
                                <p class="text-[18px] font-bold text-mistral-success">{{ formatPercent(device.health_percent) }}</p>
                                <p class="text-[10px] text-mistral-steel">{{ t('fingerprint_devices.health') || 'Health' }}</p>
                            </div>
                        </div>

                        <!-- Health bar -->
                        <div class="w-full h-2 bg-mistral-canvas rounded-full overflow-hidden mb-2">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="{
                                    'bg-mistral-success': device.health_percent >= 95,
                                    'bg-mistral-warning': device.health_percent >= 50 && device.health_percent < 95,
                                    'bg-mistral-danger': device.health_percent < 50,
                                }"
                                :style="{ width: device.health_percent + '%' }"
                            ></div>
                        </div>

                        <!-- Command status pills -->
                        <div class="flex gap-2 flex-wrap text-[10px]">
                            <span v-if="device.pending_commands > 0" class="px-2 py-0.5 rounded-full bg-mistral-warning/15 text-mistral-warning font-medium">
                                <i class="fas fa-hourglass-half mr-1"></i>{{ device.pending_commands }} pending
                            </span>
                            <span v-if="device.sending_commands > 0" class="px-2 py-0.5 rounded-full bg-mistral-info/15 text-mistral-info font-medium">
                                <i class="fas fa-paper-plane mr-1"></i>{{ device.sending_commands }} sending
                            </span>
                            <span v-if="device.failed_commands > 0" class="px-2 py-0.5 rounded-full bg-mistral-danger/15 text-mistral-danger font-medium">
                                <i class="fas fa-times-circle mr-1"></i>{{ device.failed_commands }} failed
                            </span>
                            <span v-if="device.completed_commands > 0" class="px-2 py-0.5 rounded-full bg-mistral-success/15 text-mistral-success font-medium">
                                <i class="fas fa-check-circle mr-1"></i>{{ device.completed_commands }} done
                            </span>
                        </div>

                        <div class="flex items-center justify-between mt-2">
                            <p v-if="device.last_pushed_at" class="text-[10px] text-mistral-stone">
                                <i class="fas fa-clock mr-1"></i>{{ t('fingerprint_devices.last_pushed') || 'Last push' }}: {{ formatDateTime(device.last_pushed_at) }}
                            </p>
                            <button
                                v-if="device.failed_commands > 0"
                                class="text-[10px] text-mistral-danger hover:text-mistral-danger/80 font-medium disabled:opacity-50 ml-auto"
                                :disabled="retryingDeviceId === device.id"
                                @click="retryDeviceFailed(device.id)"
                            >
                                <i :class="retryingDeviceId === device.id ? 'fas fa-spinner fa-spin mr-1' : 'fas fa-redo mr-1'"></i>
                                {{ t('fingerprint_devices.retry') || 'Retry' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Employee coverage summary -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[15px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                        <i class="fas fa-check-circle text-mistral-success"></i>
                        {{ t('fingerprint_devices.fully_synced') || 'Fully Synced' }}
                    </h3>
                    <div class="text-center py-4">
                        <p class="text-[36px] font-bold text-mistral-success">{{ fullySyncedEmployees.length }}</p>
                        <p class="text-[12px] text-mistral-steel">
                            {{ t('fingerprint_devices.all_devices_have_faces') || 'Employees with faces on all devices' }}
                        </p>
                    </div>
                </div>
            </Card>

            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[15px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                        <i class="fas fa-exclamation-triangle text-mistral-warning"></i>
                        {{ t('fingerprint_devices.partially_synced') || 'Partially Synced' }}
                    </h3>
                    <div class="text-center py-4">
                        <p class="text-[36px] font-bold text-mistral-warning">{{ partiallySyncedEmployees.length }}</p>
                        <p class="text-[12px] text-mistral-steel">
                            {{ t('fingerprint_devices.missing_some_devices') || 'Employees missing faces on some devices' }}
                        </p>
                    </div>
                    <div v-if="partiallySyncedEmployees.length > 0" class="mt-3 max-h-40 overflow-auto space-y-1">
                        <div
                            v-for="emp in partiallySyncedEmployees.slice(0, 5)"
                            :key="emp.id"
                            class="flex items-center justify-between text-[12px] p-2 rounded bg-mistral-canvas"
                        >
                            <span class="text-mistral-ink">{{ emp.name }}</span>
                            <Badge :text="`${emp.synced_devices}/${emp.total_active_devices}`" variant="pending" />
                        </div>
                        <p v-if="partiallySyncedEmployees.length > 5" class="text-[11px] text-mistral-stone text-center py-1">
                            +{{ partiallySyncedEmployees.length - 5 }} more...
                        </p>
                    </div>
                </div>
            </Card>

            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[15px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                        <i class="fas fa-user-slash text-mistral-danger"></i>
                        {{ t('fingerprint_devices.no_faces') || 'No Faces' }}
                    </h3>
                    <div class="text-center py-4">
                        <p class="text-[36px] font-bold text-mistral-danger">{{ noFaceEmployees.length }}</p>
                        <p class="text-[12px] text-mistral-steel">
                            {{ t('fingerprint_devices.employees_without_any_face') || 'Employees without any face template' }}
                        </p>
                    </div>
                    <div v-if="noFaceEmployees.length > 0" class="mt-3 max-h-40 overflow-auto space-y-1">
                        <div
                            v-for="emp in noFaceEmployees.slice(0, 5)"
                            :key="emp.id"
                            class="flex items-center justify-between text-[12px] p-2 rounded bg-mistral-canvas"
                        >
                            <span class="text-mistral-ink">{{ emp.name }}</span>
                            <span class="text-mistral-stone text-[11px]">{{ emp.employee_code }}</span>
                        </div>
                        <p v-if="noFaceEmployees.length > 5" class="text-[11px] text-mistral-stone text-center py-1">
                            +{{ noFaceEmployees.length - 5 }} more...
                        </p>
                    </div>
                </div>
            </Card>
        </div>
    </div>

    <!-- ===== DEVICES TAB ===== -->
    <div v-if="activeTab === 'devices'">
        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <h3 class="text-[16px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                    <i class="fas fa-server text-mistral-primary"></i>
                    {{ t('fingerprint_devices.device_face_sync_detail') || 'Device Face Sync Detail' }}
                </h3>

                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-mistral-hairline-soft">
                                <th class="text-left py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.device_name') || 'Device' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.status') || 'Status' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.faces') || 'Faces' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.employees') || 'Employees' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.pending') || 'Pending' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.sending') || 'Sending' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.failed') || 'Failed' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.completed') || 'Done' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.health') || 'Health' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.last_pushed') || 'Last Push' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="device in devices"
                                :key="device.id"
                                class="border-b border-mistral-hairline-soft hover:bg-mistral-canvas/50 transition-colors"
                            >
                                <td class="py-3 px-3">
                                    <div>
                                        <Link
                                            :href="route('fingerprint-devices.show', device.id)"
                                            class="font-medium text-mistral-ink hover:text-mistral-primary transition-colors"
                                        >
                                            {{ device.name }}
                                        </Link>
                                        <p class="text-[11px] text-mistral-stone">{{ device.ip_address }}:{{ device.port }}</p>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <Badge :text="device.status" :variant="statusVariant(device.status)" />
                                </td>
                                <td class="py-3 px-3 text-center font-semibold text-mistral-primary">{{ device.face_count }}</td>
                                <td class="py-3 px-3 text-center font-semibold text-mistral-info">{{ device.employee_count }}</td>
                                <td class="py-3 px-3 text-center">
                                    <span v-if="device.pending_commands > 0" class="text-mistral-warning font-semibold">
                                        {{ device.pending_commands }}
                                    </span>
                                    <span v-else class="text-mistral-stone">0</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span v-if="device.sending_commands > 0" class="text-mistral-info font-semibold">
                                        {{ device.sending_commands }}
                                    </span>
                                    <span v-else class="text-mistral-stone">0</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span v-if="device.failed_commands > 0" class="text-mistral-danger font-semibold">
                                        {{ device.failed_commands }}
                                    </span>
                                    <span v-else class="text-mistral-stone">0</span>
                                </td>
                                <td class="py-3 px-3 text-center font-semibold text-mistral-success">{{ device.completed_commands }}</td>
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-16 h-2 bg-mistral-canvas rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="{
                                                    'bg-mistral-success': device.health_percent >= 95,
                                                    'bg-mistral-warning': device.health_percent >= 50 && device.health_percent < 95,
                                                    'bg-mistral-danger': device.health_percent < 50,
                                                }"
                                                :style="{ width: device.health_percent + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-[11px] font-semibold" :class="{
                                            'text-mistral-success': device.health_percent >= 95,
                                            'text-mistral-warning': device.health_percent >= 50 && device.health_percent < 95,
                                            'text-mistral-danger': device.health_percent < 50,
                                        }">
                                            {{ formatPercent(device.health_percent) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-center text-[11px] text-mistral-stone">
                                    {{ formatDateTime(device.last_pushed_at) }}
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <Link
                                            :href="route('fingerprint-devices.sync', { device_id: device.id })"
                                            class="text-mistral-primary hover:text-mistral-primary/80 text-[12px] font-medium"
                                        >
                                            <i class="fas fa-sync-alt"></i>
                                        </Link>
                                        <button
                                            v-if="device.failed_commands > 0"
                                            class="text-mistral-danger hover:text-mistral-danger/80 text-[12px] font-medium disabled:opacity-50"
                                            :disabled="retryingDeviceId === device.id"
                                            :title="t('fingerprint_devices.retry_device_failed') || 'Retry failed commands for this device'"
                                            @click="retryDeviceFailed(device.id)"
                                        >
                                            <i :class="retryingDeviceId === device.id ? 'fas fa-spinner fa-spin' : 'fas fa-redo'"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Card>
    </div>

    <!-- ===== EMPLOYEES TAB ===== -->
    <div v-if="activeTab === 'employees'">
        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[16px] font-semibold text-mistral-ink flex items-center gap-2">
                        <i class="fas fa-users text-mistral-primary"></i>
                        {{ t('fingerprint_devices.employee_face_coverage') || 'Employee Face Coverage' }}
                    </h3>
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-mistral-stone text-[12px]"></i>
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('common.search') || 'Search...'"
                            class="pl-8 pr-3 py-1.5 text-[13px] border border-mistral-hairline-soft rounded-lg bg-mistral-surface text-mistral-ink focus:outline-none focus:ring-2 focus:ring-mistral-primary/30 focus:border-mistral-primary w-64"
                        />
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-[13px]">
                        <thead>
                            <tr class="border-b border-mistral-hairline-soft">
                                <th class="text-left py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.employee') || 'Employee' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.code') || 'Code' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.total_templates') || 'Templates' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.synced_devices_count') || 'Synced Devices' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.missing_devices_count') || 'Missing' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.coverage') || 'Coverage' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.status') || 'Status' }}</th>
                                <th class="text-center py-3 px-3 font-semibold text-mistral-steel">{{ t('fingerprint_devices.source') || 'Source' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="emp in filteredEmployees"
                                :key="emp.id"
                                class="border-b border-mistral-hairline-soft hover:bg-mistral-canvas/50 transition-colors cursor-pointer"
                                @click="openEmployeeDetail(emp)"
                            >
                                <td class="py-3 px-3">
                                    <span class="font-medium text-mistral-ink">{{ emp.name }}</span>
                                </td>
                                <td class="py-3 px-3 text-center text-mistral-stone">{{ emp.employee_code }}</td>
                                <td class="py-3 px-3 text-center font-semibold text-mistral-primary">{{ emp.total_templates }}</td>
                                <td class="py-3 px-3 text-center">
                                    <span class="font-semibold" :class="emp.synced_devices > 0 ? 'text-mistral-success' : 'text-mistral-stone'">
                                        {{ emp.synced_devices }} / {{ emp.total_active_devices }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span v-if="emp.missing_devices > 0" class="font-semibold text-mistral-danger">
                                        {{ emp.missing_devices }}
                                    </span>
                                    <span v-else class="text-mistral-success">
                                        <i class="fas fa-check"></i>
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <div class="w-16 h-2 bg-mistral-canvas rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="{
                                                    'bg-mistral-success': emp.coverage_percent >= 95,
                                                    'bg-mistral-warning': emp.coverage_percent >= 50 && emp.coverage_percent < 95,
                                                    'bg-mistral-danger': emp.coverage_percent < 50,
                                                }"
                                                :style="{ width: emp.coverage_percent + '%' }"
                                            ></div>
                                        </div>
                                        <span class="text-[11px] font-semibold">{{ formatPercent(emp.coverage_percent) }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <Badge
                                        v-if="emp.is_fully_synced"
                                        :text="t('fingerprint_devices.synced') || 'Synced'"
                                        variant="active"
                                    />
                                    <Badge
                                        v-else-if="emp.total_templates === 0"
                                        :text="t('fingerprint_devices.no_data') || 'No Data'"
                                        variant="absent"
                                    />
                                    <Badge
                                        v-else
                                        :text="t('fingerprint_devices.partial') || 'Partial'"
                                        variant="pending"
                                    />
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <span v-if="emp.source_devices.length" class="text-[11px] text-mistral-stone">
                                        {{ emp.source_devices.join(', ') }}
                                    </span>
                                    <span v-else class="text-mistral-stone">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div v-if="filteredEmployees.length === 0" class="text-center py-12 text-mistral-steel">
                        <i class="fas fa-search text-[32px] mb-2"></i>
                        <p>{{ t('common.no_results') || 'No results found' }}</p>
                    </div>
                </div>
            </div>
        </Card>

        <!-- Employee detail modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showSyncDetail && selectedEmployee"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    @click.self="showSyncDetail = false"
                >
                    <div class="absolute inset-0 bg-mistral-ink/40 backdrop-blur-sm"></div>
                    <Card
                        variant="base"
                        padding="none"
                        class="relative z-10 flex w-full max-w-lg max-h-[90vh] flex-col overflow-hidden rounded-2xl shadow-level-4"
                    >
                        <div class="flex items-center justify-between border-b border-mistral-hairline-soft px-6 py-5">
                            <h3 class="text-[16px] font-semibold text-mistral-ink">{{ selectedEmployee.name }}</h3>
                            <button class="text-mistral-stone hover:text-mistral-ink" @click="showSyncDetail = false">
                                <i class="fas fa-times text-[16px]"></i>
                            </button>
                        </div>
                        <p class="px-6 pb-4 text-[12px] text-mistral-steel">
                            Code: {{ selectedEmployee.employee_code }} | Coverage: {{ formatPercent(selectedEmployee.coverage_percent) }}
                        </p>
                        <div class="flex-1 overflow-y-auto px-6 pb-6">
                            <h4 class="text-[13px] font-semibold text-mistral-ink mb-3">
                                {{ t('fingerprint_devices.device_sync_status') || 'Device Sync Status' }}
                            </h4>
                            <div class="space-y-2">
                                <div
                                    v-for="device in devices"
                                    :key="device.id"
                                    class="flex items-center justify-between p-3 rounded-lg border"
                                    :class="
                                        selectedEmployee.missing_device_ids.includes(device.id)
                                            ? 'border-mistral-danger/30 bg-mistral-danger/5'
                                            : 'border-mistral-success/30 bg-mistral-success/5'
                                    "
                                >
                                    <div>
                                        <p class="text-[13px] font-medium text-mistral-ink">{{ device.name }}</p>
                                        <p class="text-[11px] text-mistral-stone">{{ device.ip_address }}:{{ device.port }}</p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Badge
                                            v-if="selectedEmployee.missing_device_ids.includes(device.id)"
                                            :text="t('fingerprint_devices.missing') || 'Missing'"
                                            variant="absent"
                                        />
                                        <Badge
                                            v-else
                                            :text="t('fingerprint_devices.synced') || 'Synced'"
                                            variant="active"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>
            </Transition>
        </Teleport>
    </div>

    <!-- ===== COMMAND QUEUE TAB ===== -->
    <div v-if="activeTab === 'queue'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Queue Summary -->
            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                        <i class="fas fa-layer-group text-mistral-primary"></i>
                        {{ t('fingerprint_devices.face_command_queue') || 'Face Command Queue' }}
                    </h3>

                    <div class="grid grid-cols-2 gap-3 mb-4">
                        <div class="p-3 rounded-lg bg-mistral-warning/10 text-center">
                            <p class="text-[24px] font-bold text-mistral-warning">{{ commandQueue.pending }}</p>
                            <p class="text-[11px] text-mistral-stone">{{ t('fingerprint_devices.pending') || 'Pending' }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-mistral-info/10 text-center">
                            <p class="text-[24px] font-bold text-mistral-info">{{ commandQueue.sending }}</p>
                            <p class="text-[11px] text-mistral-stone">{{ t('fingerprint_devices.sending') || 'Sending' }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-mistral-success/10 text-center">
                            <p class="text-[24px] font-bold text-mistral-success">{{ commandQueue.completed }}</p>
                            <p class="text-[11px] text-mistral-stone">{{ t('fingerprint_devices.completed') || 'Completed' }}</p>
                        </div>
                        <div class="p-3 rounded-lg bg-mistral-danger/10 text-center">
                            <p class="text-[24px] font-bold text-mistral-danger">{{ commandQueue.failed }}</p>
                            <p class="text-[11px] text-mistral-stone">{{ t('fingerprint_devices.failed') || 'Failed' }}</p>
                        </div>
                    </div>

                    <!-- Per-device breakdown -->
                    <div v-if="Object.keys(commandQueue.per_device || {}).length > 0">
                        <h4 class="text-[13px] font-semibold text-mistral-ink mb-2">
                            {{ t('fingerprint_devices.per_device_breakdown') || 'Per-Device Breakdown' }}
                        </h4>
                        <div class="space-y-2 max-h-60 overflow-auto">
                            <div
                                v-for="(stats, deviceName) in commandQueue.per_device"
                                :key="deviceName"
                                class="p-2 rounded-lg bg-mistral-canvas"
                            >
                                <p class="text-[12px] font-medium text-mistral-ink mb-1">{{ deviceName }}</p>
                                <div class="flex gap-2 text-[10px]">
                                    <span v-if="stats.pending" class="text-mistral-warning">⏳ {{ stats.pending }}</span>
                                    <span v-if="stats.sending" class="text-mistral-info">📤 {{ stats.sending }}</span>
                                    <span v-if="stats.completed" class="text-mistral-success">✅ {{ stats.completed }}</span>
                                    <span v-if="stats.failed" class="text-mistral-danger">❌ {{ stats.failed }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Recent Logs -->
            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold text-mistral-ink mb-4 flex items-center gap-2">
                        <i class="fas fa-history text-mistral-info"></i>
                        {{ t('fingerprint_devices.recent_sync_logs') || 'Recent Sync Logs' }}
                    </h3>

                    <div class="space-y-2 max-h-[400px] overflow-auto">
                        <div
                            v-for="log in recentLogs"
                            :key="log.id"
                            class="p-3 rounded-lg border border-mistral-hairline-soft"
                        >
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[13px] font-medium text-mistral-ink">{{ log.device_name }}</span>
                                <Badge
                                    :text="log.status"
                                    :variant="log.status === 'completed' ? 'active' : log.status === 'failed' ? 'absent' : 'pending'"
                                />
                            </div>
                            <div class="flex items-center gap-3 text-[11px] text-mistral-stone">
                                <span><i class="fas fa-arrow-right mr-1"></i>{{ log.direction }}</span>
                                <span v-if="log.duration_seconds">{{ parseFloat(log.duration_seconds).toFixed(1) }}s</span>
                                <span>{{ formatDateTime(log.started_at) }}</span>
                            </div>
                            <div v-if="log.errors?.length" class="mt-1 text-[10px] text-mistral-danger">
                                {{ log.errors.length }} error(s)
                            </div>
                        </div>

                        <div v-if="recentLogs.length === 0" class="text-center py-8 text-mistral-stone">
                            <i class="fas fa-inbox text-[24px] mb-2"></i>
                            <p class="text-[12px]">{{ t('fingerprint_devices.no_logs') || 'No recent logs' }}</p>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Failed Commands -->
        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[16px] font-semibold text-mistral-ink flex items-center gap-2">
                        <i class="fas fa-exclamation-circle text-mistral-danger"></i>
                        {{ t('fingerprint_devices.failed_face_commands') || 'Failed Face Commands' }}
                        <Badge v-if="failedCommands.length" :text="String(failedCommands.length)" variant="absent" />
                    </h3>
                    <Button
                        v-if="failedCommands.length > 0"
                        variant="secondary"
                        icon="fas fa-redo"
                        :disabled="retryingFailed"
                        @click="retryAllFailed"
                    >
                        <i v-if="retryingFailed" class="fas fa-spinner fa-spin mr-1"></i>
                        {{ retryingFailed ? (t('common.please_wait') || 'Please wait...') : (t('fingerprint_devices.retry_all_failed') || 'Retry All Failed') }}
                    </Button>
                </div>

                <div v-if="failedCommands.length === 0" class="text-center py-8 text-mistral-stone">
                    <i class="fas fa-check-circle text-[32px] text-mistral-success mb-2"></i>
                    <p class="text-[13px]">{{ t('fingerprint_devices.no_failed_commands') || 'No failed face commands' }}</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-[12px]">
                        <thead>
                            <tr class="border-b border-mistral-hairline-soft">
                                <th class="text-left py-2 px-3 font-semibold text-mistral-steel">Device</th>
                                <th class="text-left py-2 px-3 font-semibold text-mistral-steel">Error</th>
                                <th class="text-center py-2 px-3 font-semibold text-mistral-steel">Retries</th>
                                <th class="text-center py-2 px-3 font-semibold text-mistral-steel">Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="cmd in failedCommands"
                                :key="cmd.id"
                                class="border-b border-mistral-hairline-soft"
                            >
                                <td class="py-2 px-3">
                                    <Link
                                        :href="route('fingerprint-devices.show', cmd.device_id)"
                                        class="text-mistral-ink hover:text-mistral-primary font-medium"
                                    >
                                        {{ cmd.device_name }}
                                    </Link>
                                </td>
                                <td class="py-2 px-3 text-mistral-danger max-w-[300px] truncate" :title="cmd.error_message">
                                    {{ cmd.error_message || '—' }}
                                </td>
                                <td class="py-2 px-3 text-center">
                                    {{ cmd.retry_count }} / {{ cmd.max_retries }}
                                </td>
                                <td class="py-2 px-3 text-center text-mistral-stone">
                                    {{ formatDateTime(cmd.updated_at) }}
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <button
                                        class="text-mistral-primary hover:text-mistral-primary/80 text-[12px] font-medium disabled:opacity-50"
                                        :disabled="retryingDeviceId === cmd.device_id"
                                        :title="t('fingerprint_devices.retry_device') || 'Retry this device'"
                                        @click="retryDeviceFailed(cmd.device_id)"
                                    >
                                        <i :class="retryingDeviceId === cmd.device_id ? 'fas fa-spinner fa-spin' : 'fas fa-redo'"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Card>
    </div>
</template>
