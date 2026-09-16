<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { PageHeader, DataTable, ConfirmDialog, Badge, Button, Alert, StatCard, Card } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';
import { usePageTitle } from '@/composables/usePageTitle';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    backups: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
    health: { type: Object, default: () => ({}) },
});

const creating = ref(false);
const showDelete = ref(false);
const selected = ref(null);

// Format date in Arabic locale with RTL-friendly format
function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('ar-EG', {
            year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    } catch {
        return dateStr;
    }
}

const columns = computed(() => [
    { key: 'id', label: 'ID', sortable: true },
    { key: 'file_name', label: t('backups.file'), sortable: true },
    { key: 'type', label: t('backups.type'), cellClass: 'text-center' },
    { key: 'status', label: t('backups.status'), cellClass: 'text-center' },
    { key: 'file_size', label: t('backups.size'), cellClass: 'text-center' },
    { key: 'verification_status', label: t('backups.verification'), cellClass: 'text-center' },
    { key: 'created_at', label: t('backups.created_at'), sortable: true },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[240px]' },
]);

function formatSize(bytes) {
    if (!bytes) return '—';
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function statusLabel(s) {
    return t(`backups.status_${s}`) || s;
}

function typeLabel(s) {
    return t(`backups.type_${s}`) || s;
}

function statusVariant(s) {
    return { completed: 'success', running: 'warning', failed: 'danger', pending: 'info' }[s] || 'info';
}

function verifyVariant(s) {
    return { verified: 'success', failed: 'danger', pending: 'warning' }[s] || 'info';
}

function createBackup() {
    creating.value = true;
    router.post(route('backups.store'), {}, {
        preserveScroll: true,
        onFinish: () => { creating.value = false; },
    });
}

function verify(id) {
    router.post(route('backups.verify', id), {}, { preserveScroll: true });
}

function restoreTest(id) {
    router.post(route('backups.restore-test', id), {}, { preserveScroll: true });
}

function confirmDelete(row) {
    selected.value = row;
    showDelete.value = true;
}

function performDelete() {
    if (!selected.value) return;
    router.delete(route('backups.destroy', selected.value.id), { preserveScroll: true });
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

usePageTitle(t('backups.title'));
</script>

<template>
    <PageHeader :title="t('backups.title')" :description="t('backups.index_description')">
        <template #actions>
            <Button variant="primary" icon="fas fa-database" :loading="creating" @click="createBackup">
                {{ creating ? t('backups.creating') : t('backups.create_backup') }}
            </Button>
            <Button variant="secondary" icon="fas fa-cog" :href="route('backups.settings')">
                {{ t('backups.settings_title') }}
            </Button>
        </template>
    </PageHeader>

    <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
    <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <StatCard :label="t('backups.health_db')" :value="health.database || 'hrmair'" icon="fas fa-database" color="primary" />
        <StatCard :label="t('backups.health_tables')" :value="`${health.tables} ${t('backups.health_tables').toLowerCase()}`" icon="fas fa-table" color="info" />
        <StatCard :label="t('backups.health_last_verified')" :value="health.latest_verified_id ? `#${health.latest_verified_id}` : t('backups.health_never')" icon="fas fa-shield-halved" color="success" />
    </div>

    <Card>
        <DataTable
            :columns="columns"
            :data="backups"
            :filters="filters"
            :route-name="'backups.index'"
            :only="['backups', 'filters', 'health']"
            storage-key="backups"
        >
            <template #cell-file_size="{ row }">
                {{ formatSize(row.file_size) }}
            </template>

            <template #cell-type="{ row }">
                <Badge :text="typeLabel(row.type)" variant="info" />
            </template>

            <template #cell-status="{ row }">
                <Badge :text="statusLabel(row.status)" :variant="statusVariant(row.status)" />
            </template>

            <template #cell-verification_status="{ row }">
                <Badge :text="statusLabel(row.verification_status)" :variant="verifyVariant(row.verification_status)" />
            </template>

            <template #cell-created_at="{ row }">
                {{ formatDate(row.created_at) }}
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1 flex-wrap">
                    <Button variant="ghost" size="sm" icon="fas fa-eye" :href="route('backups.show', row.id)" :title="t('backups.details')" />
                    <Button variant="ghost" size="sm" icon="fas fa-check" :title="t('backups.verify_now')" @click="verify(row.id)" />
                    <Button variant="ghost" size="sm" icon="fas fa-download" :href="route('backups.download', row.id)" :title="t('backups.download')" />
                    <Button variant="ghost" size="sm" icon="fas fa-flask" :title="t('backups.test_restore_now')" @click="restoreTest(row.id)" />
                    <Button variant="ghost" size="sm" icon="fas fa-trash" :title="t('backups.delete')" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>
    </Card>

    <ConfirmDialog
        v-model="showDelete"
        :title="t('backups.confirm_delete_title')"
        :message="t('backups.confirm_delete_message')"
        :confirm-text="t('common.delete')"
        confirm-variant="danger"
        @confirm="performDelete"
    />
</template>
