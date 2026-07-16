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
import IconButton from '@/Components/ui/IconButton.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    rotations: { type: Object, default: () => ({ data: [], links: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters?.search || '');
const showDelete = ref(false);
const selectedRotation = ref(null);

function formatPattern(rotation) {
    if (!rotation.pattern || !Array.isArray(rotation.pattern)) return '—';
    const work = rotation.work_days_count || 0;
    const rest = rotation.rest_days_count || 0;
    return `${work}+${rest}`;
}

function formatCycleLength(rotation) {
    return rotation.cycle_length ? `${rotation.cycle_length} ${t('shifts.days')}` : '—';
}

const columns = computed(() => [
    { key: 'name', label: t('shifts.rotation_name'), sortable: true },
    { key: 'pattern', label: t('shifts.work_pattern'), cellClass: 'text-center' },
    { key: 'cycle_length', label: t('shifts.cycle_length'), cellClass: 'text-center' },
    { key: 'number_of_groups', label: t('shifts.groups_count'), cellClass: 'text-center' },
    { key: 'anchor_start_date', label: t('shifts.anchor_start_date') },
    { key: 'active_employees_count', label: t('shifts.employees_count'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[200px]' },
]);

function onSearch(value) {
    router.get(
        route('rotations.index'),
        { ...props.filters, search: value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function confirmDelete(rotation) {
    selectedRotation.value = rotation;
    showDelete.value = true;
}

function performDelete() {
    if (!selectedRotation.value) return;
    router.delete(route('rotations.destroy', selectedRotation.value.id), {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('shifts.rotations')">
        <PageHeader
            :title="t('shifts.rotations')"
            :description="t('shifts.rotations_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.assign')" icon="fas fa-user-check">
                    {{ t('shifts.rotation_assignments') }}
                </Button>
                <Button variant="primary" :href="route('rotations.create')" icon="fas fa-plus">
                    {{ t('shifts.add_rotation') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="sm" class="mb-6">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <SearchInput
                    v-model="search"
                    :placeholder="t('common.search')"
                    @search="onSearch"
                />
            </div>
        </Card>

        <DataTable :columns="columns" :data="rotations">
            <template #cell-name="{ row }">
                <div class="flex items-center gap-2">
                    <div
                        class="w-3 h-3 rounded-full shrink-0"
                        :style="{ backgroundColor: row.color || '#fa520f' }"
                    ></div>
                    <Link
                        :href="route('rotations.show', row.id)"
                        class="text-mistral-primary hover:underline font-medium"
                    >
                        {{ row.name }}
                    </Link>
                </div>
            </template>

            <template #cell-pattern="{ row }">
                <span class="text-[13px] font-mono">{{ formatPattern(row) }}</span>
            </template>

            <template #cell-cycle_length="{ row }">
                <span class="text-[13px]">{{ formatCycleLength(row) }}</span>
            </template>

            <template #cell-number_of_groups="{ row }">
                <Badge :text="row.number_of_groups" variant="info" />
            </template>

            <template #cell-anchor_start_date="{ row }">
                <span class="text-[13px]">{{ row.anchor_start_date || '—' }}</span>
            </template>

            <template #cell-active_employees_count="{ row }">
                <span class="text-[13px] font-medium">{{ row.active_employees_count || 0 }}</span>
            </template>

            <template #cell-actions="{ row }">
                <div class="flex items-center justify-center gap-1">
                    <IconButton
                        icon="fas fa-user-plus"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('shifts.assign_employee')"
                        :href="route('rotations.assign', { rotation: row.id })"
                    />
                    <IconButton
                        icon="fas fa-eye"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.view')"
                        :href="route('rotations.show', row.id)"
                    />
                    <IconButton
                        icon="fas fa-edit"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.edit')"
                        :href="route('rotations.edit', row.id)"
                    />
                    <IconButton
                        icon="fas fa-trash"
                        variant="ghost"
                        size="sm"
                        :aria-label="t('common.delete')"
                        @click="confirmDelete(row)"
                    />
                </div>
            </template>

            <template #footer>
                <Pagination :data="rotations" />
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('shifts.delete_rotation_confirm_title')"
            :message="t('shifts.delete_rotation_confirm_message', { name: selectedRotation?.name })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
