<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});
</script>

<template>
    <div class="flex items-start justify-between mb-6 flex-wrap gap-4" :dir="dir">
        <div class="text-start">
            <h1 class="text-[20px] sm:text-[24px] text-mistral-ink font-bold leading-tight tracking-tight">
                {{ title }}
            </h1>
            <p v-if="description" class="text-[13px] sm:text-[14px] text-mistral-steel mt-1.5 leading-relaxed">
                {{ description }}
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <slot name="actions" />
        </div>
    </div>
</template>
