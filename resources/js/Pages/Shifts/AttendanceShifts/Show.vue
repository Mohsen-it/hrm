<template>
  <AppLayout>
    <PageHeader :title="shift.alias">
      <template #subtitle>{{ formatCycleUnit(shift.cycle_unit) }} - {{ shift.shift_cycle }}</template>
      <div class="flex gap-2">
        <Button variant="secondary" :href="route('attendance.shifts.index')">
          {{ t('actions.back') }}
        </Button>
        <Button variant="primary" :href="route('attendance.shifts.edit', shift.id)">
          {{ t('actions.edit') }}
        </Button>
      </div>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-1">
        <h3 class="mb-4 text-lg font-semibold">{{ t('attendance.fields.details') }}</h3>
        <dl class="space-y-3">
          <div>
            <dt class="text-sm text-gray-500">{{ t('attendance.fields.alias') }}</dt>
            <dd class="font-medium">{{ shift.alias }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">{{ t('attendance.fields.cycle_unit') }}</dt>
            <dd class="font-medium">{{ formatCycleUnit(shift.cycle_unit) }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">{{ t('attendance.fields.shift_cycle') }}</dt>
            <dd class="font-medium">{{ shift.shift_cycle }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">{{ t('attendance.fields.work_weekend') }}</dt>
            <dd>
              <Badge :variant="shift.work_weekend ? 'success' : 'secondary'">
                {{ shift.work_weekend ? t('general.yes') : t('general.no') }}
              </Badge>
            </dd>
          </div>
        </dl>
      </Card>

      <Card class="lg:col-span-2">
        <h3 class="mb-4 text-lg font-semibold">{{ t('attendance.fields.shift_details') }}</h3>
        <DataTable
          :columns="detailColumns"
          :data="shift.details || []"
          :empty-message="t('attendance.messages.empty_details', 'لا توجد تفاصيل.')"
        >
          <template #cell-day_index="{ item }">
            {{ getDayName(item.day_index) }}
          </template>
        </DataTable>
      </Card>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Button from '@/Components/ui/Button.vue'
import Card from '@/Components/ui/Card.vue'
import DataTable from '@/Components/ui/DataTable.vue'
import Badge from '@/Components/ui/Badge.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  shift: Object,
})

const detailColumns = [
  { key: 'day_index', label: t('attendance.fields.day_index') },
  { key: 'in_time', label: t('attendance.fields.in_time') },
  { key: 'out_time', label: t('attendance.fields.out_time') },
]

const formatCycleUnit = (unit) => {
  const units = { 1: 'يومي', 2: 'أسبوعي', 3: 'شهري' }
  return units[unit] || unit
}

const getDayName = (index) => {
  const days = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']
  return days[index] || index
}
</script>
