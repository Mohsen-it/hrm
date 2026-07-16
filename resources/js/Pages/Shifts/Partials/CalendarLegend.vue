<script setup>
defineProps({
    showWork: { type: Boolean, default: true },
    showRest: { type: Boolean, default: true },
    showAbsent: { type: Boolean, default: true },
    showHoliday: { type: Boolean, default: true },
})
</script>

<template>
    <div class="flex items-center gap-4 text-sm flex-wrap">
        <div v-if="showWork" class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-sm bg-green-500"></span>
            <span class="text-gray-700">دوام</span>
        </div>
        <div v-if="showRest" class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-sm bg-gray-300"></span>
            <span class="text-gray-700">راحة</span>
        </div>
        <div v-if="showAbsent" class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-sm bg-red-500"></span>
            <span class="text-gray-700">غياب</span>
        </div>
        <div v-if="showHoliday" class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-sm bg-yellow-400"></span>
            <span class="text-gray-700">عطلة</span>
        </div>
    </div>
</template>
