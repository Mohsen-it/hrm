<script setup>
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    template: { type: Object, required: true },
});

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'medium' });
}

const fields = computed(() => [
    { label: t('fingerprint_devices.id'), value: String(props.template.id) },
    { label: t('fingerprint_devices.user'), value: props.template.user?.name ?? '—' },
    { label: t('fingerprint_devices.device_name'), value: props.template.device?.name ?? '—' },
    { label: t('fingerprint_devices.finger_id'), value: String(props.template.finger_id) },
    { label: t('fingerprint_devices.template_format'), value: props.template.template_format },
    { label: t('fingerprint_devices.template_version'), value: String(props.template.template_version) },
    { label: t('fingerprint_devices.template_quality'), value: String(props.template.quality) },
    { label: t('fingerprint_devices.is_master'), value: props.template.is_master ? t('common.yes') : t('common.no') },
    { label: t('fingerprint_devices.captured_at'), value: formatDateTime(props.template.captured_at) },
    { label: t('fingerprint_devices.synced_at'), value: formatDateTime(props.template.synced_at) },
    { label: t('fingerprint_devices.created_at'), value: formatDateTime(props.template.created_at) },
]);
</script>

<template>
    <AppLayout :title="t('fingerprint_devices.template_details')">
        <PageHeader
            :title="t('fingerprint_devices.template_details')"
            :description="`#${template.id}`"
        >
            <template #actions>
                <Button variant="secondary" :href="route('fingerprint-templates.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="card p-6">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-[var(--color-hairline)]">
                <div class="w-20 h-20 rounded-md bg-[var(--color-surface-2)] flex items-center justify-center border border-[var(--color-hairline)]">
                    <i class="fas fa-fingerprint text-[32px] text-[var(--color-ink-subtle)]"></i>
                </div>
                <div>
                    <h2 class="text-[20px] font-semibold text-[var(--color-ink)]">
                        {{ template.user?.name || '—' }}
                    </h2>
                    <p class="text-[13px] text-[var(--color-ink-muted)] mt-1">
                        {{ template.device?.name || '—' }}
                    </p>
                    <div class="mt-2 flex items-center gap-2">
                        <Badge
                            v-if="template.is_master"
                            :text="t('fingerprint_devices.is_master')"
                            variant="active"
                        />
                        <Badge
                            v-else
                            :text="t('fingerprint_devices.standard')"
                            variant="inactive"
                        />
                    </div>
                </div>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-right">
                    <dt class="text-[12px] font-semibold text-[var(--color-ink-subtle)] uppercase tracking-wider">
                        {{ field.label }}
                    </dt>
                    <dd class="text-[14px] text-[var(--color-ink)] mt-1 break-words">
                        {{ field.value }}
                    </dd>
                </div>
            </dl>

            <div class="mt-6 pt-6 border-t border-[var(--color-hairline)]">
                <p class="text-[12px] text-[var(--color-ink-muted)] mb-2">
                    {{ t('fingerprint_devices.template_data_label') }}
                </p>
                <div class="bg-[var(--color-surface-1)] border border-[var(--color-hairline)] rounded-md p-3 font-mono text-[11px] text-[var(--color-ink-muted)] break-all max-h-40 overflow-auto">
                    <template v-if="template.template_data">
                        {{ template.template_data.substring(0, 200) }}{{ template.template_data.length > 200 ? '...' : '' }}
                    </template>
                    <span v-else>—</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
