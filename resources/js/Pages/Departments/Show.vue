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
    department: { type: Object, required: true },
});

const fields = computed(() => [
    { label: t('departments.code'), value: props.department.department_code },
    { label: t('departments.name'), value: props.department.department_name },
    { label: t('departments.company'), value: props.department.company?.company_name || '—' },
    { label: t('departments.branch'), value: props.department.branch?.branch_name || '—' },
    { label: t('departments.parent'), value: props.department.parent?.department_name || t('departments.no_parent') },
    { label: t('departments.manager'), value: props.department.manager?.name || t('departments.no_manager') },
    { label: t('departments.email'), value: props.department.email || '—' },
    { label: t('departments.phone'), value: props.department.phone || '—' },
    { label: t('departments.location'), value: props.department.location || '—' },
    { label: t('departments.description'), value: props.department.description || '—' },
]);

const childrenCount = computed(() => {
    if (Array.isArray(props.department.children)) {
        return props.department.children.length;
    }
    return 0;
});

const employeesCount = computed(() => {
    if (Array.isArray(props.department.users)) {
        return props.department.users.length;
    }
    return 0;
});
</script>

<template>
    <AppLayout :title="t('departments.view_department')">
        <PageHeader
            :title="t('departments.view_department')"
            :description="department.department_name"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('departments.index')">
                    {{ t('common.back') }}
                </Button>
                <Button variant="primary" icon="fas fa-pen" :href="route('departments.edit', department.id)">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline-soft">
                    <div
                        class="w-16 h-16 rounded-xl bg-mistral-surface flex items-center justify-center border border-mistral-hairline-soft"
                    >
                        <i class="fas fa-sitemap text-[24px] text-mistral-stone"></i>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-[18px] font-bold text-mistral-ink">
                            {{ department.department_name }}
                        </h2>
                        <p class="text-[13px] text-mistral-steel mt-0.5">
                            {{ department.department_code }}
                        </p>
                        <div class="mt-2 flex items-center gap-2 flex-wrap">
                            <Badge v-if="department.status === 1" :text="t('common.active')" variant="active" />
                            <Badge v-else :text="t('common.inactive')" variant="inactive" />
                            <Badge :text="`${t('departments.children_count')}: ${childrenCount}`" variant="info" />
                            <Badge :text="`${t('departments.employees_count')}: ${employeesCount}`" variant="info" />
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                    <div v-for="(field, idx) in fields" :key="idx" class="flex flex-col">
                        <dt class="text-[11px] font-semibold text-mistral-stone uppercase tracking-wider">
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
