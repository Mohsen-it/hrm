<script setup>
import { ref, computed, onMounted, onUnmounted, provide, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import { useNavigation } from '@/composables/useNavigation';
import NavSidebar from '@/Components/navigation/NavSidebar.vue';
import Navbar from '@/Components/layout/Navbar.vue';
import CommandPalette from '@/Components/navigation/CommandPalette.vue';
import SunsetStripeBand from '@/Components/layout/SunsetStripeBand.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import { useRealtimeAttendance } from '@/composables/useRealtimeAttendance';
import { usePageTitle, resetPageTitle } from '@/composables/usePageTitle';

// With the persistent-layout pattern Inertia passes every page prop down to
// this layout, so don't let them fall through onto the root element.
defineOptions({ inheritAttrs: false });

const title = usePageTitle();

const page = usePage();
const { direction, isRtl } = useTranslations();

provide('dir', direction);

const {
    breadcrumbs,
    visibleModules,
    activeModule,
    allVisibleItems,
    recentPages,
    navFavorites,
    activeItemId,
    trackPageVisit,
} = useNavigation();

const isMobile = ref(false);
const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(false);
const isCommandPaletteOpen = ref(false);
const livePunchNotification = ref(null);
let livePunchNotificationTimer = null;

useRealtimeAttendance({
    onPunch: (punch) => {
        livePunchNotification.value = punch;
        window.EventBus?.emit('attendance:punch-received', punch);

        if (livePunchNotificationTimer) {
            window.clearTimeout(livePunchNotificationTimer);
        }

        livePunchNotificationTimer = window.setTimeout(() => {
            livePunchNotification.value = null;
        }, 4000);
    },
});

function updateIsMobile() {
    isMobile.value = window.innerWidth < 768;
}

onMounted(() => {
    updateIsMobile();
    window.addEventListener('resize', updateIsMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateIsMobile);
    if (livePunchNotificationTimer) {
        window.clearTimeout(livePunchNotificationTimer);
    }
});

const mainPadding = computed(() => {
    if (isMobile.value) {
        return isRtl.value ? 'mr-0' : 'ml-0';
    }
    if (isRtl.value) {
        return isSidebarCollapsed.value ? 'md:mr-[68px]' : 'md:mr-[268px]';
    }
    return isSidebarCollapsed.value ? 'md:ml-[68px]' : 'md:ml-[268px]';
});

// Keep the notification away from the fixed sidebar on both RTL and LTR layouts.
const livePunchNotificationPosition = computed(() => (isRtl.value ? 'start-4' : 'end-4'));

function closeMobileSidebar() {
    isSidebarOpen.value = false;
}

function toggleMobileSidebar() {
    isSidebarOpen.value = !isSidebarOpen.value;
}

function toggleSidebarCollapse() {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
}

function openCommandPalette() {
    isCommandPaletteOpen.value = true;
}

function closeCommandPalette() {
    isCommandPaletteOpen.value = false;
}

// Track page visits for recent pages; also reset the shared page title so
// pages without a title don't inherit the previous page's one.
watch(() => page.url, () => {
    resetPageTitle();
    trackPageVisit();
}, { immediate: true });
</script>

<template>
    <div :dir="direction" class="min-h-screen bg-mistral-surface">
        <!-- Mobile backdrop -->
        <div
            v-if="isMobile && isSidebarOpen"
            class="fixed inset-0 bg-mistral-ink/40 backdrop-blur-sm z-35 md:hidden"
            @click="closeMobileSidebar"
        ></div>

        <!-- Navigation Sidebar -->
        <NavSidebar
            :is-open="!isMobile || isSidebarOpen"
            :is-collapsed="isSidebarCollapsed && !isMobile"
            @close="closeMobileSidebar"
            @toggle-collapse="toggleSidebarCollapse"
        >
            <template #language>
                <LanguageSwitcher :dir="isRtl ? 'rtl' : 'ltr'" />
            </template>
        </NavSidebar>

        <!-- Command Palette -->
        <CommandPalette
            :is-open="isCommandPaletteOpen"
            :navigation-items="allVisibleItems"
            :recent-pages="recentPages"
            :active-item-id="activeItemId"
            @close="closeCommandPalette"
            @open="openCommandPalette"
        />

        <!-- Main content area -->
        <div
            class="transition-all duration-200 ease-out min-h-screen"
            :class="mainPadding"
        >
            <Navbar
                :title="title"
                :show-mobile-toggle="isMobile"
                :breadcrumbs="breadcrumbs"
                :active-module="activeModule"
                :modules="visibleModules"
                :recent-pages="recentPages"
                :nav-favorites="navFavorites"
                :all-items="allVisibleItems"
                @toggle-mobile-sidebar="toggleMobileSidebar"
                @open-command-palette="openCommandPalette"
            />

            <!-- Flash messages are rendered by each page via the <Alert> component
                 (single source of truth) to avoid duplicate banners. -->

            <!-- Page content -->
            <main class="p-4 md:p-6 pb-10">
                <slot />
            </main>
        </div>

        <!-- Brand signature: sunset stripe band -->
        <SunsetStripeBand :dir="direction" />

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="-translate-y-2 opacity-0"
        >
            <div
                v-if="livePunchNotification"
                class="fixed top-[4.5rem] sm:top-20 z-50 flex w-[calc(100vw-2rem)] max-w-[320px] items-center gap-3 rounded-xl border border-mistral-primary/25 bg-mistral-canvas/95 px-3.5 py-3 text-start shadow-[0_8px_24px_rgba(0,0,0,0.10)] backdrop-blur-sm"
                :class="livePunchNotificationPosition"
                role="status"
                aria-live="polite"
                aria-atomic="true"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-mistral-primary/10 text-mistral-primary" aria-hidden="true">
                    <i class="fas fa-fingerprint" aria-hidden="true"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[13px] font-semibold text-mistral-ink">
                        {{ livePunchNotification.user?.name || livePunchNotification.user?.employee_code || 'موظف' }}
                    </span>
                    <span class="block text-[11px] text-mistral-steel">
                        {{ livePunchNotification.punch_type === 'check_out' ? 'تم تسجيل انصراف' : 'تم تسجيل حضور' }}
                        <span v-if="livePunchNotification.device?.name" class="text-mistral-stone"> · {{ livePunchNotification.device.name }}</span>
                    </span>
                </span>
                <IconButton
                    icon="fas fa-xmark"
                    aria-label="إخفاء إشعار تسجيل البصمة"
                    variant="ghost"
                    size="sm"
                    @click="livePunchNotification = null"
                />
            </div>
        </Transition>
    </div>
</template>
