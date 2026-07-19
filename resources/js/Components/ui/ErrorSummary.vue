<script setup>
import { computed, ref, onMounted, nextTick } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    errors: { type: Object, default: () => ({}) },
    dir: { type: String, default: 'rtl' },
});

const isVisible = ref(false);

const errorList = computed(() => {
    return Object.entries(props.errors)
        .filter(([, value]) => value && typeof value === 'string')
        .map(([key, message]) => ({
            key,
            message,
            fieldId: key.replace(/\./g, '-'),
        }));
});

const errorCount = computed(() => errorList.value.length);

function scrollToField(fieldId) {
    const el = document.getElementById(fieldId);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.focus({ preventScroll: true });
    }
}

onMounted(async () => {
    if (errorCount.value > 0) {
        await nextTick();
        setTimeout(() => {
            isVisible.value = true;
        }, 100);
    }
});
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="opacity-0 -translate-y-2 scale-[0.98]"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 -translate-y-2 scale-[0-98]"
    >
        <div
            v-if="errorCount > 0 && isVisible"
            class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6"
            role="alert"
            :dir="dir"
        >
            <div class="flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0 mt-0.5">
                    <i class="fas fa-circle-exclamation text-red-600 text-[14px]" aria-hidden="true"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="text-[14px] font-semibold text-red-800">
                        {{ errorCount === 1 ? t('common.errors_one', { count: 1 }) : t('common.errors_many', { count: errorCount }) }}
                    </h3>
                    <ul class="mt-2 space-y-1">
                        <li
                            v-for="error in errorList"
                            :key="error.key"
                        >
                            <button
                                type="button"
                                class="text-[13px] text-red-700 hover:text-red-900 hover:underline transition-colors cursor-pointer"
                                @click="scrollToField(error.fieldId)"
                            >
                                {{ error.message }}
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </Transition>
</template>
