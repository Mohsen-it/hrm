<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
  item: { type: Object, required: true },
  isCollapsed: { type: Boolean, default: false },
  isActive: { type: Boolean, default: false },
  isFavorite: { type: Boolean, default: false },
  level: { type: Number, default: 0 },
});

const emit = defineEmits(['toggle-favorite', 'navigate']);

const { t } = useTranslations();

const href = computed(() => {
  if (typeof window === 'undefined' || typeof window.route !== 'function') {
    return '#';
  }
  try {
    return window.route(props.item.route);
  } catch {
    return '#';
  }
});

const itemClasses = computed(() => {
  const classes = [
    'nav-item',
    { 'nav-item--active': props.isActive },
  ];
  if (props.isCollapsed) classes.push('nav-item--collapsed');
  if (props.level > 0) classes.push('nav-item--nested');
  return classes;
});
</script>

<template>
  <div class="nav-item-wrapper group" :class="{ 'px-2': !isCollapsed }">
    <Link
      :href="href"
      :class="itemClasses"
      :title="isCollapsed ? t(item.label) : ''"
      :aria-current="isActive ? 'page' : null"
      @click="$emit('navigate')"
    >
      <i
        v-if="item.icon"
        :class="[item.icon, 'nav-item__icon']"
        aria-hidden="true"
      ></i>
      <span v-if="!isCollapsed" class="nav-item__label">{{ t(item.label) }}</span>

      <!-- Favorite toggle (visible on hover, hidden when collapsed) -->
      <button
        v-if="!isCollapsed"
        type="button"
        :class="[
          'nav-item__fav',
          isFavorite ? 'nav-item__fav--active' : '',
        ]"
        :aria-label="isFavorite ? 'Unpin' : 'Pin to favorites'"
        :title="isFavorite ? 'Unpin' : 'Pin to favorites'"
        @click.prevent.stop="$emit('toggle-favorite')"
      >
        <i :class="isFavorite ? 'fa-solid fa-thumbtack' : 'fa-regular fa-thumbtack'" aria-hidden="true"></i>
      </button>
    </Link>

    <!-- Tooltip for collapsed mode -->
    <div
      v-if="isCollapsed"
      class="nav-tooltip"
      role="tooltip"
    >
      {{ t(item.label) }}
    </div>
  </div>
</template>
