import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import { navigationGroups, flattenNavItems, navigationModules } from '@/navigation';

const RECENT_KEY = 'hrm-nav-recent';
const FAV_NAV_KEY = 'hrm-nav-fav-pages';
const MAX_RECENT = 12;

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

export function useNavigation() {
  const page = usePage();
  const { t, isRtl } = useTranslations();

  const permissions = computed(() => page.props.auth?.permissions || []);
  const user = computed(() => page.props.auth?.user || null);

  // --- Recent Pages ---
  const recentPages = ref(loadJson(RECENT_KEY, []));

  watch(recentPages, (v) => saveJson(RECENT_KEY, v), { deep: true });

  function addToRecent(item) {
    if (!item || !item.id) return;
    const filtered = recentPages.value.filter((r) => r.id !== item.id);
    filtered.unshift({
      id: item.id,
      label: item.label,
      route: item.route,
      icon: item.icon,
      groupLabel: item.groupLabel,
      timestamp: Date.now(),
    });
    recentPages.value = filtered.slice(0, MAX_RECENT);
  }

  function removeRecent(id) {
    recentPages.value = recentPages.value.filter((r) => r.id !== id);
  }

  function clearRecent() {
    recentPages.value = [];
  }

  // --- Navigation Favorites (pages pinned from navbar) ---
  const navFavorites = ref(loadJson(FAV_NAV_KEY, []));

  watch(navFavorites, (v) => saveJson(FAV_NAV_KEY, v), { deep: true });

  function toggleNavFavorite(itemId) {
    const idx = navFavorites.value.indexOf(itemId);
    if (idx >= 0) {
      navFavorites.value = navFavorites.value.filter((id) => id !== itemId);
    } else {
      navFavorites.value = [...navFavorites.value, itemId];
    }
  }

  function isNavFavorite(itemId) {
    return navFavorites.value.includes(itemId);
  }

  // --- Route resolution (must be before computeds that call it) ---
  const routeCache = new Map();
  function resolveRoute(routeName) {
    if (routeCache.has(routeName)) return routeCache.get(routeName);
    let href = '#';
    if (typeof window !== 'undefined' && typeof window.route === 'function') {
      try {
        href = window.route(routeName);
        // Ziggy returns absolute URLs (e.g. http://host/vacations/requests);
        // keep only the path so it can be compared against page.url.
        if (typeof href === 'string' && href.startsWith('http')) {
          href = new URL(href).pathname;
        }
      } catch {
        href = '#';
      }
    }
    routeCache.set(routeName, href);
    return href;
  }

  // --- Permission check ---
  function hasPermission(...perms) {
    if (!perms.length) return true;
    if (permissions.value.length === 0) return false;
    return perms.some((p) => permissions.value.includes(p));
  }

  // --- Visible groups (filtered by permissions) ---
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

  const allVisibleItems = computed(() => flattenNavItems(visibleGroups.value));

  // --- Active route matching ---
  // The most specific (longest) URL prefix wins, so nested pages like
  // /rotations/unassigned-employees highlight their own menu item instead of
  // the first matching parent (/rotations).
  const activeRoute = computed(() => {
    const url = page.url || '/';
    return url.replace(/^\//, '').split('?')[0].replace(/\/+$/, '');
  });

  const activeItemId = computed(() => {
    const route = activeRoute.value;
    let best = null;
    let bestLength = -1;

    for (const item of allVisibleItems.value) {
      const itemUrl = resolveRoute(item.route);
      if (itemUrl === '#' || itemUrl === '/') {
        if (itemUrl === '/' && (route === '' || route === '/' || route === 'dashboard')) return item.id;
        continue;
      }

      const normalized = itemUrl.replace(/^\//, '').replace(/\/+$/, '');
      if (route === normalized) return item.id; // exact match always wins
      if (route.startsWith(normalized + '/') && normalized.length > bestLength) {
        best = item.id;
        bestLength = normalized.length;
      }
    }

    return best;
  });

  const activeItem = computed(() => {
    if (!activeItemId.value) return null;
    return allVisibleItems.value.find((i) => i.id === activeItemId.value) || null;
  });

  const activeGroup = computed(() => {
    if (!activeItem.value) return null;
    return visibleGroups.value.find((g) => g.key === activeItem.value.groupKey) || null;
  });

  // --- Breadcrumbs ---
  const breadcrumbs = computed(() => {
    const crumbs = [{ label: t('menu.dashboard'), route: 'dashboard', icon: 'fa-solid fa-gauge-high' }];

    if (!activeItem.value) return crumbs;

    const group = visibleGroups.value.find((g) =>
      g.items.some((i) => i.id === activeItem.value.id)
    );

    if (group && group.key !== 'dashboard') {
      crumbs.push({
        label: t(group.label),
        route: null,
        icon: null,
      });
    }

    if (activeItem.value.id !== 'dashboard') {
      crumbs.push({
        label: t(activeItem.value.label),
        route: activeItem.value.route,
        icon: activeItem.value.icon,
      });
    }

    return crumbs;
  });

  // --- Module switcher ---
  const visibleModules = computed(() => {
    return navigationModules.filter((mod) => {
      if (!mod.permissions || mod.permissions.length === 0) return true;
      return hasPermission(...mod.permissions);
    });
  });

  const activeModule = computed(() => {
    if (!activeItem.value) return null;
    for (const mod of navigationModules) {
      if (mod.groupKeys && mod.groupKeys.includes(activeItem.value.groupKey)) {
        return mod;
      }
    }
    return navigationModules[0];
  });

  // --- Search across all items ---
  function searchItems(query) {
    const q = query.trim().toLowerCase();
    if (!q) return [];
    return allVisibleItems.value.filter((item) => {
      const label = t(item.label).toLowerCase();
      const routeStr = item.route.toLowerCase();
      return label.includes(q) || routeStr.includes(q);
    });
  }

  // --- Track page visits ---
  function trackPageVisit() {
    if (activeItem.value) {
      addToRecent(activeItem.value);
    }
  }

  return {
    user,
    permissions,
    recentPages,
    navFavorites,
    visibleGroups,
    allVisibleItems,
    activeRoute,
    activeItemId,
    activeItem,
    activeGroup,
    breadcrumbs,
    visibleModules,
    activeModule,
    hasPermission,
    resolveRoute,
    searchItems,
    addToRecent,
    removeRecent,
    clearRecent,
    toggleNavFavorite,
    isNavFavorite,
    trackPageVisit,
  };
}
