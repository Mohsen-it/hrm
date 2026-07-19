<script setup>
defineProps({
    icon: { type: String, default: 'fas fa-inbox' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center py-16 px-6" :dir="dir">
        <div class="w-16 h-16 rounded-2xl bg-mistral-surface flex items-center justify-center mb-4">
            <i :class="[icon, 'text-[28px] text-mistral-stone']" aria-hidden="true"></i>
        </div>
        <h3 v-if="title" class="text-[15px] text-mistral-ink font-semibold mb-1.5">
            {{ title }}
        </h3>
        <p v-if="description" class="text-[13px] text-mistral-stone max-w-sm leading-relaxed">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
