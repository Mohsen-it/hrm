<script setup>
defineProps({
    label: { type: String, default: '' },
    required: { type: Boolean, default: false },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    layout: { type: String, default: 'default' },
    dir: { type: String, default: 'rtl' },
});
</script>

<template>
    <div
        :class="[
            'w-full',
            layout === 'horizontal' ? 'grid grid-cols-1 md:grid-cols-3 gap-3 items-start' : 'space-y-1',
        ]"
        :dir="dir"
    >
        <div v-if="label || hint" :class="layout === 'horizontal' ? 'md:pt-3' : ''">
            <label v-if="label" class="block text-[13px] text-mistral-ink font-semibold">
                {{ label }}
                <span v-if="required" class="text-mistral-primary" aria-hidden="true">*</span>
            </label>
            <p v-if="hint" class="text-[12px] text-mistral-stone mt-1">
                {{ hint }}
            </p>
        </div>
        <div :class="layout === 'horizontal' ? 'md:col-span-2' : ''">
            <slot />
            <p v-if="error" class="text-[12px] text-mistral-danger mt-1" role="alert">
                {{ error }}
            </p>
        </div>
    </div>
</template>
