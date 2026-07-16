<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    devices: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    deviceTypes: { type: Array, default: () => [] },
    branches: { type: Array, default: () => [] },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedDevice = ref(null);
const syncingAll = ref(false);
const syncResult = ref(null);
const showSyncResult = ref(false);

const columns = computed(() => [
    { key: 'name', label: t('fingerprint_devices.device_name'), sortable: true },
    { key: 'serial_number', label: t('fingerprint_devices.serial_number'), sortable: true },
    { key: 'ip_address', label: t('fingerprint_devices.ip_address') },
    { key: 'device_type', label: t('fingerprint_devices.device_type') },
    { key: 'branch', label: t('fingerprint_devices.branch') },
    { key: 'status', label: t('common.status'), cellClass: 'text-center' },
    { key: 'last_seen_at', label: t('fingerprint_devices.last_seen'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[200px]' },
]);

const statusVariant = (status) => {
    const map = { online: 'active', offline: 'inactive', maintenance: 'pending', deactivated: 'inactive' };
    return map[status] || 'inactive';
};

const statusOptions = [
    { value: 'online', label: t('fingerprint_devices.online') },
    { value: 'offline', label: t('fingerprint_devices.offline') },
    { value: 'maintenance', label: t('fingerprint_devices.maintenance') },
    { value: 'deactivated', label: t('fingerprint_devices.deactivated') },
];

const deviceTypeOptions = computed(() => [
    { value: '', label: t('fingerprint_devices.all_types') },
    ...props.deviceTypes.map((dt) => ({ value: dt.id, label: dt.name })),
]);

const branchOptions = computed(() => [
    { value: '', label: t('fingerprint_devices.all_branches') },
    ...props.branches.map((b) => ({ value: b.id, label: b.branch_name })),
]);

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' });
}

function onSearch(value) {
    router.get(
        route('fingerprint-devices.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function applyFilter(key, value) {
    const payload = { ...props.filters, [key]: value };
    if (value === '' || value === null || value === undefined) {
        delete payload[key];
    }
    router.get(
        route('fingerprint-devices.index'),
        payload,
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters() {
    router.get(
        route('fingerprint-devices.index'),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(device) {
    selectedDevice.value = device;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedDevice.value) return;
    router.delete(route('fingerprint-devices.destroy', selectedDevice.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

async function syncAllDevices() {
    syncingAll.value = true;
    syncResult.value = null;
    try {
        const res = await fetch(route('fingerprint-devices.sync-all'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': decodeURIComponent(
                    document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '',
                ),
            },
            credentials: 'same-origin',
        });
        const json = await res.json();
        syncResult.value = json;
        showSyncResult.value = true;
        router.reload({ only: ['devices'] });
    } catch (e) {
        syncResult.value = { success: false, error: e.message };
        showSyncResult.value = true;
    } finally {
        syncingAll.value = false;
    }
}
</script>

<template>
    <AppLayout :title="t('fingerprint_devices.title')">
        <PageHeader
            :title="t('fingerprint_devices.title')"
            :description="t('fingerprint_devices.index_description')"
        >
            <template #actions>
                <Button
                    variant="dark"
                    :icon="syncingAll ? 'fas fa-spinner fa-spin' : 'fas fa-sync-alt'"
                    :loading="syncingAll"
                    @click="syncAllDevices"
                >
                    {{ syncingAll ? 'جاري المزامنة...' : 'مزامنة الكل' }}
                </Button>
                <Button variant="secondary" icon="fas fa-chart-bar" :href="route('fingerprint-devices.dashboard')">
                    {{ t('fingerprint_devices.dashboard') }}
                </Button>
                <Button variant="secondary" icon="fas fa-bolt" :href="route('fingerprint-devices.live-scan')">
                    {{ t('fingerprint_devices.live_scan') }}
                </Button>
                <Button variant="secondary" icon="fas fa-cloud-download-alt" :href="route('fingerprint-devices.sync')">
                    {{ t('fingerprint_devices.sync_title') }}
                </Button>
                <Button variant="primary" icon="fas fa-plus" :href="route('fingerprint-devices.create')">
                    {{ t('fingerprint_devices.add_device') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <div class="card p-6 mb-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <SearchInput
                        v-model="search"
                        :placeholder="t('common.search')"
                        @search="onSearch"
                    />
                    <FormSelect
                        :model-value="filters.status ?? ''"
                        :options="[
                            { value: '', label: t('common.all_statuses') },
                            ...statusOptions,
                        ]"
                        class="max-w-[180px]"
                        @update:model-value="(v) => applyFilter('status', v)"
                    />
                    <FormSelect
                        v-if="deviceTypes.length"
                        :model-value="filters.device_type_id ?? ''"
                        :options="deviceTypeOptions"
                        class="max-w-[180px]"
                        @update:model-value="(v) => applyFilter('device_type_id', v)"
                    />
                    <FormSelect
                        v-if="branches.length"
                        :model-value="filters.branch_id ?? ''"
                        :options="branchOptions"
                        class="max-w-[180px]"
                        @update:model-value="(v) => applyFilter('branch_id', v)"
                    />
                    <Button
                        v-if="Object.keys(filters).length"
                        variant="ghost"
                        icon="fas fa-times"
                        @click="clearFilters"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                </div>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :data="devices"
            :empty-title="t('fingerprint_devices.no_devices_title')"
            :empty-description="t('fingerprint_devices.no_devices_description')"
        >
            <template #cell-device_type="{ row }">
                <span v-if="row.device_type">{{ row.device_type.name }}</span>
                <span v-else class="text-mistral-stone">—</span>
            </template>

            <template #cell-branch="{ row }">
                <span v-if="row.branch">{{ row.branch.branch_name }}</span>
                <span v-else class="text-mistral-stone">—</span>
            </template>

            <template #cell-status="{ row }">
                <Badge :text="t('fingerprint_devices.' + row.status)" :variant="statusVariant(row.status)" />
            </template>

            <template #cell-last_seen_at="{ row }">
                <span class="text-[12px] text-mistral-steel">{{ formatDate(row.last_seen_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('fingerprint-devices.show', row.id)" />
                    <IconButton icon="fas fa-edit" :aria-label="t('common.edit')" :href="route('fingerprint-devices.edit', row.id)" />
                    <IconButton icon="fas fa-plug" :aria-label="t('fingerprint_devices.test_connection')" @click="router.post(route('fingerprint-devices.test-connection', row.id), {}, { preserveScroll: true })" />
                    <IconButton icon="fas fa-cloud-download-alt" :aria-label="t('fingerprint_devices.sync_title')" :href="route('fingerprint-devices.sync', { device_id: row.id })" />
                    <IconButton icon="fas fa-trash" :aria-label="t('common.delete')" variant="danger" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('fingerprint_devices.delete_confirm_title')"
            :message="t('fingerprint_devices.delete_confirm_message', { name: selectedDevice?.name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />

        <Teleport to="body">
            <div v-if="showSyncResult" class="fixed inset-0 z-[9999] flex items-center justify-center" style="background: rgba(0,0,0,0.5);">
                <div class="card p-6 w-[520px] max-h-[85vh] overflow-y-auto mx-4 shadow-level-4 z-10">
                    <div class="flex items-center justify-between px-6 py-5 border-b border-mistral-hairline-soft">
                        <h3 class="text-xl font-bold text-mistral-ink">نتائج المزامنة</h3>
                        <Button
                            variant="icon"
                            icon="fas fa-times"
                            aria-label="إغلاق"
                            @click="showSyncResult = false"
                        />
                    </div>

                    <div class="p-6">
                        <div v-if="syncResult?.success" class="space-y-5">
                            <Alert
                                type="success"
                                :message="`تمت المزامنة بنجاح - تم مزامنة ${syncResult.devices_synced} جهاز بصمة`"
                            />

                            <div class="grid grid-cols-2 gap-4">
                                <Card variant="cream-soft" padding="md" class="text-center">
                                    <div class="text-3xl font-extrabold text-mistral-info">{{ syncResult.total_users_matched }}</div>
                                    <div class="text-sm text-mistral-info mt-1">موظف تم مزامنتهم</div>
                                </Card>
                                <Card variant="cream-soft" padding="md" class="text-center">
                                    <div class="text-3xl font-extrabold text-mistral-success">{{ syncResult.total_attendance_pulled }}</div>
                                    <div class="text-sm text-mistral-success mt-1">سجل حضور</div>
                                </Card>
                            </div>

                            <div v-if="syncResult.results?.length">
                                <div class="text-sm font-semibold text-mistral-ink mb-2">تفاصيل كل جهاز:</div>
                                <div class="space-y-2">
                                    <div
                                        v-for="r in syncResult.results"
                                        :key="r.device_id"
                                        class="flex items-center justify-between p-3 bg-mistral-surface rounded-xl border border-mistral-hairline-soft"
                                    >
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-fingerprint text-mistral-stone"></i>
                                            <span class="font-semibold text-sm text-mistral-ink">{{ r.device_name }}</span>
                                        </div>
                                        <span class="text-sm text-mistral-success font-medium">
                                            {{ r.users_matched }} موظف / {{ r.attendance_pulled }} سجل
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="space-y-5">
                            <Alert
                                type="danger"
                                :message="`فشلت المزامنة - ${syncResult?.error || 'خطأ غير معروف'}`"
                            />
                            <div v-if="syncResult?.errors?.length">
                                <div class="text-sm font-semibold text-mistral-ink mb-2">الأجهزة التي فشلت:</div>
                                <div class="space-y-2">
                                    <div
                                        v-for="e in syncResult.errors"
                                        :key="e.device_id"
                                        class="flex items-center justify-between p-3 bg-mistral-danger-bg rounded-xl border border-mistral-hairline-soft"
                                    >
                                        <span class="font-semibold text-sm text-mistral-danger">{{ e.device_name }}</span>
                                        <span class="text-xs text-mistral-danger max-w-[200px] truncate">{{ e.error }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-mistral-hairline-soft flex justify-end">
                        <Button variant="primary" @click="showSyncResult = false">
                            حسنا
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
