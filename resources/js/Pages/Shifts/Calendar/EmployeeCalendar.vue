<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import CalendarLegend from '@/Pages/Shifts/Partials/CalendarLegend.vue'
import { useTranslations } from '@/composables/useTranslations'
import { getMonthCalendar } from '@/composables/useCyclicCalendar'

const { t } = useTranslations()

const props = defineProps({
    calendar: { type: Array, default: () => [] },
    employee: { type: Object, default: () => ({ id: null }) },
    month: { type: Number, default: () => new Date().getMonth() + 1 },
    year: { type: Number, default: () => new Date().getFullYear() },
})

const currentYear = ref(props.year)
const currentMonth = ref(props.month)

const monthName = computed(() => {
    const names = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر']
    return names[currentMonth.value - 1]
})

function goToPrevMonth() {
    if (currentMonth.value === 1) {
        currentMonth.value = 12
        currentYear.value--
    } else {
        currentMonth.value--
    }
    navigate()
}

function goToNextMonth() {
    if (currentMonth.value === 12) {
        currentMonth.value = 1
        currentYear.value++
    } else {
        currentMonth.value++
    }
    navigate()
}

function navigate() {
    router.get(route('schedule-calendar.employee', {
        employee: props.employee.id,
        month: currentMonth.value,
        year: currentYear.value,
    }))
}

function statusColor(status) {
    const map = {
        work: 'bg-green-500 text-white',
        rest: 'bg-gray-100 text-gray-500',
        holiday: 'bg-yellow-400 text-white',
        absent: 'bg-red-500 text-white',
        present: 'bg-green-600 text-white',
        on_leave: 'bg-blue-400 text-white',
    }
    return map[status] || 'bg-gray-100 text-gray-500'
}

function dayLabel(status) {
    const map = {
        work: 'د',
        rest: 'ر',
        holiday: 'ع',
        absent: 'غ',
        present: 'ح',
        on_leave: 'إ',
    }
    return map[status] || ''
}
</script>

<template>
    <AppLayout :title="'تقويم الموظف'">
        <PageHeader :title="'تقويم الموظف'" />

        <!-- Navigation -->
        <div class="flex items-center justify-between mb-4">
            <button @click="goToPrevMonth" class="btn btn-sm btn-outline">
                &laquo; السابق
            </Button>
            <h3 class="text-lg font-bold">{{ monthName }} {{ currentYear }}</h3>
            <button @click="goToNextMonth" class="btn btn-sm btn-outline">
                التالي &raquo;
            </Button>
        </div>

        <CalendarLegend class="mb-4" />

        <!-- Calendar grid: 7 columns -->
        <div class="grid grid-cols-7 gap-1 text-center">
            <div class="text-xs font-bold text-gray-500 py-2">السبت</div>
            <div class="text-xs font-bold text-gray-500 py-2">الأحد</div>
            <div class="text-xs font-bold text-gray-500 py-2">الإثنين</div>
            <div class="text-xs font-bold text-gray-500 py-2">الثلاثاء</div>
            <div class="text-xs font-bold text-gray-500 py-2">الأربعاء</div>
            <div class="text-xs font-bold text-gray-500 py-2">الخميس</div>
            <div class="text-xs font-bold text-gray-500 py-2">الجمعة</div>

            <template v-for="(cell, i) in calendar" :key="cell.date">
                <div
                    v-if="i === 0"
                    :style="{ gridColumnStart: cell.day_of_week + 2 }"
                ></div>
                <div
                    :class="[statusColor(cell.status), 'rounded-lg p-2 min-h-[60px] flex flex-col items-center justify-center cursor-pointer hover:opacity-80 transition']"
                    :title="cell.date + ' - ' + cell.day_name"
                >
                    <span class="text-xs">{{ new Date(cell.date).getDate() }}</span>
                    <span class="text-[10px] mt-0.5" v-if="cell.status !== 'rest'">{{ dayLabel(cell.status) }}</span>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
