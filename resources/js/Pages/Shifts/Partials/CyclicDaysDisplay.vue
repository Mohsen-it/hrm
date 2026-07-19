<script setup>
import { computed } from 'vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    workDays: { type: Number, required: true },
    restDays: { type: Number, required: true },
})

const circles = computed(() => {
    const arr = []
    for (let i = 0; i < props.workDays; i++) {
        arr.push({ color: 'bg-mistral-success', type: 'work' })
    }
    for (let i = 0; i < props.restDays; i++) {
        arr.push({ color: 'bg-mistral-surface', border: 'border border-mistral-hairline-soft', type: 'rest' })
    }
    return arr
})
</script>

<template>
    <div class="flex items-center gap-3 text-sm">
        <span class="text-mistral-slate font-medium">{{ t('shifts.work_days') }}: {{ workDays }}</span>
        <div class="flex items-center gap-1">
            <span
                v-for="(c, i) in circles"
                :key="i"
                :class="[c.color, c.border || '', 'w-5 h-5 rounded-full inline-block shadow-sm']"
            ></span>
        </div>
        <span class="text-mistral-hairline-soft">|</span>
        <span class="text-mistral-slate font-medium">{{ t('shifts.rest_days') }}: {{ restDays }}</span>
        <div class="flex items-center gap-1">
            <span
                v-for="n in restDays"
                :key="'r' + n"
                class="w-5 h-5 rounded-full inline-block bg-mistral-surface border border-mistral-hairline-soft shadow-sm"
            ></span>
        </div>
    </div>
</template>
