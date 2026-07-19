<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';

const props = defineProps({
  title: { type: String, default: '' },
  showMobileToggle: { type: Boolean, default: false },
  breadcrumbs: { type: Array, default: () => [] },
  activeModule: { type: Object, default: null },
  modules: { type: Array, default: () => [] },
  recentPages: { type: Array, default: () => [] },
  navFavorites: { type: Array, default: () => [] },
  allItems: { type: Array, default: () => [] },
});

const emit = defineEmits([
  'toggle-mobile-sidebar',
  'open-command-palette',
  'toggle-recent',
  'toggle-quick-actions',
]);

const { t, isRtl } = useTranslations();

const showModuleSwitcher = ref(false);
const showRecentDropdown = ref(false);
const showQuickActions = ref(false);

function toggleModuleSwitcher() {
  showModuleSwitcher.value = !showModuleSwitcher.value;
  showRecentDropdown.value = false;
  showQuickActions.value = false;
}

function toggleRecent() {
  showRecentDropdown.value = !showRecentDropdown.value;
  showModuleSwitcher.value = false;
  showQuickActions.value = false;
}

function toggleQuickActions() {
  showQuickActions.value = !showQuickActions.value;
  showModuleSwitcher.value = false;
  showRecentDropdown.value = false;
}

function closeAllDropdowns() {
  showModuleSwitcher.value = false;
  showRecentDropdown.value = false;
  showQuickActions.value = false;
}

