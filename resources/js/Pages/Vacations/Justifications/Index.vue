<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Alert, Button, ConfirmDialog, DataTable, IconButton, PageHeader } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();
const showDelete = ref(false);
const selected = ref(null);

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [] }) },
    filters: { type: Object, default: () => ({}) },
});

const columns = computed(() => [
    { key: 'user.name', label: t('vacations.employee') },
    { key: 'attendance_date', label: 'التاريخ', sortable: true },
    { key: 'arrival_time', label: 'وقت الوصول' },
    { key: 'justification_type', label: 'نوع التبرير', cellClass: 'text-center' },
    { key: 'reason', label: 'السبب' },
    { key: 'actions', label: t('common.actions'), cellClass: 'w-[100px] text-center' },
]);

function justificationTypes(row) {
    return [
        row.missing_check_in ? 'بصمة دخول' : null,
        row.missing_check_out ? 'بصمة خروج' : null,
        row.late_arrival ? 'تأخر عن الدوام' : null,
    ].filter(Boolean);
}

function search(value) {
    router.get(route('vacations.justifications.index'), { ...props.filters, search: value }, {
        preserveState: true,
        replace: true,
        only: ['requests'],
    });
}

function exportExcel() {
    window.location.href = route('vacations.justifications.export', props.filters);
}

function confirmDelete(row) {
    selected.value = row;
    showDelete.value = true;
}

function destroy() {
    if (!selected.value) return;

    router.delete(route('vacations.justifications.destroy', selected.value.id), {
        preserveScroll: true,
        onFinish: () => {
            showDelete.value = false;
            selected.value = null;
        },
    });
}


usePageTitle('التبريرات');
</script>

<template>
    
        <PageHeader title="طلبات الإجازات" description="سجل التبريرات المرتبطة ببصمات الموظفين والدوريات">
            <template #actions>
                <Button variant="secondary" :href="route('vacations.requests.index')">طلبات الإجازات</Button>
                <Button variant="primary" icon="fas fa-plus" :href="route('vacations.justifications.create')">تسجيل تبرير</Button>
            </template>
        </PageHeader>

        <div class="mb-6 flex gap-5 border-b border-mistral-hairline">
            <a :href="route('vacations.requests.index')" class="pb-3 text-sm text-mistral-steel">الإجازات</a>
            <span class="border-b-2 border-mistral-primary pb-3 text-sm font-medium text-mistral-primary">التبرير</span>
        </div>

        <Alert v-if="page.props.flash?.success" type="success" :message="page.props.flash.success" class="mb-4" />

        <DataTable
            :columns="columns"
            :data="requests"
            :filters="filters"
            route-name="vacations.justifications.index"
            storage-key="attendance-justifications"
            @search="search"
            @export="exportExcel"
        >
            <template #cell-arrival_time="{ value }">
                <span class="font-mono text-mistral-steel">{{ value ? value.slice(0, 5) : '—' }}</span>
            </template>
            <template #cell-justification_type="{ row }">
                <div class="flex flex-wrap justify-center gap-1">
                    <span
                        v-for="type in justificationTypes(row)"
                        :key="type"
                        class="inline-flex rounded-full bg-mistral-primary/10 px-2.5 py-1 text-xs font-semibold text-mistral-primary"
                    >
                        {{ type }}
                    </span>
                    <span v-if="!justificationTypes(row).length" class="text-xs text-mistral-stone">—</span>
                </div>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-center gap-1">
                    <IconButton icon="fas fa-pen" :aria-label="t('common.edit')" :href="route('vacations.justifications.edit', row.id)" />
                    <IconButton icon="fas fa-trash" variant="danger" :aria-label="t('common.delete')" @click="confirmDelete(row)" />
                </div>
            </template>
        </DataTable>

        <ConfirmDialog
            v-model="showDelete"
            :title="t('common.confirm_delete')"
            message="هل تريد حذف هذا التبرير؟"
            :confirm-text="t('common.delete')"
            :cancel-text="t('common.cancel')"
            confirm-variant="danger"
            @confirm="destroy"
        />
    </template>
