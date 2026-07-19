<script setup>
import { onMounted, onUnmounted } from 'vue';
import Button from './Button.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    saveLabel: { type: String, default: null },
    cancelLabel: { type: String, default: null },
    cancelHref: { type: String, default: null },
    saving: { type: Boolean, default: false },
    showShortcut: { type: Boolean, default: true },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['save', 'cancel']);

function onKeyDown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        if (!props.saving) {
            emit('save');
        }
    }
}

onMounted(() => {
    document.addEventListener('keydown', onKeyDown);
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeyDown);
});
</script>

<template>
    <div
        :class="[
            'sticky bottom-0 z-30 border-t border-mistral-hairline-soft bg-white/95 backdrop-blur-sm',
            'px-5 sm:px-6 py-4',
        ]"
        :dir="dir"
    >
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <Button
                    type="submit"
                    variant="primary"
                    :loading="saving"
                    icon="fas fa-check"
                    size="md"
                >
                    {{ saveLabel || t('common.save') }}
                </Button>
                <Button
                    v-if="cancelHref"
                    variant="secondary"
                    :href="cancelHref"
                    size="md"
                >
                    {{ cancelLabel || t('common.cancel') }}
                </Button>
                <Button
                    v-else
                    variant="secondary"
                    size="md"
                    @click="emit('cancel')"
                >
                    {{ cancelLabel || t('common.cancel') }}
                </Button>
            </div>

            <p
                v-if="showShortcut"
                class="hidden sm:flex items-center gap-1.5 text-[11px] text-mistral-stone"
            >
                <kbd class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-mistral-surface border border-mistral-hairline-soft rounded text-[10px] font-mono text-mistral-steel">
                    Ctrl
                </kbd>
                <span>+</span>
                <kbd class="inline-flex items-center gap-0.5 px-1.5 py-0.5 bg-mistral-surface border border-mistral-hairline-soft rounded text-[10px] font-mono text-mistral-steel">
                    Enter
                </kbd>
                <span class="me-1">{{ t('common.to_save') }}</span>
            </p>
        </div>
    </div>
</template>
