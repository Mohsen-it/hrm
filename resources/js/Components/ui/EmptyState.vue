<script setup>
defineProps({
    icon: { type: String, default: 'fas fa-inbox' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center py-12 px-4" :dir="dir">
        <i :class="[icon, 'text-[48px] text-mistral-muted mb-4']" aria-hidden="true"></i>
        <h3 v-if="title" class="text-[16px] text-mistral-ink font-semibold mb-2">
            {{ title }}
        </h3>
        <p v-if="description" class="text-[14px] text-mistral-steel max-w-sm">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-4">
            <slot />
        </div>
    </div>
</template>
