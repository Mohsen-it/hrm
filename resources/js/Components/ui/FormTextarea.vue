<script setup>
import { computed, ref, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    rows: { type: Number, default: 3 },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    autofocus: { type: Boolean, default: false },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue']);

const inputId = computed(() => props.id || props.name || `textarea-${Math.random().toString(36).slice(2, 9)}`);
const isFocused = ref(false);
const textareaRef = ref(null);

const hasValue = computed(() => {
    const v = props.modelValue;
    if (v === null || v === undefined || v === '') return false;
    return true;
});

const isFloating = computed(() => isFocused.value || hasValue.value);

function onInput(e) {
    emit('update:modelValue', e.target.value);
    autoResize(e.target);
}

function onFocus() {
    isFocused.value = true;
}

function onBlur() {
    isFocused.value = false;
}

function autoResize(el) {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

function focus() {
    textareaRef.value?.focus();
}

defineExpose({ focus });
</script>

<template>
    <div class="w-full text-start" :dir="dir">
        <div class="relative" @click="focus">
            <textarea
                :id="inputId"
                ref="textareaRef"
                :name="name"
                :value="modelValue"
                :placeholder="isFloating ? placeholder : ''"
                :required="required"
                :disabled="disabled"
                :rows="rows"
                :autofocus="autofocus"
                :aria-invalid="!!error"
                :aria-describedby="error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined"
                :class="[
                    'peer w-full pt-3 pb-2 px-3 text-[14px] text-mistral-ink bg-white border rounded-lg transition-all duration-200 resize-y min-h-[80px]',
                    'placeholder:text-transparent',
                    'focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary',
                    'disabled:bg-mistral-surface disabled:text-mistral-muted disabled:cursor-not-allowed',
                    error
                        ? 'border-mistral-danger focus:ring-mistral-danger/20 focus:border-mistral-danger'
                        : 'border-mistral-hairline-strong hover:border-mistral-stone',
                ]"
                @input="onInput"
                @focus="onFocus"
                @blur="onBlur"
            ></textarea>
            <label
                v-if="label"
                :for="inputId"
                :class="[
                    'absolute text-[13px] font-medium pointer-events-none transition-all duration-200 origin-top-start z-10',
                    isFloating
                        ? (dir === 'rtl' ? 'top-1.5 right-3 text-[11px]' : 'top-1.5 left-3 text-[11px]')
                        : (dir === 'rtl' ? 'top-3 right-3 text-[14px]' : 'top-3 left-3 text-[14px]'),
                    isFloating && 'text-mistral-steel',
                    !isFloating && 'text-mistral-muted',
                    isFocused && !error && 'text-mistral-primary',
                    error && 'text-mistral-danger',
                ]"
            >
                {{ label }}
                <span v-if="required" class="text-mistral-danger ms-0.5" aria-hidden="true">*</span>
            </label>
        </div>
        <p v-if="hint && !error" :id="`${inputId}-hint`" class="text-[12px] text-mistral-stone mt-1">
            {{ hint }}
        </p>
        <p v-if="error" :id="`${inputId}-error`" class="text-[12px] text-mistral-danger mt-1 flex items-center gap-1" role="alert">
            <i class="fas fa-exclamation-circle text-[10px]" aria-hidden="true"></i>
            {{ error }}
        </p>
    </div>
</template>
