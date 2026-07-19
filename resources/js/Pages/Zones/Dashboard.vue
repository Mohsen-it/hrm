<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge, StatCard, DataTable, IconButton } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    zones: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
});

const displayName = (z) => locale.value === 'en' && z.name_en ? z.name_en : z.name_ar;

const statCards = computed(() => [
    { label: t('zones.stats.total'), value: props.stats.total ?? 0, icon: 'fas fa-globe', color: 'primary' },
    { label: t('zones.stats.active'), value: props.stats.active ?? 0, icon: 'fas fa-check-circle', color: 'success' },
    { label: t('zones.stats.inactive'), value: props.stats.inactive ?? 0, icon: 'fas fa-pause-circle', color: 'info' },
    { label: t('zones.stats.branches_total'), value: props.stats.branches_total ?? 0, icon: 'fas fa-code-branch', color: 'warning' },
    { label: t('zones.stats.employees_total'), value: props.stats.employees_total ?? 0, icon: 'fas fa-users', color: 'primary' },
    { label: t('zones.stats.devices_total'), value: props.stats.devices_total ?? 0, icon: 'fas fa-microchip', color: 'info' },
]);

const columns = computed(() => [
    { key: 'code', label: t('zones.code') },
    { key: 'name_ar', label: t('zones.name_ar') },
    { key: 'zone_type', label: t('zones.zone_type') },
    { key: 'city', label: t('zones.city') },
    { key: 'branches_count', label: t('zones.branches'), cellClass: 'text-center' },
    { key: 'employees_count', label: t('zones.employees_count'), cellClass: 'text-center' },
    { key: 'devices_count', label: t('zones.devices_count'), cellClass: 'text-center' },
    { key: 'actions', label: t('common.actions'), cellClass: 'text-center w-[80px]' },
]);
</script>

<template>
    <AppLayout :title="`${t('zones.title')} · Dashboard`">
        <PageHeader :title="`${t('zones.title')} · Dashboard`" :description="t('zones.dashboard_description')">
            <template #actions>
                <Button variant="secondary" icon="fas fa-list" :href="route('zones.index')">{{ t('zones.title') }}</Button>
                <Button variant="primary" icon="fas fa-plus" :href="route('zones.create')">
                    {{ t('zones.add_zone') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <StatCard
                v-for="c in statCards"
                :key="c.label"
                :label="c.label"
                :value="c.value"
                :icon="c.icon"
                :color="c.color"
            />
        </div>

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <h3 class="text-[14px] font-semibold mb-4 flex items-center gap-2">
                    <i class="fas fa-list text-mistral-primary"></i>
                    {{ t('zones.title') }}
                </h3>

                <div v-if="zones.length === 0" class="text-center py-8 text-[13px] text-mistral-steel">
                    {{ t('common.no_data') }}
                </div>

                <DataTable
                    v-else
                    :columns="columns"
                    :data="{ data: zones, links: [] }"
                    storage-key="zones-dashboard"
                    enable-search="false"
                    enable-filters="false"
                    enable-pagination="false"
                >
                    <template #cell-name_ar="{ row }">
                        <span class="font-medium">{{ displayName(row) }}</span>
                    </template>
                    <template #cell-zone_type="{ row }">
                        <Badge :text="t('zones.zone_type_' + row.zone_type)" variant="info" />
                    </template>
                    <template #cell-city="{ row }">
                        {{ row.city || '—' }}
                    </template>
                    <template #cell-actions="{ row }">
                        <div class="flex items-center justify-center">
                            <IconButton icon="fas fa-eye" :aria-label="t('common.view')" :href="route('zones.show', row.id)" />
                        </div>
                    </template>
                </DataTable>
            </div>
        </Card>
    </AppLayout>
</template>
