<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    options: { type: Array, required: true },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'اختر...' },
    searchPlaceholder: { type: String, default: 'بحث...' },
    emptyText: { type: String, default: 'لا توجد نتائج' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const inputId = computed(() => props.id || props.name || `searchable-${Math.random().toString(36).slice(2, 9)}`);
const isOpen = ref(false);
const isFocused = ref(false);
const search = ref('');
const triggerRef = ref(null);
const dropdownRef = ref(null);

// Position state for fixed dropdown
const dropdownStyle = ref({});

const selectedOption = computed(() => props.options.find(opt => opt.value === props.modelValue));

const filteredOptions = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((opt) => String(opt.label ?? '').toLowerCase().includes(q));
});

const isFloating = computed(() => isFocused.value || props.modelValue);

function updatePosition() {
    if (!isOpen.value || !triggerRef.value) return;
    const rect = triggerRef.value.getBoundingClientRect();
    dropdownStyle.value = {
        top: `${rect.bottom + window.scrollY + 4}px`,
        left: `${rect.left + window.scrollX}px`,
        width: `${rect.width}px`,
    };
}

function selectOption(opt) {
    if (props.disabled) return;
    emit('update:modelValue', opt.value);
    emit('change', opt.value);
    close();
}

function open() {
    if (props.disabled) return;
    isOpen.value = true;
    nextTick(() => {
        updatePosition();
        const inp = dropdownRef.value?.querySelector('input');
        inp?.focus();
    });
}

function close() {
    isOpen.value = false;
    search.value = '';
    isFocused.value = false;
}

function onClickOutside(e) {
    if (triggerRef.value && !triggerRef.value.contains(e.target)) close();
}

onMounted(() => {
    document.addEventListener('mousedown', onClickOutside);
    window.addEventListener('scroll', updatePosition, true);
    window.addEventListener('resize', updatePosition);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onClickOutside);
    window.removeEventListener('scroll', updatePosition, true);
    window.removeEventListener('resize', updatePosition);
});
</script>

<template>
    <div class="w-full text-start relative" :dir="dir">
        <div ref="triggerRef" class="relative">
            <button
                type="button"
                :id="inputId"
                :disabled="disabled"
                @click="isOpen ? close() : open()"
                @focus="isFocused = true"
                @blur="isFocused = false"
                :class="[
                    'peer w-full h-11 pt-3 pb-1 px-3 text-start text-[14px] bg-white border rounded-lg transition-all duration-200',
                    'focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary',
                    'disabled:bg-mistral-surface disabled:text-mistral-muted disabled:cursor-not-allowed',
                    error
                        ? 'border-mistral-danger focus:ring-mistral-danger/20 focus:border-mistral-danger'
                        : 'border-mistral-hairline-strong hover:border-mistral-stone',
                ]"
            >
                <span :class="selectedOption ? 'text-mistral-ink' : 'text-mistral-muted'">
                    {{ selectedOption ? selectedOption.label : placeholder }}
                </span>
            </button>

            <label
                v-if="label"
                :for="inputId"
                :class="[
                    'absolute text-[13px] font-medium pointer-events-none transition-all duration-200 origin-top-start z-10',
                    isFloating
                        ? (dir === 'rtl' ? 'top-1.5 right-3 text-[11px]' : 'top-1.5 left-3 text-[11px]')
                        : (dir === 'rtl' ? 'top-2.5 right-3 text-[14px]' : 'top-2.5 left-3 text-[14px]'),
                    isFloating && 'text-mistral-steel',
                    !isFloating && 'text-mistral-muted',
                    isFocused && !error && 'text-mistral-primary',
                    error && 'text-mistral-danger',
                ]"
            >
                {{ label }}
                <span v-if="required" class="text-mistral-danger ms-0.5" aria-hidden="true">*</span>
            </label>

            <div class="absolute top-0 bottom-0 flex items-center gap-1" :class="dir === 'rtl' ? 'left-2' : 'right-2'">
                <span class="text-mistral-muted pointer-events-none" :class="isOpen ? 'rotate-180' : ''" style="transition: transform 0.2s">
                    <i class="fas fa-chevron-down text-[10px]"></i>
                </span>
            </div>

            <div
                v-if="isOpen"
                ref="dropdownRef"
                :style="dropdownStyle"
                class="fixed z-[9999] bg-white border border-mistral-hairline-strong rounded-lg shadow-xl overflow-hidden"
            >
                <div class="p-2 border-b border-mistral-hairline-soft">
                    <div class="relative">
                        <i class="fas fa-search absolute top-1/2 -translate-y-1/2 text-mistral-muted text-[12px]" :class="dir === 'rtl' ? 'right-2.5' : 'left-2.5'"></i>
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="searchPlaceholder"
                            class="w-full h-9 text-[13px] bg-mistral-surface/40 border border-mistral-hairline rounded-md focus:outline-none focus:border-mistral-primary"
                            :class="dir === 'rtl' ? 'pr-8 pl-2' : 'pl-8 pr-2'"
                        />
                    </div>
                </div>
                <div class="max-h-60 overflow-auto py-1">
                    <button
                        v-for="opt in filteredOptions"
                        :key="opt.value"
                        type="button"
                        @click="selectOption(opt)"
                        :class="[
                            'w-full px-3 py-2 text-[13px] text-start transition-colors',
                            modelValue === opt.value
                                ? 'bg-mistral-primary/5 text-mistral-primary font-medium'
                                : 'text-mistral-ink hover:bg-mistral-surface',
                        ]"
                    >
                        {{ opt.label }}
                    </button>
                    <div v-if="filteredOptions.length === 0" class="px-3 py-4 text-center text-[12px] text-mistral-muted">
                        {{ emptyText }}
                    </div>
                </div>
            </div>
        </div>
        <p v-if="hint && !error" class="text-[12px] text-mistral-stone mt-1">
            {{ hint }}
        </p>
        <p v-if="error" class="text-[12px] text-mistral-danger mt-1 flex items-center gap-1" role="alert">
            <i class="fas fa-exclamation-circle text-[10px]" aria-hidden="true"></i>
            {{ error }}
        </p>
    </div>
</template>
