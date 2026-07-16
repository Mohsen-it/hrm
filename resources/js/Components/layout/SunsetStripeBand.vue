<script setup>
defineProps({
    dir: { type: String, default: 'rtl' },
});
</script>

<template>
    <div class="sunset-stripe-band" aria-hidden="true" :dir="dir"></div>
</template>
