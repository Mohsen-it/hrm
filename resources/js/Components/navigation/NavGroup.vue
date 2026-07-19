<script setup>
import { computed } from 'vue';
import NavItem from './NavItem.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
  group: { type: Object, required: true },
  isCollapsed: { type: Boolean, default: false },
  isExpanded: { type: Boolean, default: false },
  activeItemId: { type: String, default: null },
  favoriteIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['toggle', 'toggle-favorite', 'navigate']);

const { t } = useTranslations();

const hasActiveItem = computed(() =>
  props.group.items.some((item) => item.id === props.activeItemId),
);

const groupLabel = computed(() => t(props.group.label));
</script>

<template>
  <div class="nav-group" :class="{ 'nav-group--active': hasActiveItem }">
    <!-- Group header -->
    <button
      v-if="!isCollapsed && group.items.length > 1"
      type="button"
      class="nav-group__header"
      :aria-expanded="isExpanded"
      :aria-label="groupLabel"
      @click="$emit('toggle')"
    >
      <span class="nav-group__label">{{ groupLabel }}</span>
      <i
        :class="[
          'fa-solid fa-chevron-down nav-group__chevron',
          { 'nav-group__chevron--open': isExpanded },
        ]"
        aria-hidden="true"
      ></i>
    </button>

    <!-- Collapsed: section divider with icon -->
    <div v-else-if="isCollapsed" class="nav-group__divider">
      <div class="nav-group__divider-line"></div>
    </div>

    <!-- Single-item collapsed: show as flat item -->
    <template v-if="isCollapsed && group.items.length === 1">
      <NavItem
        :item="group.items[0]"
        :is-collapsed="true"
        :is-active="group.items[0].id === activeItemId"
        :is-favorite="favoriteIds.includes(group.items[0].id)"
        @navigate="$emit('navigate')"
        @toggle-favorite="$emit('toggle-favorite', group.items[0].id)"
      />
    </template>

    <!-- Multi-item expanded: show list -->
    <div
      v-else-if="!isCollapsed"
      v-show="isExpanded || hasActiveItem"
      class="nav-group__items"
    >
      <NavItem
        v-for="item in group.items"
        :key="item.id"
        :item="item"
        :is-collapsed="false"
        :is-active="item.id === activeItemId"
        :is-favorite="favoriteIds.includes(item.id)"
        @navigate="$emit('navigate')"
        @toggle-favorite="$emit('toggle-favorite', item.id)"
      />
    </div>

    <!-- Multi-item collapsed: show all icons -->
    <div v-else-if="isCollapsed" class="nav-group__items--collapsed">
      <NavItem
        v-for="item in group.items"
        :key="item.id"
        :item="item"
        :is-collapsed="true"
        :is-active="item.id === activeItemId"
        :is-favorite="favoriteIds.includes(item.id)"
        @navigate="$emit('navigate')"
        @toggle-favorite="$emit('toggle-favorite', item.id)"
      />
    </div>
  </div>
</template>
