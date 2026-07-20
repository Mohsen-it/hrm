<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    subordination: { type: Object, required: true },
});

const fields = computed(() => [
    { label: t('subordinations.code'), value: props.subordination.code || '—' },
    { label: t('subordinations.name_ar'), value: props.subordination.name_ar || '—' },
    { label: t('subordinations.name_en'), value: props.subordination.name_en || '—' },
    { label: t('subordinations.sort_order'), value: String(props.subordination.sort_order ?? 0) },
    { label: t('subordinations.status'), value: props.subordination.status === 1 ? t('subordinations.active') : t('subordinations.inactive') },
    { label: t('subordinations.description'), value: props.subordination.description || '—' },
    { label: t('common.created_at'), value: props.subordination.created_at || '—' },
    { label: t('common.updated_at'), value: props.subordination.updated_at || '—' },
]);
</script>

<template>
    <AppLayout :title="t('subordinations.view_subordination')">
        <PageHeader
            :title="t('subordinations.view_subordination')"
            :description="subordination.name_ar"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('subordinations.index')">
                    {{ t('common.back') }}
                </Button>
                <Button variant="primary" icon="fas fa-pen" :href="route('subordinations.edit', subordination.id)">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-3 pb-4 border-b border-mistral-hairline-soft">
                    <div class="w-14 h-14 rounded-full bg-mistral-primary-soft flex items-center justify-center">
                        <i class="fas fa-map-location-dot text-[24px] text-mistral-primary"></i>
                    </div>
                    <div>
                        <h2 class="text-[20px] font-semibold text-mistral-ink">{{ subordination.name_ar }}</h2>
                        <p v-if="subordination.name_en" class="text-[13px] text-mistral-steel">{{ subordination.name_en }}</p>
                    </div>
                    <div class="ms-auto">
                        <Badge
                            v-if="subordination.status === 1"
                            :text="t('subordinations.active')"
                            variant="active"
                        />
                        <Badge v-else :text="t('subordinations.inactive')" variant="inactive" />
                    </div>
                </div>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 mt-4">
                    <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-end">
                        <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">
                            {{ field.label }}
                        </dt>
                        <dd class="text-[14px] text-mistral-ink mt-1 break-words">
                            {{ field.value }}
                        </dd>
                    </div>
                </dl>
            </div>
        </Card>
    </AppLayout>
</template>
