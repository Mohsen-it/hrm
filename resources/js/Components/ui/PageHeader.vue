<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});
</script>

<template>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3" :dir="dir">
        <div class="text-start">
            <h1 class="text-[22px] text-mistral-ink font-semibold leading-tight tracking-tight">
                {{ title }}
            </h1>
            <p v-if="description" class="text-[14px] text-mistral-steel mt-2 leading-snug">
                {{ description }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