function navigateTo(routeName) {
  const href = resolveRoute(routeName);
  if (href && href !== '#') {
    window.location.href = href;
  }
  closeAllDropdowns();
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

function refreshPage() {
  router.reload();
  closeAllDropdowns();
}

function printPage() {
  window.print();
  closeAllDropdowns();
}

function formatTimeAgo(timestamp) {
  if (!timestamp) return '';
  const diff = Date.now() - timestamp;
  const minutes = Math.floor(diff / 60000);
  if (minutes < 1) return 'Just now';
  if (minutes < 60) return `${minutes}m ago`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

const breadcrumbItems = computed(() => {
  return props.breadcrumbs.map((crumb, idx) => {
    const isLast = idx === props.breadcrumbs.length - 1;
    if (isLast || !crumb.route) {
      return { label: crumb.label };
    }
    return { label: crumb.label, href: resolveRoute(crumb.route) };
  });
});

function onClickOutside(e) {
  if (!e.target.closest('.navbar-dropdown')) {
    closeAllDropdowns();
  }
}

onMounted(() => {
  document.addEventListener('click', onClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', onClickOutside);
});
</script>

<template>
  <header class="navbar sticky top-0 z-30">
    <div class="navbar__inner">
      <!-- Left section -->
      <div class="navbar__left">
        <!-- Mobile toggle -->
        <button
          v-if="showMobileToggle"
          type="button"
          class="navbar__mobile-toggle md:hidden"
          :aria-label="t('common.main') || 'Open menu'"
          @click="emit('toggle-mobile-sidebar')"
        >
          <i class="fas fa-bars text-[16px]" aria-hidden="true"></i>
        </button>

        <!-- Dashboard home button (persistent) -->
        <Link
          :href="resolveRoute('dashboard')"
          class="navbar__home"
          :title="t('menu.dashboard')"
        >
          <i class="fas fa-gauge-high text-[14px]" aria-hidden="true"></i>
        </Link>

        <!-- Module switcher -->
        <div class="navbar-dropdown" v-if="activeModule">
          <button
            type="button"
            class="navbar__module-switcher"
            @click.stop="toggleModuleSwitcher"
            :aria-expanded="showModuleSwitcher"
            aria-haspopup="listbox"
          >
            <div :class="['navbar__module-icon', activeModule.color]">
              <i :class="activeModule.icon" aria-hidden="true"></i>
            </div>
            <span class="navbar__module-label">{{ t(activeModule.label) }}</span>
            <i
              :class="[
                'fas fa-chevron-down text-[9px] text-mistral-muted transition-transform duration-150',
                { 'rotate-180': showModuleSwitcher },
              ]"
              aria-hidden="true"
            ></i>
          </button>

          <Transition name="dropdown">
            <div
              v-if="showModuleSwitcher"
              class="navbar-dropdown__menu navbar-dropdown__menu--modules"
              role="listbox"
            >
              <button
                v-for="mod in modules"
                :key="mod.id"
                :class="[
                  'navbar-dropdown__item',
                  { 'navbar-dropdown__item--active': mod.id === activeModule.id },
                ]"
                role="option"
                :aria-selected="mod.id === activeModule.id"
                @click.stop="navigateTo(mod.route)"
              >
                <div :class="['navbar__module-icon navbar__module-icon--sm', mod.color]">
                  <i :class="mod.icon" aria-hidden="true"></i>
                </div>
                <span class="navbar-dropdown__label">{{ t(mod.label) }}</span>
                <i
                  v-if="mod.id === activeModule.id"
                  class="fas fa-check text-[10px] text-mistral-primary ms-auto"
                  aria-hidden="true"
                ></i>
              </button>
            </div>
          </Transition>
        </div>

        <!-- Separator -->
        <div class="navbar__separator hidden sm:block"></div>

        <!-- Breadcrumbs -->
        <nav
          v-if="breadcrumbItems.length > 1"
          :aria-label="isRtl ? 'مسار التنقل' : 'Breadcrumb'"
          class="navbar__breadcrumbs hidden sm:block"
        >
          <ol class="navbar__breadcrumb-list">
            <li
              v-for="(item, index) in breadcrumbItems"
              :key="index"
              class="navbar__breadcrumb-item"
            >
              <a
                v-if="item.href"
                :href="item.href"
                class="navbar__breadcrumb-link"
              >
                {{ item.label }}
              </a>
              <span
                v-else
                :class="[
                  'navbar__breadcrumb-text',
                  { 'navbar__breadcrumb-text--current': index === breadcrumbItems.length - 1 },
                ]"
                :aria-current="index === breadcrumbItems.length - 1 ? 'page' : undefined"
              >
                {{ item.label }}
              </span>
              <i
                v-if="index < breadcrumbItems.length - 1"
                class="fas fa-chevron-right text-[8px] text-mistral-muted mx-1.5 rtl-flip"
                aria-hidden="true"
              ></i>
            </li>
          </ol>
        </nav>
      </div>

      <!-- Right section -->
      <div class="navbar__right">
        <!-- Search trigger (Ctrl+K) -->
        <button
          type="button"
          class="navbar__search-trigger"
          @click.stop="emit('open-command-palette')"
          :title="t('common.search') + ' (Ctrl+K)'"
        >
          <i class="fas fa-search text-[13px] text-mistral-steel" aria-hidden="true"></i>
          <span class="navbar__search-label hidden lg:inline">{{ t('common.search') }}</span>
          <kbd class="navbar__kbd hidden lg:inline">Ctrl+K</kbd>
        </button>

        <!-- Quick actions -->
        <div class="navbar-dropdown hidden sm:block">
          <button
            type="button"
            class="navbar__icon-btn"
            :title="t('common.quick_actions') || 'Quick actions'"
            :aria-label="t('common.quick_actions') || 'Quick actions'"
            :aria-expanded="showQuickActions"
            @click.stop="toggleQuickActions"
          >
            <i class="fas fa-bolt text-[15px]" aria-hidden="true"></i>
          </button>

          <Transition name="dropdown">
            <div
              v-if="showQuickActions"
              class="navbar-dropdown__menu navbar-dropdown__menu--actions"
            >
              <div class="navbar-dropdown__heading">{{ t('common.quick_actions') || 'Quick Actions' }}</div>
              <button
                class="navbar-dropdown__item"
                @click.stop="navigateTo('attendance.live.index')"
              >
                <i class="fas fa-satellite-dish text-mistral-success text-[13px]" aria-hidden="true"></i>
                <span class="navbar-dropdown__label">{{ t('menu.attendance_live') }}</span>
              </button>
              <button
                class="navbar-dropdown__item"
                @click.stop="navigateTo('users.index')"
              >
                <i class="fas fa-users text-blue-500 text-[13px]" aria-hidden="true"></i>
                <span class="navbar-dropdown__label">{{ t('menu.users') }}</span>
              </button>
              <button
                class="navbar-dropdown__item"
                @click.stop="navigateTo('vacations.requests.index')"
              >
                <i class="fas fa-inbox text-mistral-warning text-[13px]" aria-hidden="true"></i>
                <span class="navbar-dropdown__label">{{ t('menu.vacation_requests') }}</span>
              </button>
              <button
                class="navbar-dropdown__item"
                @click.stop="navigateTo('attendance.reports.index')"
              >
                <i class="fas fa-chart-line text-mistral-info text-[13px]" aria-hidden="true"></i>
                <span class="navbar-dropdown__label">{{ t('menu.attendance_reports') }}</span>
              </button>
              <div class="navbar-dropdown__divider"></div>
              <button
                class="navbar-dropdown__item"
                @click.stop="refreshPage"
              >
                <i class="fas fa-sync-alt text-mistral-steel text-[13px]" aria-hidden="true"></i>
                <span class="navbar-dropdown__label">{{ t('common.refresh') }}</span>
                <kbd class="navbar__kbd navbar__kbd--sm ms-auto">Ctrl+R</kbd>
              </button>
              <button
                class="navbar-dropdown__item"
                @click.stop="printPage"
              >
                <i class="fas fa-print text-mistral-steel text-[13px]" aria-hidden="true"></i>
                <span class="navbar-dropdown__label">Print</span>
                <kbd class="navbar__kbd navbar__kbd--sm ms-auto">Ctrl+P</kbd>
              </button>
            </div>
          </Transition>
        </div>

        <!-- Separator -->
        <div class="navbar__separator"></div>

        <!-- Notifications -->
        <button
          type="button"
          class="navbar__icon-btn relative"
          :title="t('common.notifications') || 'Notifications'"
          :aria-label="t('common.notifications') || 'Notifications'"
        >
          <i class="fas fa-bell text-[15px]" aria-hidden="true"></i>
          <span class="navbar__notification-dot"></span>
        </button>

        <!-- User menu placeholder (keeps existing logout) -->
        <div class="w-px h-5 bg-mistral-hairline mx-1 hidden sm:block"></div>
        <Link
          :href="route('logout')"
          method="post"
          as="button"
          class="navbar__icon-btn"
          :title="t('common.logout')"
          :aria-label="t('common.logout')"
        >
          <i class="fas fa-right-from-bracket rtl-flip text-[15px]" aria-hidden="true"></i>
        </Link>
      </div>
    </div>
  </header>
</template>
