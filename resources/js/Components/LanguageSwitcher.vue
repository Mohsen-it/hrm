<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const { locale, t } = useTranslations();

const props = defineProps({
    dir: { type: String, default: 'rtl' },
});

const open = ref(false);
const dropdownRef = ref(null);

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

function handleClickOutside(e) {
    if (dropdownRef.value && !dropdownRef.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', handleClickOutside));
onUnmounted(() => document.removeEventListener('click', handleClickOutside));
</script>

<template>
    <div ref="dropdownRef" class="relative" :dir="dir">
        <button
            type="button"
            class="inline-flex items-center gap-2 h-8 px-3 text-[13px] font-medium rounded-lg text-mistral-steel hover:text-mistral-ink hover:bg-mistral-surface transition-colors cursor-pointer"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="open = !open"
        >
            <span class="text-[15px]">{{ current.flag }}</span>
            <span>{{ current.label }}</span>
            <i class="fas fa-chevron-down text-[9px] text-mistral-muted rtl-flip" aria-hidden="true"></i>
        </button>

        <Transition
            enter-active-class="duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute top-full mt-1 bg-white border border-mistral-hairline-soft rounded-xl shadow-level-3 min-w-[150px] z-50 py-1 overflow-hidden"
                :class="dir === 'rtl' ? 'left-0' : 'right-0'"
                role="listbox"
                :aria-label="t('common.language') || 'Language'"
            >
                <button
                    v-for="lang in languages"
                    :key="lang.code"
                    type="button"
                    role="option"
                    :aria-selected="lang.code === locale"
                    class="w-full flex items-center gap-2.5 px-3 py-2 text-[13px] text-end hover:bg-mistral-surface transition-colors cursor-pointer"
                    :class="lang.code === locale ? 'text-mistral-primary font-semibold bg-mistral-primary/5' : 'text-mistral-steel'"
                    @click="switchTo(lang.code)"
                >
                    <span class="text-[15px]">{{ lang.flag }}</span>
                    <span>{{ lang.label }}</span>
                    <i v-if="lang.code === locale" class="fas fa-check text-[11px] ms-auto text-mistral-primary" aria-hidden="true"></i>
                </button>
            </div>
        </Transition>
    </div>
</template>
