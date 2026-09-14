import { watch, nextTick, onUnmounted } from 'vue';

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
    '[contenteditable="true"]',
].join(',');

/**
 * useFocusTrap — احتجاز التركيز داخل حاوية (WAI-ARIA dialog pattern)
 *
 * بينما `active` صحيح:
 *  - تركّز أول عنصر قابل للتركيز عند الفتح (أو `initialFocusRef` إن وُجد).
 *  - Tab / Shift+Tab يدوران داخل الحاوية فقط.
 *  - عند إلغاء التنشيط يُعاد التركيز للعنصر الذي كان عليه قبل الفتح.
 *
 * @param {import('vue').Ref<HTMLElement|null>} containerRef حاوية الاحتجاز
 * @param {import('vue').Ref<boolean>} active هل الاحتجاز مُفعّل
 * @param {{ initialFocusRef?: import('vue').Ref<HTMLElement|null> }} options
 */
export function useFocusTrap(containerRef, active, { initialFocusRef = null } = {}) {
    let previouslyFocusedElement = null;
    let tabHandler = null;

    function getFocusableElements() {
        const container = containerRef.value;
        if (!container) return [];
        return Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR)).filter((el) => {
            if (el.closest('[inert]')) return false;
            if (el.getAttribute('aria-hidden') === 'true') return false;
            if (el.offsetParent === null && getComputedStyle(el).position !== 'fixed') return false;
            return true;
        });
    }

    function focusFirst() {
        const initial = initialFocusRef?.value;
        if (initial && containerRef.value?.contains(initial)) {
            initial.focus();
            return;
        }
        const elements = getFocusableElements();
        (elements[0] || containerRef.value)?.focus?.();
    }

    function handleKeydown(e) {
        if (e.key !== 'Tab') return;
        const container = containerRef.value;
        if (!container) return;

        const elements = getFocusableElements();
        if (elements.length === 0) {
            e.preventDefault();
            container.focus();
            return;
        }

        const first = elements[0];
        const last = elements[elements.length - 1];
        const activeElement = document.activeElement;

        if (e.shiftKey) {
            if (activeElement === first || !container.contains(activeElement)) {
                e.preventDefault();
                last.focus();
            }
        } else if (activeElement === last || !container.contains(activeElement)) {
            e.preventDefault();
            first.focus();
        }
    }

    function activate() {
        previouslyFocusedElement = document.activeElement;
        tabHandler = handleKeydown;
        // The container is usually behind a v-if/Teleport and not mounted yet
        // when `active` flips — defer attachment + initial focus one tick.
        nextTick(() => {
            if (!active.value) return; // closed again before mounting
            containerRef.value?.addEventListener('keydown', tabHandler);
            focusFirst();
        });
    }

    function deactivate() {
        if (tabHandler) {
            containerRef.value?.removeEventListener('keydown', tabHandler);
            tabHandler = null;
        }
        previouslyFocusedElement?.focus?.();
        previouslyFocusedElement = null;
    }

    watch(active, (isActive) => {
        if (isActive) activate();
        else deactivate();
    });

    onUnmounted(() => {
        // If the component unmounts while open (e.g. route change), restore
        // focus without relying on the (already-unmounted) container.
        if (active.value) {
            previouslyFocusedElement?.focus?.();
            previouslyFocusedElement = null;
        }
        tabHandler = null;
    });

    return { focusFirst, deactivate };
}
