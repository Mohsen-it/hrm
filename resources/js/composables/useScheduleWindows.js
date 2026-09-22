import { computed, reactive } from 'vue'
import { useTranslations } from '@/composables/useTranslations'

/**
 * Clock-time editing for time-schedule margins.
 *
 * Window edges (in/out ahead/above) and grace margins (late/early) are stored
 * as minute offsets, but operators think in clock times: this composable binds
 * time inputs to the minute fields, shows live hour equivalents plus the
 * resulting window edges, and blocks invalid picks (a window start after its
 * duty time, or an end before it).
 *
 * @param {object} form reactive form holding in_time/out_time + *_margin minutes
 */
export function useScheduleWindows(form) {
    const { t } = useTranslations()

    function num(value) {
        const n = Number(value)
        return Number.isFinite(n) && n > 0 ? n : 0
    }

    function toMinutes(time) {
        if (!time || !/^\d{1,2}:\d{2}/.test(time)) return null
        const [h, m] = time.split(':').map(Number)
        return h * 60 + m
    }

    function fmtClock(mins) {
        const m = ((mins % 1440) + 1440) % 1440
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0')
    }

    function fmtDuration(mins) {
        const n = Math.max(0, Math.round(Number(mins) || 0))
        return Math.floor(n / 60) + ':' + String(n % 60).padStart(2, '0')
    }

    function minutesHint(value) {
        return t('shifts.minutes') + ' · = ' + fmtDuration(value) + ' ' + t('shifts.hours_short')
    }

    function edgeHint(value, edge) {
        const base = t('shifts.minutes') + ' · = ' + fmtDuration(value) + ' ' + t('shifts.hours_short')
        return edge === null ? base : base + ' · ' + edge
    }

    const inMinutes = computed(() => toMinutes(form.in_time))
    const outMinutes = computed(() => toMinutes(form.out_time))

    const inStart = computed(() => (inMinutes.value === null ? null : fmtClock(inMinutes.value - num(form.in_ahead_margin))))
    const inEnd = computed(() => (inMinutes.value === null ? null : fmtClock(inMinutes.value + num(form.in_above_margin))))
    const outStart = computed(() => (outMinutes.value === null ? null : fmtClock(outMinutes.value - num(form.out_ahead_margin))))
    const outEnd = computed(() => (outMinutes.value === null ? null : fmtClock(outMinutes.value + num(form.out_above_margin))))

    const showPreview = computed(() => inStart.value !== null && outStart.value !== null)

    const windowErrors = reactive({ late: '', early: '', in_ahead: '', in_above: '', out_ahead: '', out_above: '' })

    function clockToMargin(clock, anchorMin, ahead, errorKey, errorMessage) {
        const picked = toMinutes(clock)
        if (picked === null || anchorMin === null) {
            windowErrors[errorKey] = ''
            return 0
        }
        const margin = ahead ? anchorMin - picked : picked - anchorMin
        if (margin < 0) {
            windowErrors[errorKey] = errorMessage
            return null
        }
        windowErrors[errorKey] = ''
        return margin
    }

    function edgeModel(anchorComputed, marginKey, ahead, errorKey, invalidMessage) {
        return computed({
            get: () => {
                const anchor = anchorComputed.value
                if (anchor === null) return ''
                return fmtClock(ahead ? anchor - num(form[marginKey]) : anchor + num(form[marginKey]))
            },
            set: (clock) => {
                const margin = clockToMargin(clock, anchorComputed.value, ahead, errorKey, invalidMessage)
                if (margin !== null) form[marginKey] = margin
            },
        })
    }

    const lateClock = computed({
        get: () => (inMinutes.value === null ? '' : fmtClock(inMinutes.value + num(form.late_margin))),
        set: (clock) => {
            const margin = clockToMargin(clock, inMinutes.value, false, 'late', t('shifts.late_until_invalid'))
            if (margin !== null) form.late_margin = margin
        },
    })

    const earlyClock = computed({
        get: () => (outMinutes.value === null ? '' : fmtClock(outMinutes.value - num(form.early_margin))),
        set: (clock) => {
            const margin = clockToMargin(clock, outMinutes.value, true, 'early', t('shifts.early_from_invalid'))
            if (margin !== null) form.early_margin = margin
        },
    })

    const inStartClock = edgeModel(inMinutes, 'in_ahead_margin', true, 'in_ahead', t('shifts.window_time_invalid'))
    const inEndClock = edgeModel(inMinutes, 'in_above_margin', false, 'in_above', t('shifts.window_time_invalid'))
    const outStartClock = edgeModel(outMinutes, 'out_ahead_margin', true, 'out_ahead', t('shifts.window_time_invalid'))
    const outEndClock = edgeModel(outMinutes, 'out_above_margin', false, 'out_above', t('shifts.window_time_invalid'))

    const hasWindowErrors = computed(() => Object.values(windowErrors).some(Boolean))

    return {
        minutesHint,
        edgeHint,
        inStart,
        inEnd,
        outStart,
        outEnd,
        showPreview,
        windowErrors,
        hasWindowErrors,
        lateClock,
        earlyClock,
        inStartClock,
        inEndClock,
        outStartClock,
        outEndClock,
    }
}
