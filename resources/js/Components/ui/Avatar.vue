<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    src: { type: String, default: null },
    size: { type: String, default: 'md' },
    dir: { type: String, default: 'rtl' },
});

// If the image 404s (user without photo), fall back to initials instantly
// and stop retrying — no broken icons, no repeated requests.
const hasError = ref(false);
watch(() => props.src, () => { hasError.value = false; });

function onError() {
    hasError.value = true;
}

const showImage = computed(() => !!props.src && !hasError.value);

const sizeClass = computed(() => {
    return {
        xs: 'w-6 h-6 text-[9px]',
        sm: 'w-8 h-8 text-[11px]',
        md: 'w-9 h-9 text-[12px]',
        lg: 'w-11 h-11 text-[14px]',
        xl: 'w-14 h-14 text-[18px]',
    }[props.size] || 'w-9 h-9 text-[12px]';
});

const initials = computed(() => {
    if (!props.name) return '?';
    const parts = props.name.trim().split(/\s+/);
    if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
});
</script>

<template>
    <div
        :class="[
            'inline-flex items-center justify-center rounded-full bg-mistral-cream text-mistral-ink font-semibold overflow-hidden shrink-0 ring-2 ring-white',
            sizeClass,
        ]"
        :dir="dir"
    >
        <img
            v-if="showImage"
            :src="src"
            :alt="name"
            class="w-full h-full object-cover"
            loading="lazy"
            decoding="async"
            fetchpriority="low"
            draggable="false"
            @error="onError"
        />
        <span v-else>{{ initials }}</span>
    </div>
</template>
