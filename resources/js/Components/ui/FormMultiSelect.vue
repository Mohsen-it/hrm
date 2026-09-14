<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, required: true },
    label: { type: String, default: '' },
    placeholder: { type: String, default: 'اختر...' },
    searchable: { type: Boolean, default: true },
    searchPlaceholder: { type: String, default: 'بحث...' },
    emptyText: { type: String, default: 'لا توجد نتائج' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    name: { type: String, default: '' },
    id: { type: String, default: '' },
    maxVisibleTags: { type: Number, default: 3 },
    dir: { type: String, default: 'rtl' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const inputId = computed(() => props.id || props.name || `multi-${Math.random().toString(36).slice(2, 9)}`);
const isOpen = ref(false);
const isFocused = ref(false);
const search = ref('');
const triggerRef = ref(null);
const dropdownRef = ref(null);

const selectedItems = computed(() => {
    if (!Array.isArray(props.modelValue)) return [];
    return props.options.filter((opt) => props.modelValue.includes(opt.value));
});

const selectedCount = computed(() => props.modelValue.length);

const visibleTags = computed(() => selectedItems.value.slice(0, props.maxVisibleTags));
const hiddenCount = computed(() => Math.max(0, selectedCount.value - props.maxVisibleTags));

const filteredOptions = computed(() => {
    if (!props.searchable || !search.value.trim()) return props.options;
    const q = search.value.trim().toLowerCase();
    return props.options.filter((opt) => String(opt.label ?? '').toLowerCase().includes(q));
});

const hasValue = computed(() => selectedCount.value > 0);
const isFloating = computed(() => isFocused.value || hasValue.value);

function isSelected(value) {
    return props.modelValue.includes(value);
}

function toggleOption(opt) {
    if (props.disabled) return;
    const next = [...props.modelValue];
    const idx = next.indexOf(opt.value);
    if (idx === -1) next.push(opt.value);
    else next.splice(idx, 1);
    emit('update:modelValue', next);
    emit('change', next);
}

function removeTag(value, e) {
    if (e) e.stopPropagation();
    const next = props.modelValue.filter((v) => v !== value);
    emit('update:modelValue', next);
    emit('change', next);
}

function clearAll(e) {
    if (e) e.stopPropagation();
    emit('update:modelValue', []);
    emit('change', []);
    search.value = '';
}

function selectAll(e) {
    if (e) e.stopPropagation();
    if (props.disabled) return;
    const values = new Set(props.modelValue);
    filteredOptions.value.forEach((opt) => values.add(opt.value));
    const next = [...values];
    emit('update:modelValue', next);
    emit('change', next);
}

function open() {
    if (props.disabled) return;
    isOpen.value = true;
    nextTick(() => {
        const inp = dropdownRef.value?.querySelector('input');
        inp?.focus();
    });
}

function close() {
    isOpen.value = false;
    search.value = '';
    isFocused.value = false;
}

function onTriggerKeydown(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        isOpen.value ? close() : open();
    } else if (e.key === 'Backspace' && search.value === '' && selectedCount.value > 0) {
        removeTag(props.modelValue[selectedCount.value - 1]);
    }
}

function onClickOutside(e) {
    const root = triggerRef.value;
    if (root && !root.contains(e.target)) close();
}

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside));

