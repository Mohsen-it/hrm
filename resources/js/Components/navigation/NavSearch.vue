<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
  modelValue: { type: String, default: '' },
  isCollapsed: { type: Boolean, default: false },
  results: { type: Array, default: () => [] },
  isActive: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select', 'close']);

const { t } = useTranslations();
const inputRef = ref(null);
const selectedIndex = ref(-1);

function onInput(e) {
  emit('update:modelValue', e.target.value);
  selectedIndex.value = -1;
}

function onKeyDown(e) {
  if (e.key === 'Escape') {
    emit('update:modelValue', '');
    emit('close');
    inputRef.value?.blur();
    return;
  }
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex.value = Math.min(selectedIndex.value + 1, props.results.length - 1);
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex.value = Math.max(selectedIndex.value - 1, -1);
  }
  if (e.key === 'Enter' && selectedIndex.value >= 0) {
    e.preventDefault();
    emit('select', props.results[selectedIndex.value]);
  }
}

function highlightMatch(text) {
  const q = props.modelValue.trim();
  if (!q) return t(text);
  const label = t(text);
  const regex = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
  return label.replace(regex, '<mark class="bg-mistral-primary/15 text-mistral-primary rounded-sm px-0.5">$1</mark>');
}

onMounted(() => {
  if (!props.isCollapsed) {
    nextTick(() => inputRef.value?.focus());
  }
});
</script>

<template>
  <div class="relative">
    <div
      :class="[
        'flex items-center gap-2 rounded-lg transition-colors',
        isCollapsed
          ? 'justify-center w-9 h-9 mx-auto'
          : 'h-9 px-3 mx-2',
        isActive
          ? 'bg-mistral-surface ring-1 ring-mistral-primary/30'
          : 'bg-mistral-surface hover:bg-mistral-hairline-soft',
      ]"
    >
      <i
        class="fa-solid fa-magnifying-glass text-mistral-stone shrink-0"
        :class="isCollapsed ? 'text-[13px]' : 'text-[12px]'"
        aria-hidden="true"
      ></i>
      <input
        v-if="!isCollapsed"
        ref="inputRef"
        type="text"
        :value="modelValue"
        :placeholder="t('common.search') + '...'"
        class="w-full bg-transparent text-[13px] text-mistral-ink placeholder:text-mistral-muted outline-none"
        autocomplete="off"
        role="searchbox"
        :aria-label="t('common.search')"
        @input="onInput"
        @keydown="onKeyDown"
      />
    </div>

    <!-- Search results dropdown -->
    <div
      v-if="isActive && results.length > 0 && !isCollapsed"
      class="absolute top-full left-0 right-0 mx-2 mt-1 bg-mistral-canvas border border-mistral-hairline-soft rounded-xl shadow-lg z-50 max-h-[320px] overflow-y-auto py-1"
      role="listbox"
    >
      <button
        v-for="(item, idx) in results"
        :key="item.id"
        type="button"
        :class="[
          'w-full flex items-center gap-3 px-3 py-2 text-start transition-colors',
          idx === selectedIndex
            ? 'bg-mistral-primary/8 text-mistral-primary'
            : 'text-mistral-steel hover:bg-mistral-surface',
        ]"
        role="option"
        :aria-selected="idx === selectedIndex"
        @click="$emit('select', item)"
        @mouseenter="selectedIndex = idx"
      >
        <i :class="[item.icon, 'text-[13px] w-5 text-center shrink-0']" aria-hidden="true"></i>
        <span
          class="text-[13px] truncate"
          v-html="highlightMatch(item.label)"
        ></span>
      </button>
    </div>

    <!-- No results -->
    <div
      v-else-if="isActive && results.length === 0 && !isCollapsed"
      class="absolute top-full left-0 right-0 mx-2 mt-1 bg-mistral-canvas border border-mistral-hairline-soft rounded-xl shadow-lg z-50 py-6 text-center"
    >
      <i class="fa-solid fa-magnifying-glass text-mistral-muted text-[20px] mb-2 block" aria-hidden="true"></i>
      <p class="text-[13px] text-mistral-stone">{{ t('common.no_data') }}</p>
    </div>
  </div>
</template>
