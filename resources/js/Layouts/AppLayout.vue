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

defineProps({
    title: { type: String, default: '' },
});

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

function updateIsMobile() {
    isMobile.value = window.innerWidth < 768;
}

onMounted(() => {
    updateIsMobile();
    window.addEventListener('resize', updateIsMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateIsMobile);
});

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const mainPadding = computed(() => {
    if (isMobile.value) {
        return isRtl.value ? 'mr-0' : 'ml-0';
    }
    if (isRtl.value) {
        return isSidebarCollapsed.value ? 'md:mr-[68px]' : 'md:mr-[268px]';
    }
    return isSidebarCollapsed.value ? 'md:ml-[68px]' : 'md:ml-[268px]';
});

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

// Track page visits for recent pages
watch(() => page.url, () => {
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

            <!-- Flash messages -->
            <div v-if="flashSuccess || flashError" class="px-4 md:px-6 pt-4 space-y-2">
                <div v-if="flashSuccess" class="flex items-center gap-2.5 px-4 py-3 rounded-lg bg-mistral-success/8 border border-mistral-success/30 text-mistral-success text-[13px] font-medium">
                    <i class="fas fa-circle-check text-[14px]" aria-hidden="true"></i>
                    <span>{{ flashSuccess }}</span>
                </div>
                <div v-if="flashError" class="flex items-center gap-2.5 px-4 py-3 rounded-lg bg-mistral-danger/8 border border-mistral-danger/30 text-mistral-danger text-[13px] font-medium">
                    <i class="fas fa-circle-exclamation text-[14px]" aria-hidden="true"></i>
                    <span>{{ flashError }}</span>
                </div>
            </div>

            <!-- Page content -->
            <main class="p-4 md:p-6 pb-10">
                <slot />
            </main>
        </div>

        <!-- Brand signature: sunset stripe band -->
        <SunsetStripeBand :dir="direction" />
    </div>
</template>
