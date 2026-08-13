import { computed, ref } from 'vue';
import { useTranslations } from './useTranslations';

/**
 * usePeriodFilter — قائمة منسدلة لفترات جاهزة (اليوم / آخر 7 أيام / آخر 30 يوم /
 * هذا الشهر / هذا العام / فترة مخصصة).
 *
 * اختيار فترة جاهزة يضبط حقلي `from`/`to` تلقائياً ثم ينفذ `apply()`؛ اختيار
 * "فترة مخصصة" يترك التاريخين للمستخدم. أي تعديل يدوي على التاريخين يحوّل
 * القائمة تلقائياً إلى "فترة مخصصة".
 *
 * @param {{ value: import('vue').Ref<string> }} fromRef
 * @param {{ value: import('vue').Ref<string> }} toRef
 * @param {string} initialPeriod
 * @param {() => void} apply
 */
export function usePeriodFilter(fromRef, toRef, initialPeriod = 'custom', apply) {
    const { t } = useTranslations();

    const period = ref(initialPeriod);

    const isCustom = computed(() => period.value === 'custom');

    const periodOptions = computed(() => [
        { value: 'custom', label: t('useractivity.period_custom') },
        { value: 'today', label: t('useractivity.period_today') },
        { value: 'last_7', label: t('useractivity.period_last_7') },
        { value: 'last_30', label: t('useractivity.period_last_30') },
        { value: 'this_month', label: t('useractivity.period_this_month') },
        { value: 'this_year', label: t('useractivity.period_this_year') },
    ]);

    function pad(n) {
        return String(n).padStart(2, '0');
    }

    function toDateStr(d) {
        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    }

    /**
     * Apply the selected preset: compute the matching from/to range and
     * trigger the page refresh.
     */
    function applyPeriod() {
        if (isCustom.value) return;

        const now = new Date();
        const to = toDateStr(now);
        let from = to;

        switch (period.value) {
            case 'last_7':
                from = toDateStr(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6));
                break;
            case 'last_30':
                from = toDateStr(new Date(now.getFullYear(), now.getMonth(), now.getDate() - 29));
                break;
            case 'this_month':
                from = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-01`;
                break;
            case 'this_year':
                from = `${now.getFullYear()}-01-01`;
                break;
            default: // today
                break;
        }

        fromRef.value = from;
        toRef.value = to;
        apply();
    }

    return {
        period,
        periodOptions,
        isCustom,
        applyPeriod,
        today: () => toDateStr(new Date()),
        daysAgo: (n) => toDateStr(new Date(new Date().getFullYear(), new Date().getMonth(), new Date().getDate() - n)),
    };
}
