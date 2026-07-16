<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    zones: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    companies: { type: Array, default: () => [] },
});

const displayName = (z) => locale.value === 'en' && z.name_en ? z.name_en : z.name_ar;

function statCard(label, value, icon, color) {
    return { label, value, icon, color };
}

const cards = computed(() => [
    statCard(t('zones.stats.total'), props.stats.total ?? 0, 'fas fa-globe', 'bg-[var(--color-primary)]'),
    statCard(t('zones.stats.active'), props.stats.active ?? 0, 'fas fa-check-circle', 'bg-green-500'),
    statCard(t('zones.stats.inactive'), props.stats.inactive ?? 0, 'fas fa-pause-circle', 'bg-gray-500'),
    statCard(t('zones.stats.branches_total'), props.stats.branches_total ?? 0, 'fas fa-code-branch', 'bg-amber-500'),
    statCard(t('zones.stats.employees_total'), props.stats.employees_total ?? 0, 'fas fa-users', 'bg-blue-500'),
    statCard(t('zones.stats.devices_total'), props.stats.devices_total ?? 0, 'fas fa-microchip', 'bg-purple-500'),
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
            <div v-for="c in cards" :key="c.label" class="card p-4 flex items-center gap-3">
                <div :class="c.color" class="w-10 h-10 rounded-md flex items-center justify-center text-white shrink-0">
                    <i :class="c.icon"></i>
                </div>
                <div>
                    <p class="text-[11px] text-[var(--color-ink-muted)]">{{ c.label }}</p>
                    <p class="text-[20px] font-semibold">{{ c.value }}</p>
                </div>
            </div>
        </div>

        <div class="card p-6">
            <h3 class="text-[14px] font-semibold mb-4 flex items-center gap-2">
                <i class="fas fa-list text-[var(--color-primary)]"></i>
                {{ t('zones.title') }}
            </h3>
            <div v-if="zones.length === 0" class="text-center py-8 text-[13px] text-[var(--color-ink-muted)]">
                {{ t('common.no_data') }}
            </div>
            <table v-else class="w-full text-right" dir="rtl">
                <thead class="text-[12px] text-[var(--color-ink-muted)] border-b border-[var(--color-hairline)]">
                    <tr>
                        <th class="py-2">{{ t('zones.code') }}</th>
                        <th class="py-2">{{ t('zones.name_ar') }}</th>
                        <th class="py-2">{{ t('zones.zone_type') }}</th>
                        <th class="py-2">{{ t('zones.city') }}</th>
                        <th class="py-2 text-center">{{ t('zones.branches') }}</th>
                        <th class="py-2 text-center">{{ t('zones.employees_count') }}</th>
                        <th class="py-2 text-center">{{ t('zones.devices_count') }}</th>
                        <th class="py-2 text-center">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="z in zones" :key="z.id" class="border-b border-[var(--color-hairline)] hover:bg-[var(--color-surface-1)]">
                        <td class="py-2 font-mono text-[12px]">{{ z.code }}</td>
                        <td class="py-2 font-medium">{{ displayName(z) }}</td>
                        <td class="py-2">
                            <Badge :text="t('zones.zone_type_' + z.zone_type)" variant="info" />
                        </td>
                        <td class="py-2">{{ z.city || '—' }}</td>
                        <td class="py-2 text-center">{{ z.branches_count || 0 }}</td>
                        <td class="py-2 text-center">{{ z.employees_count || 0 }}</td>
                        <td class="py-2 text-center">{{ z.devices_count || 0 }}</td>
                        <td class="py-2 text-center">
                            <Link :href="route('zones.show', z.id)" class="btn-icon text-[var(--color-primary)]" :title="t('common.view')">
                                <i class="fas fa-eye"></i>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
