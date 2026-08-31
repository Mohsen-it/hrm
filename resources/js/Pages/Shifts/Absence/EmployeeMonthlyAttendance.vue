<script>
import AppLayout from '@/Layouts/AppLayout.vue';
export default { layout: AppLayout };
</script>

<script setup>
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { PageHeader, Card, Button, Badge, StatCard, FormInput, FormSelect, EmptyState } from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'
import { usePageTitle } from '@/composables/usePageTitle'

const { t } = useTranslations()

const props = defineProps({
    report: { type: Object, default: null },
    employee: { type: Object, default: null },
    filters: { type: Object, default: () => ({ month: 1, year: 2026, employee_id: null }) },
})

const searchQuery = ref('')
const searchResults = ref([])
const searching = ref(false)
const selectedEmployeeId = ref(props.filters?.employee_id || null)
const selectedEmployeeLabel = ref(props.employee ? `${props.employee.name} (${props.employee.employee_code || ''})` : '')
const selectedMonth = ref(Number(props.filters?.month) || new Date().getMonth() + 1)
const selectedYear = ref(Number(props.filters?.year) || new Date().getFullYear())

const monthOptions = computed(() =>
    Array.from({ length: 12 }, (_, i) => ({ value: i + 1, label: t(`shifts.${['january','february','march','april','may','june','july','august','september','october','november','december'][i]}`) }))
)

let searchTimer = null
watch(searchQuery, (val) => {
    clearTimeout(searchTimer)
    if (!val || val.trim().length < 2) { searchResults.value = []; return }
    searchTimer = setTimeout(async () => {
        searching.value = true
        try {
            const res = await fetch(route('smart-absence.search-employees') + `?q=${encodeURIComponent(val)}`, { headers: { 'Accept': 'application/json' } })
            const data = await res.json()
            searchResults.value = (data || []).map(e => ({ value: e.id, label: `${e.name} — ${e.employee_code || ''}`, raw: e }))
        } finally { searching.value = false }
    }, 300)
})

function selectEmployee(opt) {
    selectedEmployeeId.value = opt.value
    selectedEmployeeLabel.value = opt.label
    searchQuery.value = ''
    searchResults.value = []
}

function loadReport() {
    if (!selectedEmployeeId.value) return
    router.get(route('smart-absence.employee-attendance'), {
        employee_id: selectedEmployeeId.value,
        month: selectedMonth.value,
        year: selectedYear.value,
    }, { preserveState: false, replace: true })
}

function statusClass(status) {
    return {
        present: 'bg-mistral-success/10 text-mistral-success border-mistral-success/30',
        vacation: 'bg-mistral-info/10 text-mistral-info border-mistral-info/30',
        exception: 'bg-mistral-primary/10 text-mistral-primary border-mistral-primary/30',
        holiday: 'bg-mistral-cream-deeper text-mistral-ink border-mistral-hairline',
        absent: 'bg-mistral-danger/10 text-mistral-danger border-mistral-danger/30',
    }[status] || 'bg-mistral-surface text-mistral-steel border-mistral-hairline'
}

function statusLabel(status) {
    const map = { present: t('shifts.present'), vacation: t('shifts.on_vacation'), exception: t('shifts.on_exception'), holiday: t('shifts.official_holiday'), absent: t('shifts.absent_short') }
    return map[status] || status
}

const r = computed(() => props.report)

usePageTitle(t('shifts.employee_monthly_attendance') || 'دوام الموظف الشهري')
</script>

