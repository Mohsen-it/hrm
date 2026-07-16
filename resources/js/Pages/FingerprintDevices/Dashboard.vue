<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    stats: { type: Object, required: true },
    onlineDevices: { type: Array, default: () => [] },
    offlineDevices: { type: Array, default: () => [] },
});

const statusVariant = (status) => {
    const map = { online: 'active', offline: 'inactive', maintenance: 'pending', deactivated: 'inactive' };
    return map[status] || 'inactive';
};

const statCards = computed(() => [
    {
        label: t('fingerprint_devices.total_devices'),
        value: props.stats.total,
        icon: 'fas fa-fingerprint',
        color: 'var(--color-primary)',
    },
    {
        label: t('fingerprint_devices.online'),
        value: props.stats.online,
        icon: 'fas fa-wifi',
        color: 'var(--color-success)',
    },
    {
        label: t('fingerprint_devices.offline'),
        value: props.stats.offline,
        icon: 'fas fa-power-off',
        color: 'var(--color-danger)',
    },
    {
        label: t('fingerprint_devices.maintenance'),
        value: props.stats.maintenance,
        icon: 'fas fa-wrench',
        color: 'var(--color-warning)',
    },
]);
</script>

<template>
    <AppLayout :title="t('fingerprint_devices.dashboard')">
        <PageHeader
            :title="t('fingerprint_devices.dashboard')"
            :description="t('fingerprint_devices.dashboard_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-devices.index')">{{ t('common.back') }}</Button>
                <Button variant="primary" icon="fas fa-plus" :href="route('fingerprint-devices.create')">
                    {{ t('fingerprint_devices.add_device') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div
                v-for="(card, idx) in statCards"
                :key="idx"
                class="card p-4 flex items-center gap-4"
            >
                <div
                    class="w-12 h-12 rounded-md flex items-center justify-center"
                    :style="{ backgroundColor: card.color + '15', color: card.color }"
                >
                    <i :class="card.icon" class="text-[20px]"></i>
                </div>
                <div>
                    <p class="text-[24px] font-bold text-[var(--color-ink)]">{{ card.value }}</p>
                    <p class="text-[12px] text-[var(--color-ink-muted)]">{{ card.label }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card p-4">
                <h3 class="text-[16px] font-semibold text-[var(--color-ink)] mb-4 flex items-center gap-2">
                    <i class="fas fa-wifi text-[var(--color-success)]"></i>
                    {{ t('fingerprint_devices.online_devices') }}
                </h3>
                <div v-if="onlineDevices.length === 0" class="text-center py-8 text-[var(--color-ink-subtle)]">
                    <i class="fas fa-power-off text-[32px] mb-2"></i>
                    <p>{{ t('fingerprint_devices.no_online_devices') }}</p>
                </div>
                <div v-else class="space-y-2">
                    <Link
                        v-for="device in onlineDevices"
                        :key="device.id"
                        :href="route('fingerprint-devices.show', device.id)"
                        class="flex items-center justify-between p-3 rounded-md bg-[var(--color-surface-1)] border border-[var(--color-hairline)] hover:bg-[var(--color-surface-2)] transition-colors"
                    >
                        <div>
                            <p class="text-[14px] font-medium text-[var(--color-ink)]">{{ device.name }}</p>
                            <p class="text-[12px] text-[var(--color-ink-muted)]">{{ device.ip_address }}:{{ device.port }}</p>
                        </div>
                        <Badge :text="t('fingerprint_devices.online')" variant="active" />
                    </Link>
                </div>
            </div>

            <div class="card p-4">
                <h3 class="text-[16px] font-semibold text-[var(--color-ink)] mb-4 flex items-center gap-2">
                    <i class="fas fa-power-off text-[var(--color-danger)]"></i>
                    {{ t('fingerprint_devices.offline_devices') }}
                </h3>
                <div v-if="offlineDevices.length === 0" class="text-center py-8 text-[var(--color-ink-subtle)]">
                    <i class="fas fa-check-circle text-[32px] mb-2"></i>
                    <p>{{ t('fingerprint_devices.no_offline_devices') }}</p>
                </div>
                <div v-else class="space-y-2">
                    <Link
                        v-for="device in offlineDevices"
                        :key="device.id"
                        :href="route('fingerprint-devices.show', device.id)"
                        class="flex items-center justify-between p-3 rounded-md bg-[var(--color-surface-1)] border border-[var(--color-hairline)] hover:bg-[var(--color-surface-2)] transition-colors"
                    >
                        <div>
                            <p class="text-[14px] font-medium text-[var(--color-ink)]">{{ device.name }}</p>
                            <p class="text-[12px] text-[var(--color-ink-muted)]">{{ device.ip_address }}:{{ device.port }}</p>
                        </div>
                        <Badge :text="t('fingerprint_devices.offline')" variant="inactive" />
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
