<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    value: { type: Number, required: true },
    duration: { type: Number, default: 800 },
    dir: { type: String, default: 'rtl' },
});

const displayValue = ref(0);
let animationFrame = null;

function animate(target) {
    const start = displayValue.value;
    const diff = target - start;
    if (diff === 0) return;

    const startTime = performance.now();

    function step(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / props.duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        displayValue.value = Math.round(start + diff * eased);

        if (progress < 1) {
            animationFrame = requestAnimationFrame(step);
        }
    }

    if (animationFrame) cancelAnimationFrame(animationFrame);
    animationFrame = requestAnimationFrame(step);
}

onMounted(() => {
    displayValue.value = props.value;
});

watch(
    () => props.value,
    (newVal) => animate(newVal),
);
</script>

<template>
    <span :dir="dir" class="tabular-nums">{{ displayValue.toLocaleString() }}</span>
</template>
