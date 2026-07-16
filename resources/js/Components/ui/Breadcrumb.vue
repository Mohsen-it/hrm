<script setup>
import { computed } from 'vue';

const props = defineProps({
    items: { type: Array, required: true },
    separator: { type: String, default: '/' },
    dir: { type: String, default: 'rtl' },
});

const isLast = (index) => index === props.items.length - 1;
</script>

<template>
    <nav :aria-label="dir === 'rtl' ? 'مسار التنقل' : 'Breadcrumb'" :dir="dir">
        <ol class="flex items-center flex-wrap gap-1 text-[13px]">
            <li v-for="(item, index) in items" :key="index" class="flex items-center gap-1">
                <a
                    v-if="item.href && !isLast(index)"
                    :href="item.href"
                    class="text-mistral-steel hover:text-mistral-primary transition-colors"
                >
                    {{ item.label }}
                </a>
                <span
                    v-else
                  :class="isLast(index) ? 'text-mistral-ink font-semibold' : 'text-mistral-steel'"
                  :aria-current="isLast(index) ? 'page' : undefined"
                >
                    {{ item.label }}
                </span>
                <span
                    v-if="!isLast(index)"
                    :class="['text-mistral-stone mx-1', dir === 'rtl' ? 'rtl-flip' : '']"
                    aria-hidden="true"
                >
                    {{ dir === 'rtl' ? '\\' : '/' }}
                </span>
            </li>
        </ol>
    </nav>
</template>
