<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, ConfirmDialog, Alert, Badge, DataTable, IconButton } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();
const page = usePage();

const props = defineProps({
    zone: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
});

const showDelete = ref(false);

const flashSuccess = computed(() => page.props.flash?.success);

const displayName = computed(() => (locale.value === 'en' && props.zone.name_en ? props.zone.name_en : props.zone.name_ar));

function performDelete() {
    router.delete(route('zones.destroy', props.zone.id), { preserveScroll: true });
}

function performDeleteBranch(branchId) {
    if (!confirm(t('zones.confirm_remove_branch'))) return;
    router.delete(route('zones.branches.detach', [props.zone.id, branchId]), { preserveScroll: true });
}

const branchColumns = computed(() => [
    { key: 'branch_code', label: t('branches.branch_code') },
    { key: 'branch_name', label: t('branches.branch_name') },
    { key: 'city', label: t('zones.city') },
    { key: 'pivot_priority', label: t('zones.priority') },
    { key: 'pivot_is_primary', label: t('zones.is_primary'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[80px]' },
]);
</script>

<template>
    <AppLayout :title="displayName">
        <PageHeader :title="displayName" :description="zone.code">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('zones.index')">{{ t('common.back') }}</Button>
                <Button variant="secondary" :href="route('zones.branches', zone.id)" icon="fas fa-code-branch">{{ t('zones.manage_branches') }}</Button>
                <Button variant="primary" icon="fas fa-edit" :href="route('zones.edit', zone.id)">{{ t('common.edit') }}</Button>
                <Button variant="danger" icon="fas fa-trash" @click="showDelete = true">{{ t('common.delete') }}</Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Card variant="base" padding="none" class="lg:col-span-2">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[14px] font-semibold mb-4 flex items-center gap-2">
                        <i class="fas fa-info-circle text-mistral-primary"></i>
                        {{ t('zones.zone_information') }}
                    </h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[13px]">
                        <div>
                            <dt class="text-mistral-steel">{{ t('zones.code') }}</dt>
                            <dd class="font-medium">{{ zone.code }}</dd>
                        </div>
                        <div>
                            <dt class="text-mistral-steel">{{ t('zones.name_ar') }}</dt>
                            <dd class="font-medium">{{ zone.name_ar }}</dd>
                        </div>
                        <div>
                            <dt class="text-mistral-steel">{{ t('zones.name_en') }}</dt>
                            <dd class="font-medium">{{ zone.name_en || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-mistral-steel">{{ t('zones.zone_type') }}</dt>
                            <dd>{{ t('zones.zone_type_' + zone.zone_type) }}</dd>
                        </div>
                        <div>
                            <dt class="text-mistral-steel">{{ t('zones.country') }}</dt>
                            <dd>{{ zone.country || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-mistral-steel">{{ t('zones.region') }}</dt>
                            <dd>{{ zone.region || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-mistral-steel">{{ t('zones.city') }}</dt>
                            <dd>{{ zone.city || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-mistral-steel">{{ t('common.status') }}</dt>
                            <dd>
                                <Badge v-if="zone.is_active" :text="t('common.active')" variant="active" />
                                <Badge v-else :text="t('common.inactive')" variant="inactive" />
                            </dd>
                        </div>
                    </dl>
                </div>
            </Card>

            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[14px] font-semibold mb-4 flex items-center gap-2">
                        <i class="fas fa-chart-bar text-mistral-primary"></i>
                        {{ t('zones.stats.total') }}
                    </h3>
                    <ul class="space-y-3 text-[13px]">
                        <li class="flex items-center justify-between">
                            <span class="text-mistral-steel">{{ t('zones.branches') }}</span>
                            <span class="font-semibold">{{ zone.branches_count || 0 }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="text-mistral-steel">{{ t('zones.employees_count') }}</span>
                            <span class="font-semibold">{{ zone.employees_count || 0 }}</span>
                        </li>
                        <li class="flex items-center justify-between">
                            <span class="text-mistral-steel">{{ t('zones.devices_count') }}</span>
                            <span class="font-semibold">{{ zone.devices_count || 0 }}</span>
                        </li>
                    </ul>
                </div>
            </Card>
        </div>

        <Card v-if="zone.description" variant="base" padding="none" class="mt-4">
            <div class="p-5 sm:p-6">
                <h3 class="text-[14px] font-semibold mb-3 flex items-center gap-2">
                    <i class="fas fa-align-left text-mistral-primary"></i>
                    {{ t('zones.description') }}
                </h3>
                <p class="text-[13px] leading-relaxed whitespace-pre-line">{{ zone.description }}</p>
            </div>
        </Card>

        <Card variant="base" padding="none" class="mt-4">
            <div class="p-5 sm:p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-[14px] font-semibold flex items-center gap-2">
                        <i class="fas fa-code-branch text-mistral-primary"></i>
                        {{ t('zones.assigned_branches') }}
                    </h3>
                    <Button variant="secondary" :href="route('zones.branches', zone.id)" icon="fas fa-cog">{{ t('zones.manage_branches') }}</Button>
                </div>

                <div v-if="branches.length === 0" class="text-center py-6 text-[13px] text-mistral-steel">
                    {{ t('zones.no_branches') }}
                </div>

                <DataTable
                    v-else
                    :columns="branchColumns"
                    :data="{ data: branches, links: [] }"
                    storage-key="zone-branches"
                    enable-search="false"
                    enable-filters="false"
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
                            <IconButton icon="fas fa-unlink" :aria-label="t('zones.remove_branch')" variant="danger" @click="performDeleteBranch(row.id)" />
                        </div>
                    </template>
                </DataTable>
            </div>
        </Card>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('zones.delete_confirm_title')"
            :message="t('zones.delete_confirm_message', { name: zone.name_ar })"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="performDelete"
        />
    </AppLayout>
</template>
