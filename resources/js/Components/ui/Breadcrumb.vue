<script setup>
const props = defineProps({
    items: { type: Array, required: true },
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
                    class="text-mistral-stone hover:text-mistral-primary transition-colors"
                >
                    {{ item.label }}
                </a>
                <span
                    v-else
                    :class="isLast(index) ? 'text-mistral-ink font-medium' : 'text-mistral-stone'"
                    :aria-current="isLast(index) ? 'page' : undefined"
                >
                    {{ item.label }}
                </span>
                <i
                    v-if="!isLast(index)"
                    class="fas fa-chevron-left text-[8px] text-mistral-muted mx-0.5 rtl-flip"
                    aria-hidden="true"
                ></i>
            </li>
        </ol>
    </nav>
</template>
