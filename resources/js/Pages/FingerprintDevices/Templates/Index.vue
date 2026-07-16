<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    templates: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedTemplate = ref(null);

const columns = computed(() => [
    { key: 'id', label: t('fingerprint_devices.id'), sortable: true, cellClass: 'text-center w-[80px]' },
    { key: 'user', label: t('fingerprint_devices.user') },
    { key: 'device', label: t('fingerprint_devices.device_name') },
    { key: 'finger_id', label: t('fingerprint_devices.finger_id'), cellClass: 'text-center' },
    { key: 'quality', label: t('fingerprint_devices.template_quality'), cellClass: 'text-center' },
    { key: 'is_master', label: t('fingerprint_devices.is_master'), cellClass: 'text-center' },
    { key: 'synced_at', label: t('fingerprint_devices.synced_at'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[120px]' },
]);

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('en-GB', { dateStyle: 'short' });
}

function onSearch(value) {
    router.get(
        route('fingerprint-templates.index'),
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
        route('fingerprint-templates.index'),
        payload,
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(template) {
    selectedTemplate.value = template;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedTemplate.value) return;
    router.delete(route('fingerprint-templates.destroy', selectedTemplate.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('fingerprint_devices.templates')">
        <PageHeader
            :title="t('fingerprint_devices.templates')"
            :description="t('fingerprint_devices.templates_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>

        <div class="card p-4 mb-4 flex items-center gap-3 flex-wrap">
            <SearchInput
                v-model="search"
                :placeholder="t('common.search')"
                @search="onSearch"
            />
            <select
                class="form-input max-w-[180px]"
                :value="filters.is_master ?? ''"
                @change="applyFilter('is_master', $event.target.value === '' ? '' : $event.target.value === '1')"
            >
                <option value="">{{ t('fingerprint_devices.all_templates') }}</option>
                <option value="1">{{ t('fingerprint_devices.master_only') }}</option>
                <option value="0">{{ t('fingerprint_devices.non_master') }}</option>
            </select>
            <select
                class="form-input max-w-[180px]"
                :value="filters.finger_id ?? ''"
                @change="applyFilter('finger_id', $event.target.value)"
            >
                <option value="">{{ t('fingerprint_devices.all_fingers') }}</option>
                <option v-for="i in 10" :key="i" :value="i - 1">{{ t('fingerprint_devices.finger') }} {{ i - 1 }}</option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="templates"
            :empty-title="t('fingerprint_devices.no_templates_title')"
            :empty-description="t('fingerprint_devices.no_templates_description')"
        >
            <template #cell-user="{ row }">
                <span v-if="row.user">{{ row.user.name }}</span>
                <span v-else class="text-[var(--color-ink-subtle)]">—</span>
            </template>

            <template #cell-device="{ row }">
                <span v-if="row.device">{{ row.device.name }}</span>
                <span v-else class="text-[var(--color-ink-subtle)]">—</span>
            </template>

            <template #cell-is_master="{ row }">
                <Badge
                    v-if="row.is_master"
                    :text="t('fingerprint_devices.is_master')"
                    variant="active"
                />
                <span v-else class="text-[var(--color-ink-subtle)]">—</span>
            </template>

            <template #cell-synced_at="{ row }">
                <span class="text-[12px] text-[var(--color-ink-muted)]">{{ formatDate(row.synced_at) }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <Link
                        :href="route('fingerprint-templates.show', row.id)"
                        class="btn-icon text-[var(--color-info)]"
                        :title="t('common.view')"
                    >
                        <i class="fas fa-eye"></i>
                    </Link>
                    <button
                        type="button"
                        class="btn-icon text-[var(--color-danger)]"
                        :title="t('common.delete')"
                        @click.stop="confirmDelete(row)"
                    >
                        <i class="fas fa-trash"></i>
                    </Button>
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('fingerprint_devices.delete_template_confirm_title')"
            :message="t('fingerprint_devices.delete_template_confirm_message')"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
