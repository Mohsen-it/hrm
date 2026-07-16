<script setup>
import { computed, ref } from 'vue';
import Card from './Card.vue';
import Button from './Button.vue';
import LoadingSpinner from './LoadingSpinner.vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    data: { type: Object, required: true },
    loading: { type: Boolean, default: false },
    emptyTitle: { type: String, default: 'لا توجد بيانات' },
    emptyDescription: { type: String, default: '' },
    rowClickable: { type: Boolean, default: false },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['row-click']);

const items = computed(() => props.data?.data || []);
const meta = computed(() => ({
    current_page: props.data?.current_page || 1,
    last_page: props.data?.last_page || 1,
    per_page: props.data?.per_page || 20,
    total: props.data?.total || 0,
    from: props.data?.from || 0,
    to: props.data?.to || 0,
}));

function cellValue(row, col) {
    if (typeof col.accessor === 'function') return col.accessor(row);
    if (typeof col.accessor === 'string') return row[col.accessor];
    return row[col.key];
}

function onRowClick(row) {
    if (props.rowClickable) emit('row-click', row);
}
</script>

<template>
    <Card variant="base" padding="none" :dir="dir">
        <div class="relative overflow-x-auto">
            <table class="w-full border-collapse text-start">
                <thead>
                    <tr class="bg-mistral-surface border-b border-mistral-hairline-soft">
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            :class="['px-4 py-3 text-[12px] font-semibold text-mistral-steel uppercase tracking-wide', col.headerClass]"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td :colspan="columns.length" class="text-center py-12">
                            <LoadingSpinner size="md" />
                        </td>
                    </tr>
                    <tr
                        v-else-if="items.length === 0"
                    >
                        <td :colspan="columns.length" class="p-0">
                            <EmptyState :title="emptyTitle" :description="emptyDescription" />
                        </td>
                    </tr>
                    <tr
                        v-for="(row, rowIndex) in items"
                        v-else
                        :key="row.id || rowIndex"
                        :class="[
                            'border-b border-mistral-hairline-soft last:border-0 transition-colors',
                            rowIndex % 2 === 1 ? 'bg-mistral-surface' : 'bg-mistral-canvas',
                            'hover:bg-mistral-surface-cream-soft',
                            rowClickable ? 'cursor-pointer' : '',
                        ]"
                        @click="onRowClick(row)"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="['px-4 py-3 text-[14px] text-mistral-ink', col.cellClass]"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :value="cellValue(row, col)">
                                {{ cellValue(row, col) }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-if="meta.total > 0" class="flex items-center justify-between px-4 py-3 border-t border-mistral-hairline-soft text-[12px] text-mistral-steel">
            <span>
                عرض {{ meta.from }} - {{ meta.to }} من {{ meta.total }}
            </span>
            <slot name="footer" :meta="meta" />
        </div>
    </Card>
</template>
