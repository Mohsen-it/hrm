<script setup>
import { ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Badge from '@/Components/ui/Badge.vue';
import Alert from '@/Components/ui/Alert.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    period: { type: Object, required: true },
    entries: { type: Object, default: () => ({}) },
});

const showPublishDialog = ref(false);
const showRegenerateDialog = ref(false);

const statusVariant = (status) => {
    const map = { draft: 'pending', published: 'active', archived: 'inactive' };
    return map[status] || 'inactive';
};

const statusLabel = (status) => {
    const map = {
        draft: t('shifts.draft'),
        published: t('shifts.published'),
        archived: t('shifts.archived'),
    };
    return map[status] || status;
};

const dayStatusVariant = (status) => {
    const map = { WORK: 'active', REST: 'inactive' };
    return map[status] || 'inactive';
};

const dayStatusLabel = (status) => {
    const map = {
        WORK: t('shifts.status_work'),
        REST: t('shifts.status_rest'),
    };
    return map[status] || status;
};

const monthNames = computed(() => [
    t('shifts.january'), t('shifts.february'), t('shifts.march'),
    t('shifts.april'), t('shifts.may'), t('shifts.june'),
    t('shifts.july'), t('shifts.august'), t('shifts.september'),
    t('shifts.october'), t('shifts.november'), t('shifts.december'),
]);

function formatMonth(month) {
    return monthNames.value[month - 1] || month;
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('ar-SA');
}

function formatDateTime(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('ar-SA', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
}

const employeeEntries = computed(() => {
    const result = [];
    for (const [employeeId, entries] of Object.entries(props.entries)) {
        if (entries.length > 0) {
            const workCount = entries.filter(e => e.day_status === 'WORK').length;
            const restCount = entries.filter(e => e.day_status === 'REST').length;
            result.push({
                employee_id: employeeId,
                employee_name: entries[0]?.employee?.name || `#${employeeId}`,
                category_name: entries[0]?.duty_category?.name || '—',
                entries: entries,
                work_count: workCount,
                rest_count: restCount,
            });
        }
    }
    return result;
});

const selectedEmployee = ref(null);
const calendarDays = computed(() => {
    if (!selectedEmployee.value) return [];

    const emp = employeeEntries.value.find(e => e.employee_id === selectedEmployee.value);
    if (!emp) return [];

    const days = [];
    const startDate = new Date(props.period.schedule_period_start);
    const endDate = new Date(props.period.schedule_period_end);

    for (let d = new Date(startDate); d <= endDate; d.setDate(d.getDate() + 1)) {
        const dateStr = d.toISOString().split('T')[0];
        const entry = emp.entries.find(e => {
            const entryDate = typeof e.date === 'string' ? e.date.split('T')[0] : new Date(e.date).toISOString().split('T')[0];
            return entryDate === dateStr;
        });

        days.push({
            date: dateStr,
            day_of_week: d.getDay(),
            day_name: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][d.getDay()],
            day_name_ar: ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'][d.getDay()],
            day_number: d.getDate(),
            status: entry ? entry.day_status : null,
        });
    }
    return days;
});

function performPublish() {
    router.post(route('schedules.publish', props.period.id), {}, {
        preserveScroll: true,
    });
}

