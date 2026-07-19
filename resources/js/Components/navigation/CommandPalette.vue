<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
  isOpen: { type: Boolean, default: false },
  navigationItems: { type: Array, default: () => [] },
  recentPages: { type: Array, default: () => [] },
  activeItemId: { type: String, default: null },
});

const emit = defineEmits(['close', 'navigate', 'open']);

const { t, isRtl } = useTranslations();

const query = ref('');
const inputRef = ref(null);
const selectedIndex = ref(0);
const scrollContainerRef = ref(null);

const QUICK_ACTIONS = [
  { id: 'qa-dashboard', label: 'menu.dashboard', icon: 'fa-solid fa-gauge-high', route: 'dashboard', type: 'action' },
  { id: 'qa-live', label: 'menu.attendance_live', icon: 'fa-solid fa-satellite-dish', route: 'attendance.live.index', type: 'action' },
  { id: 'qa-users', label: 'menu.users', icon: 'fa-solid fa-users', route: 'users.index', type: 'action' },
  { id: 'qa-vacations', label: 'menu.vacation_requests', icon: 'fa-solid fa-inbox', route: 'vacations.requests.index', type: 'action' },
  { id: 'qa-reports', label: 'menu.attendance_reports', icon: 'fa-solid fa-chart-line', route: 'attendance.reports.index', type: 'action' },
  { id: 'qa-devices', label: 'menu.devices', icon: 'fa-solid fa-microchip', route: 'fingerprint-devices.index', type: 'action' },
  { id: 'qa-holidays', label: 'menu.holidays', icon: 'fa-solid fa-umbrella-beach', route: 'holidays.index', type: 'action' },
  { id: 'qa-departments', label: 'menu.departments', icon: 'fa-solid fa-sitemap', route: 'departments.index', type: 'action' },
];

const SYSTEM_ACTIONS = [
  { id: 'sa-refresh', label: 'Refresh page', icon: 'fa-solid fa-sync-alt', shortcut: 'Ctrl+R', type: 'system', action: 'refresh' },
  { id: 'sa-print', label: 'Print page', icon: 'fa-solid fa-print', shortcut: 'Ctrl+P', type: 'system', action: 'print' },
  { id: 'sa-home', label: 'Go to Dashboard', icon: 'fa-solid fa-house', shortcut: 'Alt+H', type: 'system', route: 'dashboard' },
];

const filteredResults = computed(() => {
  const q = query.value.trim().toLowerCase();

  if (!q) {
    const recent = props.recentPages.slice(0, 5).map((r) => ({
      ...r,
      type: 'recent',
    }));
    const quick = QUICK_ACTIONS.map((a) => ({
      ...a,
      label: t(a.label),
      type: 'quick-action',
    }));
    return [
      ...(recent.length > 0 ? [{ heading: t('common.recent') || 'Recent', items: recent }] : []),
      { heading: t('common.quick_actions') || 'Quick Actions', items: quick },
    ];
  }

  const matched = props.navigationItems
    .filter((item) => {
      const label = t(item.label).toLowerCase();
      const routeStr = item.route.toLowerCase();
      return label.includes(q) || routeStr.includes(q);
    })
    .slice(0, 10)
    .map((item) => ({ ...item, type: 'navigation' }));

  const matchedActions = SYSTEM_ACTIONS.filter((a) =>
    a.label.toLowerCase().includes(q)
  ).map((a) => ({ ...a, type: 'system' }));

  const groups = [];
  if (matched.length > 0) {
    groups.push({ heading: t('common.navigation') || 'Navigation', items: matched });
  }
  if (matchedActions.length > 0) {
    groups.push({ heading: t('common.actions') || 'Actions', items: matchedActions });
  }
  if (groups.length === 0 && q) {
    groups.push({ heading: t('common.no_results') || 'No results', items: [] });
  }
  return groups;
});

const flatResults = computed(() => {
  return filteredResults.value.flatMap((g) => g.items);
});

function close() {
  query.value = '';
  selectedIndex.value = 0;
  emit('close');
}

function navigateTo(item) {
  if (!item) return;

  if (item.type === 'system' && item.action) {
    if (item.action === 'refresh') {
      router.reload();
    } else if (item.action === 'print') {
      window.print();
    }
    close();
    return;
  }

  const href = resolveRoute(item.route);
  if (href && href !== '#') {
    emit('navigate', item);
    window.location.href = href;
  }
  close();
}

function resolveRoute(routeName) {
  if (typeof window !== 'undefined' && typeof window.route === 'function') {
    try {
      return window.route(routeName);
    } catch {
      return '#';
    }
  }
  return '#';
}

