<script setup>
import { ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    title: { type: String, default: '' },
    collapsible: { type: Boolean, default: true },
    defaultOpen: { type: Boolean, default: true },
    dir: { type: String, default: 'rtl' },
});

const { t } = useTranslations();
const open = ref(true);

function toggle() {
    open.value = !open.value;
}
</script>

<template>
    <div
        class="p-4 mb-4 bg-mistral-cream-soft border border-mistral-primary/20 rounded-lg text-sm text-mistral-ink leading-relaxed"
        :dir="dir"
    >
        <button
            v-if="collapsible"
            type="button"
            class="flex items-center gap-2 font-semibold mb-2 text-start w-full"
            :aria-expanded="open ? 'true' : 'false'"
            @click="toggle"
        >
            <i class="fas fa-circle-question text-mistral-primary text-[13px]" aria-hidden="true"></i>
            <span class="flex-1">{{ title || t('common.what_is_this_section') }}</span>
            <i
                class="fas fa-chevron-down text-[10px] text-mistral-steel transition-transform"
                :class="{ 'rotate-180': !open }"
                aria-hidden="true"
            ></i>
        </button>
        <p v-else class="flex items-center gap-2 font-semibold mb-2">
            <i class="fas fa-circle-question text-mistral-primary text-[13px]" aria-hidden="true"></i>
            {{ title || t('common.what_is_this_section') }}
        </p>
        <div v-show="!collapsible || open">
            <slot />
        </div>
    </div>
</template>
