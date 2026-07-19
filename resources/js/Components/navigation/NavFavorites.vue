<script setup>
import { computed } from 'vue';
import NavItem from './NavItem.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
  items: { type: Array, default: () => [] },
  isCollapsed: { type: Boolean, default: false },
  activeItemId: { type: String, default: null },
});

const emit = defineEmits(['toggle-favorite', 'navigate']);

const { t } = useTranslations();

const hasItems = computed(() => props.items.length > 0);
</script>

<template>
  <div v-if="hasItems && !isCollapsed" class="nav-favorites">
    <div class="nav-favorites__header">
      <i class="fa-solid fa-thumbtack text-mistral-muted text-[10px]" aria-hidden="true"></i>
      <span class="nav-favorites__title">{{ t('common.pinned') }}</span>
    </div>
    <div class="nav-favorites__items">
      <NavItem
        v-for="item in items"
        :key="item.id"
        :item="item"
        :is-collapsed="false"
        :is-active="item.id === activeItemId"
        :is-favorite="true"
        @navigate="$emit('navigate')"
        @toggle-favorite="$emit('toggle-favorite', item.id)"
      />
    </div>
    <div class="nav-favorites__separator"></div>
  </div>
</template>
