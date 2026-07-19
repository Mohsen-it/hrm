<script setup>
import { computed, ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    modelValue: { type: [File, null], default: null },
    label: { type: String, default: '' },
    accept: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || `file-${props.name || Math.random().toString(36).slice(2)}`);

const isDragOver = ref(false);

const fileName = computed(() => {
    if (!props.modelValue) return '';
    return props.modelValue.name || '';
});

const fileSize = computed(() => {
    if (!props.modelValue) return '';
    const bytes = props.modelValue.size;
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
});

function onChange(event) {
    const file = event.target.files?.[0] || null;
    emit('update:modelValue', file);
}

function clear() {
    emit('update:modelValue', null);
}

function onDragOver(e) {
    e.preventDefault();
    isDragOver.value = true;
}

function onDragLeave() {
    isDragOver.value = false;
}

function onDrop(e) {
    e.preventDefault();
    isDragOver.value = false;
    const file = e.dataTransfer.files?.[0] || null;
    emit('update:modelValue', file);
}
</script>

<template>
    <div :dir="dir">
        <label
            v-if="label"
            :for="inputId"
            class="block text-[13px] text-mistral-ink font-medium mb-1.5"
        >
            {{ label }}
            <span v-if="required" class="text-mistral-danger ms-0.5">*</span>
        </label>

        <div
            v-if="!modelValue"
            :class="[
                'border-2 border-dashed rounded-lg p-6 text-center transition-all duration-150',
                isDragOver
                    ? 'border-mistral-primary bg-mistral-primary/5'
                    : 'border-mistral-hairline-strong hover:border-mistral-stone',
                disabled ? 'opacity-50 pointer-events-none' : 'cursor-pointer',
            ]"
            @dragover="onDragOver"
            @dragleave="onDragLeave"
            @drop="onDrop"
            @click="$refs.fileInput.click()"
        >
            <i class="fas fa-cloud-arrow-up text-[24px] text-mistral-stone mb-2" aria-hidden="true"></i>
            <p class="text-[13px] text-mistral-steel">
                {{ t('common.drag_file_here') }} <span class="text-mistral-primary font-medium">{{ t('common.choose_file') }}</span>
            </p>
        </div>

        <div v-else class="flex items-center gap-3 p-3 bg-mistral-surface rounded-lg border border-mistral-hairline-soft">
            <div class="w-10 h-10 rounded-lg bg-mistral-primary/10 flex items-center justify-center shrink-0">
                <i class="fas fa-file text-mistral-primary text-[14px]" aria-hidden="true"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-[13px] font-medium text-mistral-ink truncate">{{ fileName }}</p>
                <p v-if="fileSize" class="text-[11px] text-mistral-stone">{{ fileSize }}</p>
            </div>
            <button
                v-if="!disabled"
                type="button"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-mistral-stone hover:text-mistral-danger hover:bg-mistral-danger/10 transition-colors"
                :aria-label="t('common.remove')"
                @click="clear"
            >
                <i class="fas fa-xmark text-[12px]" aria-hidden="true"></i>
            </button>
        </div>

        <input
            :id="inputId"
            ref="fileInput"
            type="file"
            :accept="accept"
            :name="name"
            :required="required"
            :disabled="disabled"
            class="sr-only"
            @change="onChange"
        />

        <p v-if="error" class="mt-1.5 text-[12px] text-mistral-danger flex items-center gap-1" role="alert">
            <i class="fas fa-exclamation-circle text-[10px]" aria-hidden="true"></i>
            {{ error }}
        </p>
        <p v-else-if="hint" class="mt-1.5 text-[12px] text-mistral-stone">{{ hint }}</p>
    </div>
</template>
