<script setup>
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

defineProps({
    showWork: { type: Boolean, default: true },
    showRest: { type: Boolean, default: true },
    showAbsent: { type: Boolean, default: true },
    showHoliday: { type: Boolean, default: true },
})

const items = [
    { key: 'work', color: 'bg-mistral-success', labelKey: 'shifts.work', prop: 'showWork' },
    { key: 'rest', color: 'bg-mistral-surface', border: 'border border-mistral-hairline-soft', labelKey: 'shifts.rest', prop: 'showRest' },
    { key: 'absent', color: 'bg-mistral-danger', labelKey: 'shifts.absent', prop: 'showAbsent' },
    { key: 'holiday', color: 'bg-mistral-warning', labelKey: 'shifts.holiday', prop: 'showHoliday' },
]
</script>

<template>
    <div class="flex items-center gap-4 text-sm flex-wrap">
        <template v-for="item in items" :key="item.key">
            <div v-if="$props[item.prop]" class="flex items-center gap-1.5">
                <span :class="[item.color, item.border || '', 'w-3.5 h-3.5 rounded-sm inline-block']"></span>
                <span class="text-mistral-slate">{{ t(item.labelKey) }}</span>
            </div>
        </template>
    </div>
</template>
