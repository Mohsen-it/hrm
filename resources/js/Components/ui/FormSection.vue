<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: '' },
    count: { type: Number, default: null },
    collapsible: { type: Boolean, default: true },
    defaultOpen: { type: Boolean, default: true },
    dir: { type: String, default: 'rtl' },
});

const isOpen = ref(props.defaultOpen);

const countLabel = computed(() => {
    if (props.count === null) return '';
    return `${props.count}`;
});

function toggle() {
    if (props.collapsible) {
        isOpen.value = !isOpen.value;
    }
}

function onKeydown(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        toggle();
    }
}
</script>

<template>
    <div
        :class="[
            'bg-white border border-mistral-hairline-soft rounded-lg overflow-hidden transition-shadow duration-200',
            isOpen ? 'shadow-level-1' : 'shadow-none hover:shadow-level-1',
        ]"
        :dir="dir"
    >
        <button
            type="button"
            :class="[
                'w-full flex items-center gap-3 px-5 sm:px-6 py-4 text-start transition-colors duration-150',
                collapsible ? 'cursor-pointer hover:bg-mistral-surface/50' : 'cursor-default',
            ]"
            :aria-expanded="isOpen"
            :aria-controls="`section-${title?.replace(/\s+/g, '-').toLowerCase()}`"
            @click="toggle"
            @keydown="onKeydown"
        >
            <div
                v-if="icon"
                class="w-8 h-8 rounded-lg bg-mistral-primary/10 flex items-center justify-center shrink-0"
            >
                <i :class="[icon, 'text-mistral-primary text-[14px]']" aria-hidden="true"></i>
            </div>

            <div class="flex-1 min-w-0">
                <h3 class="text-[15px] font-semibold text-mistral-ink leading-tight">
                    {{ title }}
                </h3>
                <p v-if="description" class="text-[12px] text-mistral-stone mt-0.5">
                    {{ description }}
                </p>
            </div>

            <span
                v-if="count !== null"
                class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 text-[11px] font-medium text-mistral-steel bg-mistral-surface rounded-full"
            >
                {{ countLabel }}
            </span>

            <button
                v-if="collapsible"
                type="button"
                :class="[
                    'w-7 h-7 flex items-center justify-center rounded-lg text-mistral-stone hover:text-mistral-ink hover:bg-mistral-surface transition-all duration-200 shrink-0',
                ]"
                tabindex="-1"
                @click.stop="toggle"
            >
                <i
                    :class="[
                        'fas fa-chevron-down text-[11px] transition-transform duration-200',
                        isOpen ? '' : '-rotate-90',
                    ]"
                    aria-hidden="true"
                ></i>
            </button>
        </button>

        <div
            :id="`section-${title?.replace(/\s+/g, '-').toLowerCase()}`"
            role="region"
            :class="[
                'overflow-hidden transition-all duration-300 ease-in-out',
                isOpen ? 'max-h-[2000px] opacity-100' : 'max-h-0 opacity-0',
            ]"
        >
            <div class="px-5 sm:px-6 pb-5 sm:pb-6 pt-0">
                <slot />
            </div>
        </div>
    </div>
</template>
