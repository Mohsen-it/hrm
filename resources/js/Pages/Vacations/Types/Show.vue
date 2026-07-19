<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    type: { type: Object, required: true },
});

const fields = computed(() => [
    { label: t('vacations.type_code'), value: props.type.code || '—' },
    { label: t('vacations.type_name_ar'), value: props.type.name_ar || '—' },
    { label: t('vacations.type_name_en'), value: props.type.name_en || '—' },
    { label: t('vacations.days_per_year'), value: props.type.default_days_per_year },
    { label: t('vacations.max_days_per_request'), value: props.type.max_days_per_request || '—' },
    { label: t('vacations.advance_notice_days'), value: props.type.advance_notice_days || '—' },
    { label: t('vacations.color'), value: props.type.color || '—' },
    { label: t('vacations.is_paid'), value: props.type.is_paid ? t('common.yes') : t('common.no') },
    { label: t('vacations.requires_approval'), value: props.type.requires_approval ? t('common.yes') : t('common.no') },
    { label: t('common.status'), value: props.type.is_active ? t('common.active') : t('common.inactive') },
    { label: t('vacations.description'), value: props.type.description || '—' },
]);
</script>

<template>
    <AppLayout :title="t('vacations.view_type')">
        <PageHeader :title="t('vacations.view_type')" :description="type.name_ar">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('vacations.types.index')">{{ t('common.back') }}</Button>
                <Button variant="primary" icon="fas fa-pen" :href="route('vacations.types.edit', type.id)">{{ t('common.edit') }}</Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">
                    <div
                        class="w-16 h-16 rounded-xl flex items-center justify-center text-white text-[24px] font-bold"
                        :style="{ backgroundColor: type.color || '#2563eb' }"
                    >
                        <i class="fas fa-tag"></i>
                    </div>
                    <div>
                        <h2 class="text-[18px] font-semibold text-mistral-ink">{{ type.name_ar }}</h2>
                        <p class="text-[13px] text-mistral-steel mt-1">{{ type.name_en }}</p>
                        <div class="mt-2 flex items-center gap-2">
                            <Badge :text="type.code" variant="info" />
                            <Badge
                                :text="type.is_active ? t('common.active') : t('common.inactive')"
                                :variant="type.is_active ? 'active' : 'inactive'"
                            />
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col">
                        <dt class="text-[11px] font-semibold text-mistral-stone uppercase tracking-wider">
                            {{ field.label }}
                        </dt>
                        <dd class="text-[14px] text-mistral-ink mt-1 break-words">{{ field.value }}</dd>
                    </div>
                </dl>
            </div>
        </Card>
    </AppLayout>
</template>
