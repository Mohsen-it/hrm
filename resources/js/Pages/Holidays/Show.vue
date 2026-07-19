<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();

const props = defineProps({
    holiday: { type: Object, required: true },
});

const displayName = computed(() =>
    locale.value === 'en' && props.holiday.name_en ? props.holiday.name_en : props.holiday.name_ar,
);

const categoryLabel = computed(() => {
    const map = {
        public: t('holidays.category_public'),
        religious: t('holidays.category_religious'),
        national: t('holidays.category_national'),
        company: t('holidays.category_company'),
    };
    return map[props.holiday.category] || props.holiday.category;
});

const fields = computed(() => [
    { label: t('holidays.name_ar'), value: props.holiday.name_ar || '—' },
    { label: t('holidays.name_en'), value: props.holiday.name_en || '—' },
    { label: t('holidays.code'), value: props.holiday.code || '—' },
    { label: t('holidays.category'), value: categoryLabel.value },
    {
        label: t('holidays.date'),
        value: props.holiday.is_recurring
            ? `${props.holiday.recurring_day || '—'}/${props.holiday.recurring_month || '—'}`
            : (props.holiday.date || '—'),
    },
    { label: t('holidays.duration_days'), value: props.holiday.duration_days },
    { label: t('holidays.is_paid'), value: props.holiday.is_paid ? t('common.yes') : t('common.no') },
    { label: t('holidays.recurring'), value: props.holiday.is_recurring ? t('common.yes') : t('common.no') },
    { label: t('common.status'), value: props.holiday.is_active ? t('common.active') : t('common.inactive') },
    { label: t('holidays.description'), value: props.holiday.description || '—' },
]);
</script>

<template>
    <AppLayout :title="t('holidays.view_holiday')">
        <PageHeader :title="t('holidays.view_holiday')" :description="displayName">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('holidays.index')">
                    {{ t('common.back') }}
                </Button>
                <Button variant="primary" icon="fas fa-pen" :href="route('holidays.edit', holiday.id)">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">
                    <div class="w-16 h-16 rounded-md bg-mistral-surface flex items-center justify-center border border-mistral-hairline-soft">
                        <i class="fas fa-calendar-day text-[28px] text-mistral-stone"></i>
                    </div>
                    <div>
                        <h2 class="text-[18px] font-semibold text-mistral-ink">{{ displayName }}</h2>
                        <p class="text-[13px] text-mistral-steel mt-1">
                            <span v-if="holiday.is_recurring">
                                {{ holiday.recurring_day }}/{{ holiday.recurring_month }}
                            </span>
                            <span v-else>{{ holiday.date || '—' }}</span>
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <Badge :text="categoryLabel" variant="info" />
                            <Badge
                                :text="holiday.is_active ? t('common.active') : t('common.inactive')"
                                :variant="holiday.is_active ? 'active' : 'inactive'"
                            />
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-end">
                        <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">
                            {{ field.label }}
                        </dt>
                        <dd class="text-[14px] text-mistral-ink mt-1 break-words">{{ field.value }}</dd>
                    </div>
                </dl>
            </div>
        </Card>
    </AppLayout>
</template>
