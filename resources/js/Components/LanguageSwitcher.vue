<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { locale, t } = useTranslations();

const props = defineProps({
    dir: { type: String, default: 'rtl' },
});

const open = ref(false);

const languages = [
    { code: 'ar', label: 'العربية', flag: '🇸🇦' },
    { code: 'en', label: 'English', flag: '🇬🇧' },
];

const current = computed(() => languages.find((l) => l.code === locale.value) || languages[0]);

function switchTo(code) {
    open.value = false;
    if (code === locale.value) return;

    router.visit('/language/' + code, {
        preserveScroll: true,
        preserveState: true,
    });
}
</script>

<template>
    <div class="relative" :dir="dir">
        <button
            class="btn-ghost btn flex items-center gap-2 text-[14px]"
            @click="open = !open"
            type="button"
        >
            <span class="text-[16px]">{{ current.flag }}</span>
            <span class="font-medium">{{ current.label }}</span>
            <i class="fas fa-chevron-down text-[10px] text-[#94a3b8] rtl-flip"></i>
        </Button>

        <div
            v-if="open"
            class="absolute top-full mt-1 bg-white border border-[#e2e8f0] rounded-md shadow-[0_4px_6px_-1px_rgba(0,0,0,0.07)] min-w-[140px] z-50"
            :class="dir === 'rtl' ? 'left-0' : 'right-0'"
        >
            <button
                v-for="lang in languages"
                :key="lang.code"
                type="button"
                class="w-full flex items-center gap-2 px-3 py-2 text-[14px] text-right hover:bg-[#f1f5f9] transition-colors"
                :class="lang.code === locale ? 'text-[#2563eb] font-semibold' : 'text-[#475569]'"
                @click="switchTo(lang.code)"
            >
                <span class="text-[16px]">{{ lang.flag }}</span>
                <span>{{ lang.label }}</span>
                <i v-if="lang.code === locale" class="fas fa-check text-[12px] mr-auto"></i>
            </Button>
        </div>
    </div>
</template>
