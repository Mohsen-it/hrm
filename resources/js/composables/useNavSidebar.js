import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import { navigationGroups, flattenNavItems } from '@/navigation';

const FAVORITES_KEY = 'hrm-nav-favorites';
const COLLAPSED_KEY = 'hrm-nav-collapsed';
const EXPANDED_GROUPS_KEY = 'hrm-nav-expanded-groups';

function loadJson(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch {
    return fallback;
  }
}

function saveJson(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {}
}

export function useNavSidebar() {
  const page = usePage();
  const { t } = useTranslations();

  const permissions = computed(
    () => page.props.auth?.permissions || [],
  );

  const user = computed(() => page.props.auth?.user || null);

  const isCollapsed = ref(loadJson(COLLAPSED_KEY, false));
  const isMobileOpen = ref(false);
  const searchQuery = ref('');
  const favorites = ref(loadJson(FAVORITES_KEY, []));
  const expandedGroups = ref(loadJson(EXPANDED_GROUPS_KEY, []));

  // Persist state
  watch(isCollapsed, (v) => saveJson(COLLAPSED_KEY, v));
  watch(favorites, (v) => saveJson(FAVORITES_KEY, v), { deep: true });
  watch(expandedGroups, (v) => saveJson(EXPANDED_GROUPS_KEY, v), { deep: true });

  // Route resolution with caching (must be declared before computed properties that use it)
  const routeCache = new Map();
  function resolveRoute(routeName) {
    if (routeCache.has(routeName)) return routeCache.get(routeName);
    let href = '#';
    if (typeof window !== 'undefined' && typeof window.route === 'function') {
      try {
        href = window.route(routeName);
      } catch {
        href = '#';
      }
    }
    routeCache.set(routeName, href);
    return href;
  }

  // Permission check
  function hasPermission(...perms) {
    if (!perms.length) return true;
    if (permissions.value.length === 0) return true;
    return perms.some((p) => permissions.value.includes(p));
  }

  // Filter groups by permissions
  const visibleGroups = computed(() => {
    return navigationGroups
      .map((group) => {
        const visibleItems = group.items.filter((item) => {
          if (!item.permissions || item.permissions.length === 0) return true;
          return hasPermission(...item.permissions);
        });
        return visibleItems.length > 0 ? { ...group, items: visibleItems } : null;
      })
      .filter(Boolean);
  });

  // All visible flat items
  const allVisibleItems = computed(() => flattenNavItems(visibleGroups.value));

  // Favorites with resolved item data
  const favoriteItems = computed(() => {
    return favorites.value
      .map((favId) => allVisibleItems.value.find((item) => item.id === favId))
      .filter(Boolean);
  });

  // Active route matching
  const activeRoute = computed(() => {
    const url = page.url || '/';
    return url.replace(/^\//, '').split('?')[0];
  });

  const activeItemId = computed(() => {
    const route = activeRoute.value;
    for (const item of allVisibleItems.value) {
      const itemUrl = resolveRoute(item.route);
      if (itemUrl === '/' && (route === '/' || route === 'dashboard')) return item.id;
      if (itemUrl && route.startsWith(itemUrl.replace(/^\//, ''))) return item.id;
    }
    return null;
  });

  const activeGroupKey = computed(() => {
    const id = activeItemId.value;
    if (!id) return null;
    const item = allVisibleItems.value.find((i) => i.id === id);
    return item?.groupKey || null;
  });

  // Auto-expand active group
  watch(activeGroupKey, (key) => {
    if (key && !expandedGroups.value.includes(key)) {
      expandedGroups.value = [...expandedGroups.value, key];
    }
  }, { immediate: true });

  // Search
  const searchResults = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return [];
    return allVisibleItems.value.filter((item) => {
      const label = t(item.label).toLowerCase();
      const routeStr = item.route.toLowerCase();
      return label.includes(q) || routeStr.includes(q);
    });
  });

  const isSearchActive = computed(() => searchQuery.value.trim().length > 0);

  // Toggle group expansion
  function toggleGroup(key) {
    const idx = expandedGroups.value.indexOf(key);
    if (idx >= 0) {
      expandedGroups.value = expandedGroups.value.filter((k) => k !== key);
    } else {
      expandedGroups.value = [...expandedGroups.value, key];
    }
  }

  function isGroupExpanded(key) {
    return expandedGroups.value.includes(key);
  }

  // Favorites
  function toggleFavorite(itemId) {
    const idx = favorites.value.indexOf(itemId);
    if (idx >= 0) {
      favorites.value = favorites.value.filter((id) => id !== itemId);
    } else {
      favorites.value = [...favorites.value, itemId];
    }
  }

  function isFavorite(itemId) {
    return favorites.value.includes(itemId);
  }

  // Collapse
  function toggleCollapse() {
    isCollapsed.value = !isCollapsed.value;
  }

  // Mobile
  function openMobile() {
    isMobileOpen.value = true;
  }

  function closeMobile() {
    isMobileOpen.value = false;
  }

  function toggleMobile() {
    isMobileOpen.value = !isMobileOpen.value;
  }

  return {
    permissions,
    user,
    isCollapsed,
    isMobileOpen,
    searchQuery,
    favorites,
    expandedGroups,
    visibleGroups,
    allVisibleItems,
    favoriteItems,
    activeRoute,
    activeItemId,
    activeGroupKey,
    searchResults,
    isSearchActive,
    hasPermission,
    toggleGroup,
    isGroupExpanded,
    toggleFavorite,
    isFavorite,
    toggleCollapse,
    openMobile,
    closeMobile,
    toggleMobile,
    resolveRoute,
  };
}
