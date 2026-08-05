<script setup>
defineProps({
    schedule: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

function time(value) {
    return value ? String(value).slice(0, 5) : '—';
}
</script>

<template>
    <div v-if="loading || schedule" class="rounded-md border border-mistral-hairline bg-mistral-surface p-4">
        <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-mistral-ink">
            <i class="fas fa-rotate text-mistral-primary" aria-hidden="true" />
            نافذة الدورية المعتمدة
        </div>
        <p v-if="loading" class="text-sm text-mistral-steel">جارٍ جلب نافذة الدخول والخروج من الدورية…</p>
        <template v-else>
            <p v-if="!schedule.is_work_day" class="mb-3 text-sm text-mistral-warning">هذا التاريخ ليس يوم عمل ضمن الدورية المحددة.</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-sm bg-white p-3">
                    <p class="text-xs text-mistral-steel">نافذة الدخول</p>
                    <p dir="ltr" class="mt-1 font-mono text-sm font-semibold text-mistral-ink">{{ time(schedule.in_ahead_margin) }} — {{ time(schedule.in_above_margin) }}</p>
                </div>
                <div class="rounded-sm bg-white p-3">
                    <p class="text-xs text-mistral-steel">نافذة الخروج</p>
                    <p dir="ltr" class="mt-1 font-mono text-sm font-semibold text-mistral-ink">{{ time(schedule.out_ahead_margin) }} — {{ time(schedule.out_above_margin) }}</p>
                </div>
            </div>
        </template>
    </div>
</template>
