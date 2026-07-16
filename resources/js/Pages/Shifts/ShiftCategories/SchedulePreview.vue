<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    category: { type: Object, required: true },
    assignment_start: { type: String, required: true },
    from: { type: String, required: true },
    to: { type: String, required: true },
    schedule: { type: Array, required: true },
});

const currentMonth = ref(new Date(props.from).getMonth());
const currentYear = ref(new Date(props.from).getFullYear());
const viewMode = ref('month');

const monthNames = computed(() => [
    t('shifts.january'), t('shifts.february'), t('shifts.march'),
    t('shifts.april'), t('shifts.may'), t('shifts.june'),
    t('shifts.july'), t('shifts.august'), t('shifts.september'),
    t('shifts.october'), t('shifts.november'), t('shifts.december')
]);

const dayNames = computed(() => [
    t('shifts.sunday'), t('shifts.monday'), t('shifts.tuesday'),
    t('shifts.wednesday'), t('shifts.thursday'), t('shifts.friday'),
    t('shifts.saturday')
]);

const shortDayNames = computed(() => [
    t('shifts.sun_short'), t('shifts.mon_short'), t('shifts.tue_short'),
    t('shifts.wed_short'), t('shifts.thu_short'), t('shifts.fri_short'),
    t('shifts.sat_short')
]);

const stats = computed(() => {
    const workDays = props.schedule.filter(s => s.is_work_day).length;
    const restDays = props.schedule.length - workDays;
    return {
        total: props.schedule.length,
        workDays,
        restDays,
    };
});

const monthSchedule = computed(() => {
    const days = [];
    const firstDay = new Date(currentYear.value, currentMonth.value, 1);
    const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0);
    const startDay = firstDay.getDay();

    // Empty cells before first day
    for (let i = 0; i < startDay; i++) {
        days.push({ date: null, isWorkDay: false, isEmpty: true });
    }

    // Days of the month
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const scheduleItem = props.schedule.find(s => s.date === dateStr);
        days.push({
            date: dateStr,
            day: d,
            isWorkDay: scheduleItem?.is_work_day ?? false,
            isEmpty: false,
            isToday: dateStr === new Date().toISOString().split('T')[0],
        });
    }

    return days;
});

const prevMonth = () => {
    if (currentMonth.value === 0) {
        currentMonth.value = 11;
        currentYear.value--;
    } else {
        currentMonth.value--;
    }
};

const nextMonth = () => {
    if (currentMonth.value === 11) {
        currentMonth.value = 0;
        currentYear.value++;
    } else {
        currentMonth.value++;
    }
};

