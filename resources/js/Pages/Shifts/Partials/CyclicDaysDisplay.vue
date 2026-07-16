<script setup>
import { computed } from 'vue'

const props = defineProps({
    workDays: { type: Number, required: true },
    restDays: { type: Number, required: true },
})

const circles = computed(() => {
    const arr = []
    for (let i = 0; i < props.workDays; i++) {
        arr.push({ color: 'bg-green-500', type: 'work' })
    }
    for (let i = 0; i < props.restDays; i++) {
        arr.push({ color: 'bg-gray-300', type: 'rest' })
    }
    return arr
})
</script>

<template>
    <div class="flex items-center gap-3 text-sm">
        <span class="text-gray-600 font-medium">أيام الدوام: {{ workDays }}</span>
        <div class="flex items-center gap-1">
            <span
                v-for="(c, i) in circles"
                :key="i"
                :class="c.color"
                class="w-5 h-5 rounded-full inline-block border border-white shadow-sm"
            ></span>
        </div>
        <span class="text-gray-400">|</span>
        <span class="text-gray-600 font-medium">أيام الراحة: {{ restDays }}</span>
        <div class="flex items-center gap-1">
            <span
                v-for="n in restDays"
                :key="'r' + n"
                class="w-5 h-5 rounded-full inline-block bg-gray-300 border border-white shadow-sm"
            ></span>
        </div>
    </div>
</template>
