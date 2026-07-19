<script setup>
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, Badge } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    position: { type: Object, required: true },
});

const fields = computed(() => [
    { label: t('positions.code'), value: props.position.position_code },
    { label: t('positions.name'), value: props.position.position_name },
    { label: t('positions.company'), value: props.position.company?.company_name || '—' },
    { label: t('positions.branch'), value: props.position.branch?.branch_name || '—' },
    { label: t('positions.department'), value: props.position.department?.department_name || t('positions.no_department') },
    { label: t('positions.min_salary'), value: props.position.min_salary ?? '—' },
    { label: t('positions.max_salary'), value: props.position.max_salary ?? '—' },
    { label: t('positions.description'), value: props.position.description || '—' },
    { label: t('positions.requirements'), value: props.position.requirements || '—' },
]);

const employeesCount = computed(() => {
    if (Array.isArray(props.position.users)) {
        return props.position.users.length;
    }
    return 0;
});
</script>

<template>
    <AppLayout :title="t('positions.view_position')">
        <PageHeader
            :title="t('positions.view_position')"
            :description="position.position_name"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('positions.index')">
                    {{ t('common.back') }}
                </Button>
                <Button variant="primary" icon="fas fa-edit" :href="route('positions.edit', position.id)">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">
                    <div class="w-16 h-16 rounded-md bg-mistral-surface flex items-center justify-center border border-mistral-hairline-soft">
                        <i class="fas fa-briefcase text-[24px] text-mistral-stone"></i>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-[20px] font-semibold text-mistral-ink">
                            {{ position.position_name }}
                        </h2>
                        <p class="text-[13px] text-mistral-steel mt-1">
                            {{ position.position_code }}
                        </p>
                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <Badge v-if="position.status === 1" :text="t('common.active')" variant="active" />
                            <Badge v-else :text="t('common.inactive')" variant="inactive" />
                            <Badge :text="`${t('positions.employees_count')}: ${employeesCount}`" variant="info" />
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col text-end">
                        <dt class="text-[12px] font-semibold text-mistral-stone uppercase tracking-wider">
                            {{ field.label }}
                        </dt>
                        <dd class="text-[14px] text-mistral-ink mt-1 break-words whitespace-pre-line">
                            {{ field.value }}
                        </dd>
                    </div>
                </dl>
            </div>
        </Card>
    </AppLayout>
</template>
