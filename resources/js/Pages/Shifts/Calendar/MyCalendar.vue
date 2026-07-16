<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue'
import CyclicDaysDisplay from '@/Pages/Shifts/Partials/CyclicDaysDisplay.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
    calendar: { type: Array, default: () => [] },
    month: { type: Number, default: () => new Date().getMonth() + 1 },
    year: { type: Number, default: () => new Date().getFullYear() },
})

const currentMonth = ref(props.month)
const currentYear = ref(props.year)

const monthNames = [
    'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
    'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
]

const monthLabel = computed(() => `${monthNames[currentMonth.value - 1]} ${currentYear.value}`)

function prevMonth() {
    if (currentMonth.value === 1) {
        currentMonth.value = 12
        currentYear.value--
    } else {
        currentMonth.value--
    }
    navigate()
}

function nextMonth() {
    if (currentMonth.value === 12) {
        currentMonth.value = 1
        currentYear.value++
    } else {
        currentMonth.value++
    }
    navigate()
}

function navigate() {
    router.get(
        route('my-calendar'),
        { month: currentMonth.value, year: currentYear.value },
        { preserveState: true, preserveScroll: true, replace: true },
    )
}

function statusColor(status) {
    const map = {
        work: 'bg-green-500 text-white',
        rest: 'bg-gray-200 text-gray-600',
        absent: 'bg-red-500 text-white',
        holiday: 'bg-yellow-400 text-white',
        present: 'bg-green-600 text-white',
        on_leave: 'bg-blue-400 text-white',
    }
    return map[status] || 'bg-gray-200 text-gray-600'
}

function dayLabel(status) {
    const map = {
        work: 'د',
        rest: 'ر',
        absent: 'غ',
        holiday: 'ع',
        present: 'ح',
        on_leave: 'إ',
    }
    return map[status] || ''
}

const dayNames = ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة']
</script>

<template>
    <AppLayout :title="'جدول دوامي'">
        <PageHeader :title="'جدول دوامي'" />

        <div class="flex items-center justify-between mb-4">
            <button @click="prevMonth" class="btn btn-sm btn-outline">&laquo; السابق</Button>
            <h3 class="text-lg font-bold">{{ monthLabel }}</h3>
            <button @click="nextMonth" class="btn btn-sm btn-outline">التالي &raquo;</Button>
        </div>

        <CalendarLegend class="mb-4" />

        <div class="grid grid-cols-7 gap-1 text-center mb-6">
            <div class="text-xs font-bold text-gray-500 py-2">السبت</div>
            <div class="text-xs font-bold text-gray-500 py-2">الأحد</div>
            <div class="text-xs font-bold text-gray-500 py-2">الإثنين</div>
            <div class="text-xs font-bold text-gray-500 py-2">الثلاثاء</div>
            <div class="text-xs font-bold text-gray-500 py-2">الأربعاء</div>
            <div class="text-xs font-bold text-gray-500 py-2">الخميس</div>
            <div class="text-xs font-bold text-gray-500 py-2">الجمعة</div>

            <template v-for="(cell, i) in calendar" :key="cell.date">
                <div
                    v-if="i === 0 && cell.day_of_week !== undefined"
                    :style="{ gridColumnStart: cell.day_of_week + 2 }"
                ></div>
                <div
                    :class="[statusColor(cell.status), 'rounded-lg p-2 min-h-[55px] flex flex-col items-center justify-center cursor-pointer hover:opacity-80 transition']"
                    :title="cell.date + ' - ' + cell.day_name"
                >
                    <span class="text-xs">{{ new Date(cell.date).getDate() }}</span>
                    <span class="text-[10px] mt-0.5" v-if="cell.status !== 'rest'">{{ dayLabel(cell.status) }}</span>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