function performRegenerate() {
    router.post(route('schedules.regenerate', props.period.id), {}, {
        preserveScroll: true,
    });
}

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="`${t('shifts.schedules_title')} - ${formatMonth(period.month)} ${period.year}`">
        <PageHeader
            :title="`${formatMonth(period.month)} ${period.year}`"
            :description="`${t('shifts.schedule_version')}: ${period.schedule_version}`"
        >
            <template #actions>
                <Button variant="secondary" :href="route('schedules.index')" icon="fas fa-arrow-right">
                    {{ t('common.back') }}
                </Button>
                <Button v-if="period.status === 'draft'" variant="primary" icon="fas fa-check" @click="showPublishDialog = true">
                    {{ t('shifts.publish_schedule') }}
                </Button>
                <Button v-if="period.status === 'published'" variant="warning" icon="fas fa-sync" @click="showRegenerateDialog = true">
                    {{ t('shifts.regenerate_schedule') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" dismissible class="mb-4" />

        <nav class="flex items-center gap-0 border-b border-mistral-hairline-soft overflow-x-auto mb-6" role="tablist">
            <Link
                :href="route('shift-categories.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.shift_categories') }}
            </Link>
            <Link
                :href="route('time-schedules.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.time_schedules_title') }}
            </Link>
            <Link
                :href="route('schedules.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-primary border-mistral-primary"
                role="tab"
                aria-selected="true"
            >
                {{ t('shifts.schedules_title') }}
            </Link>
            <Link
                :href="route('shifts.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.title') }}
            </Link>
            <Link
                :href="route('shift-assignments.index')"
                class="px-4 py-2.5 text-[13px] font-medium transition-colors border-b-2 text-mistral-steel border-transparent hover:text-mistral-ink"
                role="tab"
                aria-selected="false"
            >
                {{ t('shifts.shift_assignments') }}
            </Link>
        </nav>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <Card variant="stat">
                <div class="text-sm text-mistral-steel">{{ t('shifts.schedule_status') }}</div>
                <Badge :text="statusLabel(period.status)" :variant="statusVariant(period.status)" class="mt-1" />
            </Card>
            <Card variant="stat">
                <div class="text-sm text-mistral-steel">{{ t('shifts.schedule_version') }}</div>
                <div class="font-medium mt-1">{{ period.schedule_version }}</div>
            </Card>
            <Card variant="stat">
                <div class="text-sm text-mistral-steel">{{ t('shifts.generated_by') }}</div>
                <div class="font-medium mt-1">{{ period.generated_by_name || '—' }}</div>
            </Card>
            <Card variant="stat">
                <div class="text-sm text-mistral-steel">{{ t('shifts.generated_at') }}</div>
                <div class="font-medium mt-1">{{ formatDateTime(period.generated_at) }}</div>
            </Card>
        </div>

        <Card variant="base" padding="sm" class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-mistral-ink">{{ t('shifts.schedule_entries') }}</h3>
                <div class="text-sm text-mistral-steel">{{ employeeEntries.length }} {{ t('shifts.employee') }}</div>
            </div>

            <div v-if="employeeEntries.length === 0" class="text-center py-8 text-mistral-steel">
                {{ t('shifts.no_data') }}
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-mistral-hairline-soft">
                            <th class="text-start py-3 px-4 font-medium text-mistral-ink">{{ t('shifts.employee_name') }}</th>
                            <th class="text-start py-3 px-4 font-medium text-mistral-ink">{{ t('shifts.category') }}</th>
                            <th class="text-center py-3 px-4 font-medium text-mistral-ink">{{ t('shifts.work_days_count') }}</th>
                            <th class="text-center py-3 px-4 font-medium text-mistral-ink">{{ t('shifts.rest_days_count') }}</th>
                            <th class="text-center py-3 px-4 font-medium text-mistral-ink">{{ t('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="emp in employeeEntries"
                            :key="emp.employee_id"
                            class="border-b border-mistral-hairline-soft hover:bg-mistral-cream-soft cursor-pointer"
                            :class="{ 'bg-mistral-cream-soft': selectedEmployee === emp.employee_id }"
                            @click="selectedEmployee = selectedEmployee === emp.employee_id ? null : emp.employee_id"
                        >
                            <td class="py-3 px-4">{{ emp.employee_name }}</td>
                            <td class="py-3 px-4">{{ emp.category_name }}</td>
                            <td class="py-3 px-4 text-center">
                                <Badge :text="emp.work_count.toString()" variant="active" />
                            </td>
                            <td class="py-3 px-4 text-center">
                                <Badge :text="emp.rest_count.toString()" variant="inactive" />
                            </td>
                            <td class="py-3 px-4 text-center">
                                <i class="fas" :class="selectedEmployee === emp.employee_id ? 'fa-chevron-up' : 'fa-chevron-down'" />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <Card v-if="selectedEmployee && calendarDays.length > 0" variant="base" padding="sm" class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-mistral-ink">
                    {{ employeeEntries.find(e => e.employee_id === selectedEmployee)?.employee_name }}
                    - {{ t('shifts.schedule_preview') }}
                </h3>
                <div class="flex items-center gap-4 text-sm">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-green-500"></span>
                        <span>{{ t('shifts.work_day_legend') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-3 rounded bg-gray-200"></span>
                        <span>{{ t('shifts.rest_day_legend') }}</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-7 gap-1 text-center mb-2">
                <div v-for="day in ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة']" :key="day" class="text-xs font-bold text-gray-500 py-2">
                    {{ day }}
                </div>
            </div>

            <div class="grid grid-cols-7 gap-1">
                <template v-for="(day, index) in calendarDays" :key="day.date">
                    <div
                        v-if="index === 0"
                        :style="{ gridColumnStart: ((day.day_of_week + 1) % 7) + 1 }"
                    ></div>
                    <div
                        :class="[
                            'rounded-lg p-2 min-h-[55px] flex flex-col items-center justify-center transition',
                            day.status === 'WORK' ? 'bg-green-500 text-white' : 'bg-gray-100 text-gray-500',
                        ]"
                        :title="day.date + ' - ' + day.day_name_ar"
                    >
                        <span class="text-xs">{{ day.day_number }}</span>
                        <span class="text-[10px] mt-0.5">{{ day.day_name }}</span>
                    </div>
                </template>
            </div>
        </Card>

        <ConfirmDialog
            v-model="showPublishDialog"
            :title="t('shifts.publish_schedule')"
            :message="t('shifts.publish_schedule') + '?'"
            :confirm-text="t('common.confirm')"
            :cancel-text="t('common.cancel')"
            confirm-variant="primary"
            @confirm="performPublish"
        />

        <ConfirmDialog
            v-model="showRegenerateDialog"
            :title="t('shifts.regenerate_schedule')"
            :message="t('shifts.regenerate_schedule') + '?'"
            :confirm-text="t('common.confirm')"
            :cancel-text="t('common.cancel')"
            confirm-variant="warning"
            @confirm="performRegenerate"
        />
    </AppLayout>
</template>
