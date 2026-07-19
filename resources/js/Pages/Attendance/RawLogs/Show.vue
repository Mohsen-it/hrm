<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    log: { type: Object, required: true },
});

const fields = computed(() => [
    { label: t('attendance.fields.user'), value: props.log.user ? `${props.log.user.name} (${props.log.user.employee_code || ''})` : '—' },
    { label: t('attendance.fields.device_id'), value: props.log.device_id || '—' },
    { label: t('attendance.fields.device_user_id'), value: props.log.device_user_id || '—' },
    { label: t('attendance.fields.punch_time'), value: props.log.punch_time || '—' },
    { label: t('attendance.fields.punch_type'), value: t(`attendance.punch_type.${props.log.punch_type}`, props.log.punch_type) },
    { label: t('attendance.fields.verify_type'), value: t(`attendance.verify_type.${props.log.verify_type}`, props.log.verify_type) },
    { label: t('attendance.fields.work_code'), value: props.log.work_code },
    { label: t('attendance.fields.source'), value: t(`attendance.source.${props.log.source}`, props.log.source) },
    { label: t('attendance.fields.ip_address'), value: props.log.ip_address || '—' },
    { label: t('attendance.fields.processed_at'), value: props.log.processed_at || '—' },
    { label: t('attendance.fields.created_at'), value: props.log.created_at || '—' },
]);
</script>

<template>
    <AppLayout :title="t('attendance.raw_log')">
        <PageHeader
            :title="t('attendance.raw_log') + ' #' + log.id"
            :description="t('attendance.show_description')"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('attendance.raw-logs.index')">
                    {{ t('attendance.actions.back') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Card variant="base" padding="none" class="lg:col-span-2">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                        {{ t('attendance.raw_log') }}
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div
                            v-for="f in fields"
                            :key="f.label"
                            class="flex items-start justify-between gap-2 py-2 border-b border-mistral-hairline-soft"
                        >
                            <span class="text-[12px] text-mistral-steel">{{ f.label }}</span>
                            <span class="text-[13px] font-semibold text-mistral-ink" dir="ltr">
                                {{ f.value }}
                            </span>
                        </div>
                    </div>
                </div>
            </Card>

            <Card variant="base" padding="none">
                <div class="p-5 sm:p-6">
                    <h3 class="text-[16px] font-semibold mb-3 text-mistral-ink">
                        {{ t('attendance.fields.processed') }}
                    </h3>
                    <div class="flex flex-col gap-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[12px] text-mistral-steel">
                                {{ t('attendance.fields.processed') }}
                            </span>
                            <Badge
                                :text="log.processed ? t('common.yes') : t('common.no')"
                                :variant="log.processed ? 'active' : 'pending'"
                            />
                        </div>
                        <div v-if="log.raw_data" class="text-[12px] text-mistral-steel mt-2">
                            <pre class="bg-mistral-surface p-2 rounded-lg text-[11px] overflow-x-auto" dir="ltr">{{ JSON.stringify(log.raw_data, null, 2) }}</pre>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
