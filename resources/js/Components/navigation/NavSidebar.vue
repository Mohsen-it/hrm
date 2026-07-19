<script setup>
import { computed, ref } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import { useNavSidebar } from '@/composables/useNavSidebar';
import NavSearch from './NavSearch.vue';
import NavFavorites from './NavFavorites.vue';
import NavGroup from './NavGroup.vue';
import NavUser from './NavUser.vue';

const props = defineProps({
  isOpen: { type: Boolean, default: true },
  isCollapsed: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'toggle-collapse']);

const { t, isRtl } = useTranslations();

const {
  user,
  favorites,
  searchQuery,
  visibleGroups,
  favoriteItems,
  activeItemId,
  searchResults,
  isSearchActive,
  toggleGroup,
  isGroupExpanded,
  toggleFavorite,
  isFavorite,
  resolveRoute,
} = useNavSidebar();

const scrollRef = ref(null);

// Close search on escape
function closeSearch() {
  searchQuery.value = '';
}

// Handle search result selection
function onSearchSelect(item) {
  const href = resolveRoute(item.route);
  if (href && href !== '#') {
    window.location.href = href;
  }
  searchQuery.value = '';
}

// Close mobile sidebar on navigate
function onNavigate() {
  if (window.innerWidth < 768) {
    emit('close');
  }
}

// Sidebar width classes
const widthClass = computed(() => {
  return props.isCollapsed ? 'w-[68px]' : 'w-[268px]';
});

// Visibility transform
const visibilityClass = computed(() => {
  if (props.isOpen) return 'translate-x-0';
  return isRtl.value ? 'translate-x-full' : '-translate-x-full';
});
</script>

<template>
  <aside
    :class="[
      'nav-sidebar fixed top-0 bottom-0 flex flex-col z-40 transition-all duration-200 ease-out',
      widthClass,
      isRtl ? 'right-0' : 'left-0',
      visibilityClass,
    ]"
    :aria-label="t('common.main') || 'Navigation'"
    role="navigation"
  >
    <!-- Brand header -->
    <div class="nav-sidebar__header">
      <div class="nav-sidebar__brand">
        <div class="nav-sidebar__logo">
          <span>H</span>
        </div>
        <transition name="nav-fade">
          <span v-if="!isCollapsed" class="nav-sidebar__brand-text">HRM</span>
        </transition>
      </div>
    </div>

    <!-- Scrollable content -->
    <div ref="scrollRef" class="nav-sidebar__scroll">
      <!-- Search -->
      <div class="nav-sidebar__search">
        <NavSearch
          v-model="searchQuery"
          :is-collapsed="isCollapsed"
          :results="searchResults"
          :is-active="isSearchActive"
          @select="onSearchSelect"
          @close="closeSearch"
        />
      </div>

      <!-- Search results replace normal menu when active -->
      <template v-if="isSearchActive">
        <div class="nav-sidebar__search-results">
          <div
            v-if="searchResults.length === 0"
            class="nav-sidebar__empty"
          >
            <i class="fa-regular fa-folder-open text-mistral-muted text-[24px] mb-2" aria-hidden="true"></i>
            <p class="text-[13px] text-mistral-stone">{{ t('common.no_data') }}</p>
          </div>
        </div>
      </template>

      <!-- Normal navigation -->
      <template v-else>
        <!-- Favorites / Pinned -->
        <NavFavorites
          :items="favoriteItems"
          :is-collapsed="isCollapsed"
          :active-item-id="activeItemId"
          @toggle-favorite="toggleFavorite"
          @navigate="onNavigate"
        />

        <!-- Navigation groups -->
        <div class="nav-sidebar__groups">
          <NavGroup
            v-for="group in visibleGroups"
            :key="group.key"
            :group="group"
            :is-collapsed="isCollapsed"
            :is-expanded="isGroupExpanded(group.key)"
            :active-item-id="activeItemId"
            :favorite-ids="favorites"
            @toggle="toggleGroup(group.key)"
            @toggle-favorite="toggleFavorite"
            @navigate="onNavigate"
          />
        </div>
      </template>
    </div>

    <!-- Bottom section -->
    <div class="nav-sidebar__footer">
      <!-- Language switcher (expanded only) -->
      <div v-if="!isCollapsed" class="nav-sidebar__lang">
        <slot name="language" />
      </div>

      <!-- User -->
      <NavUser :user="user" :is-collapsed="isCollapsed" />

      <!-- Collapse toggle -->
      <button
        type="button"
        class="nav-sidebar__collapse-btn"
        :class="isRtl ? '-left-2.5' : '-right-2.5'"
        :title="isCollapsed ? t('common.expand') : t('common.collapse')"
        :aria-label="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="emit('toggle-collapse')"
      >
        <i
          :class="[
            isCollapsed
              ? (isRtl ? 'fa-solid fa-chevron-right' : 'fa-solid fa-chevron-left')
              : (isRtl ? 'fa-solid fa-chevron-left' : 'fa-solid fa-chevron-right'),
            'text-[8px]',
          ]"
          aria-hidden="true"
        ></i>
      </button>
    </div>
  </aside>
</template>
