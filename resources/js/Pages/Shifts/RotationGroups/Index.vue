<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    groups: { type: Object, default: () => ({ data: [], links: [] }) },
    rotations: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const columns = computed(() => [
    { key: 'rotation.name', label: t('shifts.rotation'), sortable: true },
    { key: 'name', label: t('shifts.group_name'), sortable: true },
    { key: 'group_index', label: t('shifts.group_index'), cellClass: 'text-center' },
    { key: 'start_date', label: t('shifts.start_date'), cellClass: 'text-center' },
    { key: 'time_schedule', label: t('shifts.time_schedule') },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[120px]' },
]);

function onSearch(value) {
    router.get(
        route('rotation-groups.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true, only: ['groups'] },
    );
}

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
</script>

<template>
    <AppLayout :title="t('shifts.rotation_groups_title')">
        <PageHeader
            :title="t('shifts.rotation_groups_title')"
            :description="t('shifts.rotation_groups_description')"
        />

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" dismissible class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" dismissible class="mb-4" />

        <DataTable
            :columns="columns"
            :data="groups"
            :filters="filters"
            :route-name="'rotation-groups.index'"
            :only="['groups']"
            storage-key="rotation-groups"
            @search="onSearch"
        >
            <template #cell-rotation_name="{ row }">
                <span class="font-medium text-mistral-ink">{{ row.rotation?.name ?? '—' }}</span>
            </template>

            <template #cell-group_index="{ row }">
                <span>{{ row.group_index ?? '—' }}</span>
            </template>

            <template #cell-start_date="{ row }">
                <span dir="ltr">{{ row.start_date ?? '—' }}</span>
            </template>

            <template #cell-time_schedule="{ row }">
                <span v-if="row.time_schedule">{{ row.time_schedule.name }}</span>
                <span v-else class="text-mistral-muted">—</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton
                        icon="fas fa-edit"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.edit')"
                        :href="route('rotation-groups.edit', row.id)"
                    />
                </div>
            </template>
        </DataTable>
    </AppLayout>
</template>
