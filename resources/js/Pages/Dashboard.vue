<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    stats: {
        type: Object,
        default: () => ({
            employees: 0,
            present_today: 0,
            absent_today: 0,
            on_vacation: 0,
            pending_requests: 0,
            active_devices: 0,
        }),
    },
    recentAttendance: {
        type: Array,
        default: () => [],
    },
});

const statsData = ref({ ...props.stats });
const recentData = ref([...(props.recentAttendance || [])]);
const showForceHoliday = ref(false);

const POLL_INTERVAL_MS = 10000;

let pollTimer = null;
const inflightControllers = new Set();
let isPolling = false;
let isTabVisible = !document.hidden;
let isComponentMounted = true;

const statCards = ref([
    { label: 'إجمالي الموظفين', key: 'employees', icon: 'fas fa-users', color: 'primary' },
    { label: 'الحاضرون اليوم', key: 'present_today', icon: 'fas fa-user-check', color: 'success' },
    { label: 'الغائبون اليوم', key: 'absent_today', icon: 'fas fa-user-times', color: 'danger' },
    { label: 'في إجازة', key: 'on_vacation', icon: 'fas fa-umbrella-beach', color: 'vacation' },
    { label: 'طلبات قيد الانتظار', key: 'pending_requests', icon: 'fas fa-hourglass-half', color: 'warning' },
    { label: 'أجهزة متصلة', key: 'active_devices', icon: 'fas fa-fingerprint', color: 'info' },
]);

function getInitials(name) {
    if (!name) return '؟';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return parts[0].substring(0, 2).toUpperCase();
}

async function fetchJson(url) {
    const controller = new AbortController();
    inflightControllers.add(controller);
    try {
        const res = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        });
        if (!res.ok) return null;
        return await res.json();
    } catch (e) {
        if (e.name !== 'AbortError') {
            // ignore other network errors silently during polling
        }
        return null;
    } finally {
        inflightControllers.delete(controller);
    }
}

async function poll() {
    if (isPolling || !isTabVisible || !isComponentMounted) {
        scheduleNext();
        return;
    }
    isPolling = true;
    try {
        const [pullJson, snapshotJson] = await Promise.all([
            fetchJson(route('dashboard.pullEvents')),
            fetchJson(route('dashboard.snapshot')),
        ]);
        if (pullJson?.events) recentData.value = pullJson.events;
        if (snapshotJson?.stats) statsData.value = snapshotJson.stats;
    } finally {
        isPolling = false;
        scheduleNext();
    }
}

function scheduleNext() {
    if (!isComponentMounted) return;
    if (pollTimer) clearTimeout(pollTimer);
    pollTimer = setTimeout(poll, POLL_INTERVAL_MS);
}

function handleVisibilityChange() {
    isTabVisible = !document.hidden;
    if (isTabVisible && !pollTimer) {
        poll();
    }
}

onMounted(() => {
    document.addEventListener('visibilitychange', handleVisibilityChange);
    poll();
});

onBeforeUnmount(() => {
    isComponentMounted = false;
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    if (pollTimer) clearTimeout(pollTimer);
    inflightControllers.forEach((c) => c.abort());
    inflightControllers.clear();
});
</script>

<template>
    <AppLayout title="لوحة التحكم">
        <div class="space-y-6">
            <!-- Stat cards grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                    v-for="card in statCards"
                    :key="card.key"
                    :label="card.label"
                    :value="statsData[card.key] ?? 0"
                    :icon="card.icon"
                    :color="card.color"
                />
            </div>

            <!-- Real-time attendance card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2 text-green-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            <span class="text-sm font-semibold">في الوقت الحقيقي رصيد</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-600">فرض عطلة</span>
                        <button
                            type="button"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="showForceHoliday ? 'bg-green-500' : 'bg-gray-300'"
                            @click="showForceHoliday = !showForceHoliday"
                        >
                            <span
                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform"
                                :class="showForceHoliday ? 'translate-x-6' : 'translate-x-1'"
                            />
                        </Button>
                        <div class="flex items-center gap-1 text-green-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Attendance list -->
                <div v-if="recentData.length > 0" class="divide-y divide-gray-100">
                    <div
                        v-for="log in recentData"
                        :key="log.id"
                        class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors"
                    >
                        <!-- Right side: Avatar + Name + ID -->
                        <div class="flex items-center gap-3">
                            <!-- Avatar -->
                            <div class="relative">
                                <img
                                    v-if="log.avatar_url"
                                    :src="log.avatar_url"
                                    :alt="log.employee_name"
                                    class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm"
                                />
                                <div
                                    v-else
                                    class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-bold shadow-sm"
                                >
                                    {{ getInitials(log.employee_name) }}
                                </div>
                                <!-- Live indicator -->
                                <span
                                    v-if="log.source === 'live'"
                                    class="absolute -top-1 -right-1 w-3.5 h-3.5 bg-green-500 border-2 border-white rounded-full animate-pulse"
                                ></span>
                                <span
                                    v-else
                                    class="absolute bottom-0 left-0 w-3 h-3 bg-gray-400 border-2 border-white rounded-full"
                                ></span>
                            </div>
                            <!-- Name + ID -->
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-gray-900">{{ log.employee_name }}</span>
                                    <span
                                        v-if="log.source === 'live'"
                                        class="inline-flex items-center gap-1 px-1.5 py-0.5 text-[10px] font-bold text-green-700 bg-green-100 rounded-full"
                                    >
                                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                        LIVE
                                    </span>
                                </div>
                                <div class="text-xs text-gray-500">{{ log.employee_code }}</div>
                            </div>
                        </div>

                        <!-- Center: Type + Time + Device -->
                        <div class="flex items-center gap-4">
                            <!-- Device icon -->
                            <div class="flex items-center gap-1 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <!-- Check In/Out label -->
                            <span
                                class="text-xs font-medium px-2 py-1 rounded-full"
                                :class="log.status === 'check_in' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ log.type === 'دخول' ? 'Check In' : 'Check Out' }}
                            </span>
                            <!-- Time -->
                            <div class="text-sm text-gray-700 font-mono" dir="ltr">
                                {{ log.time }}
                            </div>
                            <!-- Device name -->
                            <div class="text-xs text-gray-500 max-w-[120px] truncate" :title="log.device_name">
                                {{ log.device_name || '—' }}
                            </div>
                        </div>

                        <!-- Left side: Action icon -->
                        <div class="flex items-center">
                            <button class="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-else class="flex flex-col items-center justify-center py-16 text-center">
                    <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                        <i class="fas fa-inbox text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-600 mb-1">لا توجد بيانات</h3>
                    <p class="text-sm text-gray-400">لم يتم تسجيل أي حضور بعد</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