<template>
    <PageHeader
        :title="t('shifts.employee_monthly_attendance')"
        :description="t('shifts.employee_monthly_attendance_desc')"
    />

    <Card variant="base" padding="none" class="mb-5">
        <div class="p-5">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-end">
                <!-- Employee search -->
                <div class="lg:col-span-5">
                    <label class="block text-[13px] font-medium text-mistral-ink mb-1.5">{{ t('shifts.employee') }} <span class="text-mistral-danger">*</span></label>
                    <div class="relative">
                        <div v-if="selectedEmployeeLabel" class="flex items-center gap-2 mb-2">
                            <span class="inline-flex items-center gap-1.5 bg-mistral-primary/10 text-mistral-primary rounded-md px-2.5 py-1 text-[12px] font-medium">
                                <i class="fas fa-user text-[11px]"></i> {{ selectedEmployeeLabel }}
                                <button type="button" @click="selectedEmployeeId=null; selectedEmployeeLabel=''" class="ms-1 hover:text-mistral-danger"><i class="fas fa-times text-[10px]"></i></button>
                            </span>
                        </div>
                        <div class="relative">
                            <i class="fas fa-search absolute top-1/2 -translate-y-1/2 right-3 text-mistral-muted text-[12px]"></i>
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="t('shifts.search_employee_placeholder')"
                                class="w-full h-11 pe-10 ps-3 text-[14px] bg-white border border-mistral-hairline-strong rounded-lg focus:outline-none focus:ring-2 focus:ring-mistral-primary/20 focus:border-mistral-primary"
                            />
                        </div>
                        <div v-if="searchResults.length" class="absolute z-20 mt-1 w-full bg-white border border-mistral-hairline-strong rounded-lg shadow-xl max-h-60 overflow-auto">
                            <button
                                v-for="opt in searchResults"
                                :key="opt.value"
                                type="button"
                                @click="selectEmployee(opt)"
                                class="w-full px-3 py-2.5 text-start text-[13px] text-mistral-ink hover:bg-mistral-surface border-b border-mistral-hairline-soft last:border-0"
                            >
                                {{ opt.label }}
                            </button>
                        </div>
                        <p v-if="searching" class="text-[11px] text-mistral-steel mt-1"><i class="fas fa-spinner fa-spin ms-1"></i> جاري البحث...</p>
                    </div>
                </div>

                <div class="lg:col-span-3">
                    <FormSelect v-model="selectedMonth" :label="t('shifts.month')" name="month" :options="monthOptions" />
                </div>
                <div class="lg:col-span-2">
                    <FormInput v-model.number="selectedYear" type="number" :label="t('shifts.year')" name="year" />
                </div>
                <div class="lg:col-span-2">
                    <Button variant="primary" icon="fas fa-search" class="w-full" :disabled="!selectedEmployeeId" @click="loadReport">
                        {{ t('common.search') || 'عرض' }}
                    </Button>
                </div>
            </div>
        </div>
    </Card>

    <!-- No selection yet -->
    <Card v-if="!r" variant="base" padding="none">
        <EmptyState icon="fas fa-calendar-check" :title="t('shifts.select_employee_and_month')" :description="t('shifts.select_employee_and_month_desc')" />
    </Card>

    <!-- Report -->
    <template v-if="r">
        <!-- Rotation info banner -->
        <Card v-if="r.has_assignment" variant="base" padding="none" class="mb-4">
            <div class="px-5 py-3 flex items-center gap-3 flex-wrap text-[13px]">
                <span class="inline-flex items-center gap-1.5"><i class="fas fa-circle-notch text-mistral-primary text-[12px]"></i> <strong>{{ r.rotation_name || '—' }}</strong> — {{ r.rotation_group_name || '—' }}</span>
                <span class="text-mistral-hairline">|</span>
                <span>{{ t('shifts.cycle_length') }}: <strong dir="ltr">{{ r.cycle_length }}</strong></span>
                <span>{{ r.work_days_count }}{{ t('shifts.work_days_count') ? '' : ' عمل' }} / {{ r.rest_days_count }} راحة</span>
                <span class="text-mistral-hairline">|</span>
                <span class="inline-flex items-center gap-1 bg-mistral-primary/10 text-mistral-primary rounded px-2 py-0.5 font-bold">عامل الوزن = {{ r.weight_factor }} <span class="font-normal opacity-70">({{ r.cycle_length }} ÷ {{ r.work_days_count }})</span></span>
                <span class="text-[11px] text-mistral-steel">إذا غاب يوم واحد = {{ r.weight_factor }} أيام وزناً</span>
            </div>
        </Card>
        <Card v-else variant="base" class="mb-4">
            <div class="text-center py-2 text-mistral-danger text-[13px]"><i class="fas fa-triangle-exclamation ms-1"></i> {{ t('shifts.no_active_assignment') }}</div>
        </Card>

        <!-- Stat cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <StatCard :label="t('shifts.expected_days')" :value="r.effective_expected" icon="fas fa-calendar-check" color="info" />
            <StatCard :label="t('shifts.present_days')" :value="r.present_days" icon="fas fa-user-check" color="success" />
            <StatCard :label="t('shifts.on_vacation') + ' (دوام)'" :value="r.vacation_days" icon="fas fa-plane" color="info" />
            <StatCard :label="t('shifts.absent_days') + ' (فعلي)'" :value="r.absent_physical" icon="fas fa-user-xmark" color="danger" />
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <StatCard :label="t('shifts.worked_days_weighted') || 'أيام الدوام (فعلي)'" :value="r.worked_physical" icon="fas fa-briefcase" color="success" />
            <StatCard :label="t('shifts.absent_weighted') || 'الغياب الموزون'" :value="r.absent_weighted" icon="fas fa-scale-balanced" color="danger" />
            <StatCard :label="t('shifts.worked_weighted') || 'الدوام الموزون'" :value="r.worked_weighted" icon="fas fa-weight-scale" color="success" />
            <StatCard :label="t('shifts.attendance_rate')" :value="r.attendance_rate + '%'" icon="fas fa-chart-pie" :color="r.attendance_rate >= 90 ? 'success' : r.attendance_rate >= 70 ? 'warning' : 'danger'" />
        </div>

        <!-- Formula explainer -->
        <Card variant="cream" class="mb-5">
            <div class="text-[12px] leading-6 text-mistral-ink">
                <strong><i class="fas fa-circle-info ms-1 text-mistral-primary"></i> كيف يُحسب؟</strong><br/>
                أيام الدوام الفعلية = الحضور ({{ r.present_days }}) + الإجازات المعتمدة ({{ r.vacation_days }}) = <strong>{{ r.worked_physical }}</strong> يوم<br/>
                الغياب الفعلي = {{ r.absent_physical }} يوم — الغياب الموزون = Σ (غياب × عامل الوزن {{ r.weight_factor }}) = <strong>{{ r.absent_weighted }}</strong> يوم وزناً<br/>
                <span class="text-mistral-steel">مثال: دورية 1 عمل / 3 راحة → عامل 4 → غياب يوم واحد = 4 أيام وزناً. الإجازات تُحتسب دواماً وليست غياباً.</span>
            </div>
        </Card>

        <!-- Daily details table -->
        <Card variant="base" padding="none" class="overflow-hidden">
            <div class="px-5 py-4 border-b border-mistral-hairline-soft flex items-center justify-between">
                <h3 class="text-[14px] font-bold text-mistral-ink"><i class="fas fa-list ms-1.5 text-mistral-primary text-[12px]"></i> تفاصيل أيام الشهر ({{ r.from }} → {{ r.to }})</h3>
                <Badge :text="r.details.length + ' يوم دوام'" variant="info" />
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[13px]">
                    <thead class="bg-mistral-surface text-mistral-steel text-[12px]">
                        <tr>
                            <th class="px-4 py-2.5 text-start font-semibold">{{ t('shifts.date') }}</th>
                            <th class="px-4 py-2.5 text-center font-semibold">{{ t('shifts.status') }}</th>
                            <th class="px-4 py-2.5 text-center font-semibold">{{ t('shifts.expected_time') }}</th>
                            <th class="px-4 py-2.5 text-center font-semibold">الوزن</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-mistral-hairline-soft">
                        <tr v-for="d in r.details" :key="d.date" class="hover:bg-mistral-surface/50">
                            <td class="px-4 py-2 font-mono text-mistral-ink" dir="ltr">{{ d.date }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium" :class="statusClass(d.status)">
                                    {{ statusLabel(d.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center font-mono" dir="ltr">{{ d.expected_time || '—' }}</td>
                            <td class="px-4 py-2 text-center"><span class="inline-flex items-center justify-center min-w-[2rem] px-2 py-0.5 rounded bg-mistral-surface text-mistral-ink text-[12px] font-semibold">{{ d.weight_factor }}</span></td>
                        </tr>
                        <tr v-if="!r.details.length">
                            <td colspan="4" class="px-4 py-8 text-center text-mistral-muted">{{ t('shifts.no_data') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>
    </template>
</template>