const formatDate = (dateStr) => {
    if (!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ar-SA', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};
</script>

<template>
    <AppLayout :title="t('shifts.schedule_preview', { name: category.name })">
        <PageHeader
            :title="t('shifts.schedule_preview', { name: category.name })"
            :description="t('shifts.schedule_preview_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('shift-categories.show', category.id)" icon="fas fa-arrow-right">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="lg" class="mb-6">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-mistral-hairline">
                <div
                    class="w-16 h-16 rounded-md flex items-center justify-center border border-mistral-hairline"
                    :style="{ backgroundColor: category.color || '#fa520f' }"
                >
                    <i class="fas fa-layer-group text-[24px] text-white"></i>
                </div>
                <div class="flex-1">
                    <h2 class="text-[20px] font-semibold text-mistral-ink">
                        {{ category.name }}
                    </h2>
                    <div class="mt-2 flex items-center gap-2 flex-wrap">
                        <Badge :text="t('shifts.cyclic')" variant="info" v-if="category.type === 'cyclic'" />
                        <Badge :text="t('shifts.weekly')" variant="primary" v-else-if="category.type === 'weekly'" />
                        <Badge :text="t('shifts.hours')" variant="secondary" v-else />
                        <Badge
                            :text="`${t('shifts.cycle_start')}: ${props.assignment_start}`"
                            variant="active"
                        />
                    </div>
                </div>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-4 gap-x-6 gap-y-3">
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ t('shifts.work_days') }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">
                        {{ category.type === 'cyclic' ? `${category.work_days || 0} ${t('shifts.days')}` : (category.work_days_json ? category.work_days_json.length : '—') }}
                    </dd>
                </div>
                <div class="flex flex-col" v-if="category.type === 'cyclic'">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ t('shifts.rest_days') }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">
                        {{ category.rest_days || 0 }} {{ t('shifts.days') }}
                    </dd>
                </div>
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ t('shifts.range_from') }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">{{ props.from }}</dd>
                </div>
                <div class="flex flex-col">
                    <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                        {{ t('shifts.range_to') }}
                    </dt>
                    <dd class="text-[14px] text-mistral-ink mt-1">{{ props.to }}</dd>
                </div>
            </dl>
        </Card>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Card variant="stat" padding="lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                            {{ t('shifts.total_days') }}
                        </p>
                        <p class="text-[28px] font-bold text-mistral-ink mt-1">{{ stats.total }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-mistral-primary/10 flex items-center justify-center">
                        <i class="fas fa-calendar text-mistral-primary text-xl"></i>
                    </div>
                </div>
            </Card>

            <Card variant="stat" padding="lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                            {{ t('shifts.work_days_count') }}
                        </p>
                        <p class="text-[28px] font-bold text-green-600 mt-1">{{ stats.workDays }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-green-100 flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                </div>
            </Card>

            <Card variant="stat" padding="lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">
                            {{ t('shifts.rest_days_count') }}
                        </p>
                        <p class="text-[28px] font-bold text-gray-500 mt-1">{{ stats.restDays }}</p>
                    </div>
                    <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center">
                        <i class="fas fa-moon text-gray-500 text-xl"></i>
                    </div>
                </div>
            </Card>
        </div>

        <Card variant="base" padding="lg">
            <template #header>
                <div class="flex items-center justify-between">
                    <h3 class="text-[16px] font-semibold text-mistral-ink">
                        {{ monthNames[currentMonth] }} {{ currentYear }}
                    </h3>
                    <div class="flex items-center gap-2">
                        <Button variant="ghost" size="sm" @click="prevMonth" icon="fas fa-chevron-right" />
                        <Button variant="ghost" size="sm" @click="nextMonth" icon="fas fa-chevron-left" />
                    </div>
                </div>
            </template>

            <div class="overflow-x-auto">
                <table class="w-full text-center text-[13px]">
                    <thead>
                        <tr class="border-b border-mistral-hairline">
                            <th v-for="(name, i) in shortDayNames" :key="i" class="px-3 py-2 text-mistral-slate">
                                {{ name }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(week, weekIndex) in chunkedDays" :key="weekIndex" class="border-b border-mistral-hairline last:border-0">
                            <td v-for="(day, dayIndex) in week" :key="dayIndex" class="px-2 py-1.5 relative">
                                <div
                                    v-if="!day.isEmpty"
                                    :class="[
                                        'w-9 h-9 rounded-md flex items-center justify-center mx-auto transition-colors',
                                        day.isWorkDay ? 'bg-green-100 text-green-700 font-medium' : 'bg-gray-100 text-gray-500',
                                        day.isToday ? 'ring-2 ring-mistral-primary' : ''
                                    ]"
                                    :title="formatDate(day.date)"
                                >
                                    {{ day.day }}
                                </div>
                                <div v-else class="w-9 h-9"></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 flex items-center justify-center gap-6 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-green-100"></span>
                    <span class="text-mistral-ink">{{ t('shifts.work_day') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded bg-gray-100"></span>
                    <span class="text-mistral-ink">{{ t('shifts.rest_day') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-4 h-4 rounded border-2 border-mistral-primary"></span>
                    <span class="text-mistral-ink">{{ t('shifts.today') }}</span>
                </div>
            </div>
        </Card>
    </AppLayout>
</template>

<script>
export default {
    computed: {
        chunkedDays() {
            const chunks = [];
            for (let i = 0; i < this.monthSchedule.length; i += 7) {
                chunks.push(this.monthSchedule.slice(i, i + 7));
            }
            return chunks;
        }
    }
}
</script>