watch(() => props.disabled, (val) => { if (val) close(); });
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
                @keydown="onTriggerKeydown"
                :aria-expanded="isOpen"
                :aria-haspopup="true"
                :aria-invalid="!!error"
                :class="[
                    'peer w-full min-h-[44px] pt-5 pb-1.5 px-3 text-start text-[14px] bg-mistral-canvas border rounded-md transition-all duration-200',
                    'focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary',
                    'disabled:bg-mistral-surface disabled:text-mistral-muted disabled:cursor-not-allowed',
                    error
                        ? 'border-mistral-danger focus:ring-mistral-danger/20 focus:border-mistral-danger'
                        : 'border-mistral-hairline-strong hover:border-mistral-stone',
                    isOpen && !error && 'border-mistral-primary ring-2 ring-mistral-primary/20',
                ]"
            >
                <div v-if="selectedCount === 0" class="text-mistral-muted text-[14px]">
                    {{ placeholder }}
                </div>
                <div v-else class="flex flex-wrap items-center gap-1.5">
                    <span
                        v-for="item in visibleTags"
                        :key="item.value"
                        class="inline-flex items-center gap-1 bg-mistral-primary/10 text-mistral-primary rounded-md px-2 py-0.5 text-[12px] font-medium"
                    >
                        <span class="max-w-[120px] truncate">{{ item.label }}</span>
                        <button
                            type="button"
                            @click="removeTag(item.value, $event)"
                            class="hover:text-mistral-primary-deep"
                            :aria-label="t('common.remove_item', { item: item.label })"
                        >
                            <i class="fas fa-times text-[10px]"></i>
                        </button>
                    </span>
                    <span
                        v-if="hiddenCount > 0"
                        class="inline-flex items-center bg-mistral-surface text-mistral-steel rounded-md px-2 py-0.5 text-[12px] font-medium"
                    >
                        +{{ hiddenCount }}
                    </span>
                </div>
            </button>

            <label
                v-if="label"
                :for="inputId"
                :class="[
                    'absolute text-[13px] font-medium pointer-events-none transition-all duration-200 origin-top-start z-10',
                    isFloating
                        ? 'top-1.5 start-3 text-[11px]'
                        : 'top-2.5 start-3 text-[14px]',
                    isFloating && 'text-mistral-steel',
                    !isFloating && 'text-mistral-muted',
                    isFocused && !error && 'text-mistral-primary',
                    error && 'text-mistral-danger',
                ]"
            >
                {{ label }}
                <span v-if="required" class="text-mistral-danger ms-0.5" aria-hidden="true">*</span>
            </label>

            <div class="absolute top-0 bottom-0 end-2 flex items-center gap-1">
                <button
                    v-if="hasValue && !disabled"
                    type="button"
                    @click="clearAll"
                    class="text-mistral-muted hover:text-mistral-danger w-6 h-6 flex items-center justify-center rounded transition-colors"
                    :aria-label="t('common.remove')"
                >
                    <i class="fas fa-times-circle text-[12px]"></i>
                </button>
                <span
                    class="text-mistral-muted pointer-events-none"
                    :class="isOpen ? 'rotate-180' : ''"
                    style="transition: transform 0.2s"
                >
                    <i class="fas fa-chevron-down text-[10px]"></i>
                </span>
            </div>

            <div
                v-if="isOpen"
                ref="dropdownRef"
                class="absolute z-50 mt-1 start-0 w-full bg-mistral-canvas border border-mistral-hairline-strong rounded-lg shadow-lg overflow-hidden"
            >
                <div v-if="searchable" class="p-2 border-b border-mistral-hairline-soft">
                    <div class="relative">
                        <i class="fas fa-search absolute start-2.5 top-1/2 -translate-y-1/2 text-mistral-muted text-[12px]"></i>
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="searchPlaceholder"
                            class="w-full h-9 text-[13px] bg-mistral-surface/40 border border-mistral-hairline rounded-md focus:outline-none focus:border-mistral-primary ps-8 pe-2"
                            @keydown.escape="close"
                            @keydown.enter.prevent
                        />
                    </div>
                </div>
                <div class="max-h-60 overflow-auto py-1">
                    <button
                        v-for="opt in filteredOptions"
                        :key="opt.value"
                        type="button"
                        @click="toggleOption(opt)"
                        :class="[
                            'w-full flex items-center gap-2 px-3 py-2 text-[13px] text-start transition-colors',
                            isSelected(opt.value)
                                ? 'bg-mistral-primary/5 text-mistral-primary font-medium'
                                : 'text-mistral-ink hover:bg-mistral-surface',
                        ]"
                    >
                        <span
                            :class="[
                                'w-4 h-4 rounded border flex items-center justify-center shrink-0',
                                isSelected(opt.value)
                                    ? 'bg-mistral-primary border-mistral-primary'
                                    : 'border-mistral-hairline-strong',
                            ]"
                        >
                            <i v-if="isSelected(opt.value)" class="fas fa-check text-white text-[9px]"></i>
                        </span>
                        <span class="flex-1 truncate">{{ opt.label }}</span>
                    </button>
                    <div
                        v-if="filteredOptions.length === 0"
                        class="px-3 py-4 text-center text-[12px] text-mistral-muted"
                    >
                        {{ emptyText }}
                    </div>
                </div>
                <div v-if="options.length > 0" class="px-3 py-2 border-t border-mistral-hairline-soft bg-mistral-surface/40 text-[12px] text-mistral-steel flex items-center justify-between">
                    <span>{{ selectedCount }} محدد</span>
                    <div class="flex items-center gap-3">
                        <button
                            v-if="selectedCount < options.length"
                            type="button"
                            @click="selectAll"
                            class="text-mistral-primary hover:underline"
                        >
                            تحديد الكل
                        </button>
                        <button
                            v-if="hasValue"
                            type="button"
                            @click="clearAll"
                            class="text-mistral-primary hover:underline"
                        >
                            مسح الكل
                        </button>
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
