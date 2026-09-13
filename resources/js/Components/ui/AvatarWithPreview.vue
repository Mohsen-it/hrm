<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import Avatar from './Avatar.vue';

const props = defineProps({
    name: { type: String, default: '' },
    src: { type: String, default: null },
    employeeCode: { type: [String, Number], default: null },
    size: { type: String, default: 'sm' },
    dir: { type: String, default: 'rtl' },
    // When set, the avatar becomes a link (e.g. to the employee profile).
    href: { type: String, default: null },
    // Delay before the preview appears (avoids flicker when the mouse
    // just passes over a list).
    openDelay: { type: Number, default: 200 },
});

// Approximate preview card size (image 192x256 + name + code + padding).
const CARD_W = 208;
const CARD_H = 336;

// The preview is teleported to <body> with fixed positioning, so it is
// never clipped by table scroll containers and never covers the hovered
// row — it docks beside the avatar on the roomier side of the screen.
// The large image mounts ONLY after the first hover: zero extra requests
// while browsing lists.
const isOpen = ref(false);
const previewMounted = ref(false);
const previewFailed = ref(false);
const triggerEl = ref(null);
const anchor = ref({ x: 0, y: 0, side: 'right' });
let openTimer = null;
let closeTimer = null;

function clearTimers() {
    if (openTimer) { clearTimeout(openTimer); openTimer = null; }
    if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
}

function place() {
    const el = triggerEl.value;
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const gap = 12;

    const spaceRight = vw - rect.right;
    const spaceLeft = rect.left;

    // Dock on the roomier side; fall back to clamping inside the viewport.
    let side = spaceRight >= spaceLeft ? 'right' : 'left';
    if (side === 'right' && spaceRight < CARD_W + gap && spaceLeft >= CARD_W + gap) side = 'left';
    if (side === 'left' && spaceLeft < CARD_W + gap && spaceRight >= CARD_W + gap) side = 'right';

    let x = side === 'right' ? rect.right + gap : rect.left - CARD_W - gap;
    x = Math.min(Math.max(8, x), Math.max(8, vw - CARD_W - 8));

    let y = rect.top + rect.height / 2 - CARD_H / 2;
    y = Math.min(Math.max(8, y), Math.max(8, vh - CARD_H - 8));

    anchor.value = { x: Math.round(x), y: Math.round(y), side };
}

function open() {
    previewMounted.value = true;
    place();
    isOpen.value = true;
}

function scheduleOpen(immediate = false) {
    clearTimers();
    if (immediate) {
        open();
        return;
    }
    openTimer = setTimeout(open, props.openDelay);
}

function scheduleClose() {
    clearTimers();
    closeTimer = setTimeout(() => { isOpen.value = false; }, 100);
}

function closeOnScroll() {
    if (isOpen.value) isOpen.value = false;
}

onMounted(() => {
    window.addEventListener('scroll', closeOnScroll, true);
    window.addEventListener('resize', closeOnScroll);
});

onUnmounted(() => {
    clearTimers();
    window.removeEventListener('scroll', closeOnScroll, true);
    window.removeEventListener('resize', closeOnScroll);
});
</script>

<template>
    <component
        :is="href ? 'a' : 'span'"
        ref="triggerEl"
        :href="href || undefined"
        class="relative inline-flex shrink-0"
        :class="{ 'cursor-pointer': !!href }"
        :tabindex="href ? undefined : 0"
        @mouseenter="scheduleOpen()"
        @mouseleave="scheduleClose()"
        @focus="scheduleOpen(true)"
        @blur="scheduleClose()"
    >
        <Avatar :name="name" :src="src" :size="size" :dir="dir" />

        <Teleport to="body">
            <div
                v-if="isOpen"
                class="fixed z-[90]"
                :style="{ left: anchor.x + 'px', top: anchor.y + 'px' }"
                role="tooltip"
            >
                <div
                    class="avatar-preview-pop relative w-[208px] overflow-hidden rounded-lg bg-mistral-canvas/95 shadow-[0_20px_50px_rgba(0,0,0,0.25)] ring-1 ring-mistral-ink/10 backdrop-blur-sm"
                    :style="{ transformOrigin: anchor.side === 'right' ? 'left center' : 'right center' }"
                >
                    <div class="bg-gradient-to-b from-mistral-primary/15 via-transparent to-transparent p-2.5 pb-2">
                        <img
                            v-if="previewMounted && src && !previewFailed"
                            :src="src"
                            :alt="name"
                            class="h-64 w-[188px] rounded-md object-cover ring-1 ring-mistral-ink/10"
                            loading="lazy"
                            decoding="async"
                            draggable="false"
                            @error="previewFailed = true"
                        />
                        <div class="mt-2 max-w-full truncate text-center text-[13px] font-bold text-mistral-ink">
                            {{ name }}
                        </div>
                        <div v-if="employeeCode" class="mt-1 flex justify-center">
                            <span class="inline-block rounded-full bg-mistral-surface px-2.5 py-0.5 text-[11px] font-semibold text-mistral-steel" dir="ltr">
                                {{ employeeCode }}
                            </span>
                        </div>
                    </div>
                    <span
                        class="absolute top-1/2 h-3 w-3 -translate-y-1/2 rotate-45 bg-mistral-canvas"
                        :class="anchor.side === 'right' ? '-left-[7px]' : '-right-[7px]'"
                        aria-hidden="true"
                    ></span>
                </div>
            </div>
        </Teleport>
    </component>
</template>

<style scoped>
@keyframes avatar-preview-in {
    from {
        opacity: 0;
        transform: scale(0.94);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

.avatar-preview-pop {
    animation: avatar-preview-in 0.16s ease-out;
}
</style>
