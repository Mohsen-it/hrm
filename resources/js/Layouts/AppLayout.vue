<script setup>
import { ref, computed, onMounted, onUnmounted, provide } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';
import Sidebar from '@/Components/layout/Sidebar.vue';
import Navbar from '@/Components/layout/Navbar.vue';
import SunsetStripeBand from '@/Components/layout/SunsetStripeBand.vue';

defineProps({
    title: { type: String, default: '' },
});

const page = usePage();
const { direction, isRtl } = useTranslations();

provide('dir', direction);

const isMobile = ref(false);
const isSidebarOpen = ref(false);
const isSidebarCollapsed = ref(false);

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
        return isSidebarCollapsed.value ? 'md:mr-16' : 'md:mr-[260px]';
    }
    return isSidebarCollapsed.value ? 'md:ml-16' : 'md:ml-[260px]';
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
</script>

<template>
    <div :dir="direction" class="min-h-screen bg-mistral-canvas">
        <!-- Mobile backdrop -->
        <div
            v-if="isMobile && isSidebarOpen"
            class="sidebar-backdrop md:hidden"
            @click="closeMobileSidebar"
        ></div>

        <!-- Sidebar -->
        <Sidebar
            :is-open="!isMobile || isSidebarOpen"
            :is-collapsed="isSidebarCollapsed && !isMobile"
            @close="closeMobileSidebar"
            @toggle-collapse="toggleSidebarCollapse"
        />

        <!-- Main content area -->
        <div
            class="transition-all duration-200"
            :class="mainPadding"
        >
            <Navbar :title="title" :show-mobile-toggle="isMobile" @toggle-mobile-sidebar="toggleMobileSidebar" />

            <!-- Flash messages -->
            <div v-if="flashSuccess || flashError" class="px-4 md:px-6 pt-4">
                <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    <span>{{ flashSuccess }}</span>
                </div>
                <div v-if="flashError" class="alert alert-danger flex items-center gap-2">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <span>{{ flashError }}</span>
                </div>
            </div>

            <!-- Page content -->
            <main class="p-4 md:p-6 pb-8">
                <slot />
            </main>
        </div>

        <!-- Brand signature: sunset stripe band -->
        <SunsetStripeBand :dir="direction" />
    </div>
</template>
