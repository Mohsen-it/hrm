<script setup>
import { computed } from 'vue';
import EmptyState from './EmptyState.vue';
import LoadingSpinner from './LoadingSpinner.vue';

const props = defineProps({
    columns: { type: Array, required: true },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    rowKey: { type: [String, Function], default: 'id' },
    stickyHeader: { type: Boolean, default: false },
    hover: { type: Boolean, default: true },
    emptyIcon: { type: String, default: 'fas fa-inbox' },
    emptyTitle: { type: String, default: '' },
    emptyDescription: { type: String, default: '' },
    tableClass: { type: String, default: 'text-[13px]' },
    dir: { type: String, default: 'rtl' },
    compact: { type: Boolean, default: false },
    clickable: { type: Boolean, default: false },
});

const emit = defineEmits(['row-click']);

const alignClass = (align) => {
    if (align === 'center') return 'text-center';
    if (align === 'end') return 'text-end';
    return 'text-start';
};

const rows = computed(() => props.items || []);

function keyOf(row, index) {
    if (typeof props.rowKey === 'function') return props.rowKey(row, index);
    return row?.[props.rowKey] ?? index;
}
</script>

<template>
    <div :dir="dir">
        <div v-if="loading" class="flex items-center justify-center py-12">
            <LoadingSpinner size="lg" />
        </div>
        <slot v-else-if="$slots.empty && rows.length === 0" name="empty" />
        <EmptyState
            v-else-if="rows.length === 0"
            :icon="emptyIcon"
            :title="emptyTitle"
            :description="emptyDescription"
        />
        <div v-else class="overflow-x-auto">
            <table
                class="w-full"
                :class="tableClass"
                role="grid"
                :aria-rowcount="String(rows.length + 1)"
            >
                <thead :class="stickyHeader ? 'sticky top-0 bg-white z-10' : ''">
                    <tr class="border-b border-mistral-hairline-soft">
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            scope="col"
                            class="px-3 font-semibold text-mistral-steel"
                            :class="[compact ? 'py-2' : 'py-3', alignClass(col.align), col.headerClass]"
                        >
                            <slot :name="`header-${col.key}`" :column="col">
                                {{ col.label }}
                            </slot>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(row, index) in rows"
                        :key="keyOf(row, index)"
                        class="border-b border-mistral-hairline-soft transition-colors"
                        :class="[hover ? 'hover:bg-mistral-canvas/50' : '', clickable ? 'cursor-pointer' : '']"
                        @click="clickable ? emit('row-click', row, index) : null"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            class="px-3"
                            :class="[compact ? 'py-2' : 'py-3', alignClass(col.align), col.cellClass]"
                        >
                            <slot :name="`cell-${col.key}`" :row="row" :index="index" :column="col">
                                {{ row?.[col.key] ?? '—' }}
                            </slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
