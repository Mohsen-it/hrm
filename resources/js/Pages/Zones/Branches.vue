<script setup>
import { reactive, ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormSelect, FormInput, Badge, Alert, DataTable, IconButton } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();
const page = usePage();

const props = defineProps({
    zone: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
});

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const displayName = computed(() => (locale.value === 'en' && props.zone.name_en ? props.zone.name_en : props.zone.name_ar));

const showAddForm = ref(false);
const adding = reactive({
    branch_id: '',
    is_primary: false,
    priority: 0,
    notes: '',
});

const yesNoOptions = [
    { value: true, label: t('common.yes') },
    { value: false, label: t('common.no') },
];

function submitAttach() {
    if (!adding.branch_id) return;
    router.post(route('zones.branches.attach', props.zone.id), adding, {
        preserveScroll: true,
        onSuccess: () => {
            showAddForm.value = false;
            adding.branch_id = '';
            adding.is_primary = false;
            adding.priority = 0;
            adding.notes = '';
        },
    });
}

function detachBranch(branchId) {
    if (!confirm(t('zones.confirm_remove_branch'))) return;
    router.delete(route('zones.branches.detach', [props.zone.id, branchId]), { preserveScroll: true });
}

const columns = computed(() => [
    { key: 'branch_code', label: t('branches.branch_code') },
    { key: 'branch_name', label: t('branches.branch_name') },
    { key: 'city', label: t('zones.city') },
    { key: 'pivot_priority', label: t('zones.priority') },
    { key: 'pivot_is_primary', label: t('zones.is_primary'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[80px]' },
]);
</script>

<template>
    <AppLayout :title="t('zones.manage_branches')">
        <PageHeader :title="`${t('zones.manage_branches')} · ${displayName}`" :description="zone.code">
            <template #actions>
                <Button variant="secondary" :href="route('zones.show', zone.id)">{{ t('zones.back_to_zone') }}</Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
        <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[14px] font-semibold flex items-center gap-2">
                        <i class="fas fa-code-branch text-mistral-primary"></i>
                        {{ t('zones.assigned_branches') }} ({{ branches.length }})
                    </h3>
                    <Button variant="primary" :icon="showAddForm ? 'fas fa-times' : 'fas fa-plus'" @click="showAddForm = !showAddForm">
                        {{ showAddForm ? t('common.cancel') : t('zones.add_branch') }}
                    </Button>
                </div>

                <form v-if="showAddForm" class="border-t border-mistral-hairline-soft pt-4 grid grid-cols-1 md:grid-cols-2 gap-3" @submit.prevent="submitAttach">
                    <FormInput v-model="adding.branch_id" :label="t('zones.select_branch')" name="branch_id" type="number" required />
                    <FormInput v-model="adding.priority" :label="t('zones.priority')" name="priority" type="number" min="0" />
                    <FormSelect v-model="adding.is_primary" :label="t('zones.is_primary')" name="is_primary" :options="yesNoOptions" />
                    <FormInput v-model="adding.notes" :label="t('zones.pivot_notes')" name="notes" />
                    <div class="md:col-span-2 flex justify-end gap-2 mt-2">
                        <Button type="submit" variant="primary" icon="fas fa-save">{{ t('zones.add_branch') }}</Button>
                    </div>
                </form>

                <div v-if="branches.length === 0" class="text-center py-6 text-[13px] text-mistral-steel">
                    {{ t('zones.no_branches') }}
                </div>

                <DataTable
                    v-else
                    :columns="columns"
                    :data="{ data: branches, links: [] }"
                    storage-key="zone-branches-manage"
                    enable-search="false"
                    enable-filters="false"
                    enable-pagination="false"
                    class="mt-3"
                >
                    <template #cell-branch_code="{ row }">
                        <span class="font-mono text-[12px]">{{ row.branch_code }}</span>
                    </template>
                    <template #cell-branch_name="{ row }">
                        <span class="font-medium">{{ row.branch_name }}</span>
                    </template>
                    <template #cell-city="{ row }">
                        {{ row.city || '—' }}
                    </template>
                    <template #cell-pivot_is_primary="{ row }">
                        <Badge v-if="row.pivot_is_primary" :text="t('zones.primary_branch')" variant="info" />
                        <span v-else class="text-mistral-stone">—</span>
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-center">
                            <IconButton icon="fas fa-unlink" :aria-label="t('zones.remove_branch')" variant="danger" @click="detachBranch(row.id)" />
                        </div>
                    </template>
                </DataTable>
            </div>
        </Card>
    </AppLayout>
</template>