function onKeydown(e) {
  const total = flatResults.value.length;
  if (!total) return;

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex.value = (selectedIndex.value + 1) % total;
    scrollToSelected();
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex.value = (selectedIndex.value - 1 + total) % total;
    scrollToSelected();
  } else if (e.key === 'Enter') {
    e.preventDefault();
    navigateTo(flatResults.value[selectedIndex.value]);
  } else if (e.key === 'Escape') {
    close();
  }
}

function scrollToSelected() {
  nextTick(() => {
    const el = scrollContainerRef.value?.querySelector('.cp-item--selected');
    if (el) {
      el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  });
}

function highlightMatch(text, q) {
  if (!q) return text;
  const idx = text.toLowerCase().indexOf(q.toLowerCase());
  if (idx === -1) return text;
  const before = text.slice(0, idx);
  const match = text.slice(idx, idx + q.length);
  const after = text.slice(idx + q.length);
  return `${before}<mark class="cp-highlight">${match}</mark>${after}`;
}

watch(() => props.isOpen, (open) => {
  if (open) {
    query.value = '';
    selectedIndex.value = 0;
    nextTick(() => inputRef.value?.focus());
  }
});

watch(query, () => {
  selectedIndex.value = 0;
});

let globalKeydownHandler;
onMounted(() => {
  globalKeydownHandler = (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      if (props.isOpen) {
        close();
      } else {
        emit('open');
      }
    }
  };
  document.addEventListener('keydown', globalKeydownHandler);
});

onUnmounted(() => {
  document.removeEventListener('keydown', globalKeydownHandler);
});

let flatIndex = 0;
</script>

<template>
  <Teleport to="body">
    <Transition name="cp-overlay">
      <div
        v-if="isOpen"
        class="cp-overlay"
        @click.self="close"
      >
        <Transition name="cp-panel">
          <div
            v-if="isOpen"
            class="cp-panel"
            :class="isRtl ? 'cp-panel--rtl' : 'cp-panel--ltr'"
            role="dialog"
            aria-label="Command palette"
            @keydown="onKeydown"
          >
            <!-- Search input -->
            <div class="cp-input-wrapper">
              <i class="fas fa-search cp-input-icon" aria-hidden="true"></i>
              <input
                ref="inputRef"
                v-model="query"
                type="text"
                class="cp-input"
                :placeholder="t('common.search_navigation') || 'Search pages, actions...'"
                autocomplete="off"
                spellcheck="false"
              />
              <div class="cp-input-hints">
                <kbd class="cp-kbd">ESC</kbd>
              </div>
            </div>

            <!-- Results -->
            <div ref="scrollContainerRef" class="cp-results">
              <template v-for="(group, gIdx) in filteredResults" :key="gIdx">
                <div v-if="group.items.length > 0" class="cp-group">
                  <div class="cp-group-heading">{{ group.heading }}</div>
                  <template v-for="(item, iIdx) in group.items" :key="item.id">
                    <div
                      :class="[
                        'cp-item',
                        { 'cp-item--selected': flatResults.indexOf(item) === selectedIndex },
                        { 'cp-item--active': item.id === activeItemId },
                      ]"
                      role="option"
                      :aria-selected="flatResults.indexOf(item) === selectedIndex"
                      @click="navigateTo(item)"
                      @mouseenter="selectedIndex = flatResults.indexOf(item)"
                    >
                      <div class="cp-item-icon">
                        <i :class="item.icon" aria-hidden="true"></i>
                      </div>
                      <div class="cp-item-content">
                        <span
                          class="cp-item-label"
                          v-html="highlightMatch(t(item.label) || item.label, query)"
                        />
                        <span v-if="item.groupLabel && !query" class="cp-item-meta">
                          {{ t(item.groupLabel) }}
                        </span>
                      </div>
                      <div v-if="item.type === 'recent'" class="cp-item-badge">
                        <i class="fa-regular fa-clock text-[10px]" aria-hidden="true"></i>
                      </div>
                      <div v-if="item.shortcut" class="cp-item-shortcut">
                        <kbd class="cp-kbd cp-kbd--sm">{{ item.shortcut }}</kbd>
                      </div>
                      <div v-if="item.type === 'navigation'" class="cp-item-action">
                        <i class="fas fa-arrow-right text-[10px] text-mistral-muted" aria-hidden="true"></i>
                      </div>
                    </div>
                  </template>
                </div>
              </template>
            </div>

            <!-- Footer -->
            <div class="cp-footer">
              <div class="cp-footer-hints">
                <span class="cp-footer-hint">
                  <kbd class="cp-kbd cp-kbd--xs">↑↓</kbd>
                  <span>Navigate</span>
                </span>
                <span class="cp-footer-hint">
                  <kbd class="cp-kbd cp-kbd--xs">↵</kbd>
                  <span>Open</span>
                </span>
                <span class="cp-footer-hint">
                  <kbd class="cp-kbd cp-kbd--xs">ESC</kbd>
                  <span>Close</span>
                </span>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
