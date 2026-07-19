<script setup>
import { computed } from 'vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    data: { type: Array, default: () => [] },
    dir: { type: String, default: 'rtl' },
});

const dayLabels = {
    ar: ['سبت', 'أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة'],
    en: ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
};

const monthLabels = {
    ar: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'],
    en: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
};

const colorScale = [
    { min: 0, max: 0, bg: 'bg-mistral-surface', text: 'text-mistral-muted', label: 'no_data' },
    { min: 0.01, max: 50, bg: 'bg-red-100', text: 'text-red-700', label: 'poor' },
    { min: 50.01, max: 70, bg: 'bg-amber-100', text: 'text-amber-700', label: 'average' },
    { min: 70.01, max: 85, bg: 'bg-green-100', text: 'text-green-700', label: 'good' },
    { min: 85.01, max: 100, bg: 'bg-emerald-200', text: 'text-emerald-800', label: 'excellent' },
];

const weeks = computed(() => {
    if (!props.data.length) return [];

    const dataMap = {};
    props.data.forEach((d) => {
        dataMap[d.date] = d;
    });

    const result = [];
    const sorted = [...props.data].sort((a, b) => a.date.localeCompare(b.date));

    if (sorted.length === 0) return [];

    const firstDate = new Date(sorted[0].date);
    const lastDate = new Date(sorted[sorted.length - 1].date);

    let current = new Date(firstDate);
    let week = [];

    while (current <= lastDate) {
        const dateStr = current.toISOString().split('T')[0];
        const dayOfWeek = (current.getDay() + 1) % 7;

        if (dayOfWeek === 0 && week.length > 0) {
            result.push(week);
            week = [];
        }

        const dayData = dataMap[dateStr] || null;
        week.push({
            date: dateStr,
            day: current.getDate(),
            month: current.getMonth(),
            dayOfWeek,
            rate: dayData ? dayData.rate : null,
            present: dayData ? dayData.present : 0,
            absent: dayData ? dayData.absent : 0,
            total: dayData ? dayData.total : 0,
        });

        current.setDate(current.getDate() + 1);
    }

    if (week.length > 0) {
        result.push(week);
    }

    return result;
});

function getColor(rate) {
    if (rate === null) return colorScale[0];
    for (const c of colorScale) {
        if (rate >= c.min && rate <= c.max) return c;
    }
    return colorScale[0];
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('ar-SA', { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <div :dir="dir">
        <!-- Day labels -->
        <div class="flex items-center gap-1 mb-2">
            <div class="w-8"></div>
            <div
                v-for="(label, i) in dayLabels[dir === 'rtl' ? 'ar' : 'en']"
                :key="i"
                class="flex-1 text-center text-[10px] text-mistral-stone font-medium"
            >
                {{ label }}
            </div>
        </div>

        <!-- Weeks grid -->
        <div class="space-y-1">
            <div v-for="(week, wi) in weeks" :key="wi" class="flex items-center gap-1">
                <div class="w-8 text-[9px] text-mistral-stone text-center shrink-0">
                    {{ week[0] ? monthLabels[dir === 'rtl' ? 'ar' : 'en'][week[0].month] : '' }}
                </div>
                <div
                    v-for="(day, di) in week"
                    :key="di"
                    class="flex-1 aspect-square rounded-md flex items-center justify-center text-[9px] font-medium relative group cursor-default transition-transform hover:scale-110"
                    :class="[getColor(day.rate).bg, getColor(day.rate).text]"
                >
                    {{ day.day }}
                    <!-- Tooltip -->
                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 bg-mistral-ink text-white text-[10px] rounded-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50 shadow-level-4">
                        <div class="font-semibold mb-1">{{ formatDate(day.date) }}</div>
                        <div v-if="day.total > 0">
                            {{ t('dashboard.present') }}: {{ day.present }} / {{ day.total }}
                            <span class="ms-1 opacity-70">({{ day.rate }}%)</span>
                        </div>
                        <div v-else class="opacity-60">{{ t('dashboard.no_data') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center gap-3 mt-4 pt-3 border-t border-mistral-hairline-soft">
            <span class="text-[10px] text-mistral-stone">{{ t('dashboard.poor') }}</span>
            <div v-for="(c, i) in colorScale.slice(1)" :key="i" class="flex items-center gap-1">
                <div :class="['w-3 h-3 rounded-sm', c.bg]"></div>
            </div>
            <span class="text-[10px] text-mistral-stone">{{ t('dashboard.excellent') }}</span>
        </div>
    </div>
</template>
