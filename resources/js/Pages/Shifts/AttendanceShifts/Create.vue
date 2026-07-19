<template>
  <AppLayout>
    <PageHeader :title="t('actions.create') + ' ' + t('attendance.attendance_shift')">
      <Button variant="secondary" :href="route('attendance.shifts.index')">
        {{ t('actions.back') }}
      </Button>
    </PageHeader>

    <form class="space-y-6" @submit.prevent="submit">
      <ErrorSummary :errors="form.errors" />

      <FormSection :title="t('attendance.attendance_shift')" icon="fas fa-clock" :collapsible="true" :default-open="true">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <FormInput
            v-model="form.alias"
            :label="t('attendance.fields.alias')"
            :error="form.errors.alias"
            required
            autofocus
          />

          <FormSelect
            v-model="form.cycle_unit"
            :label="t('attendance.fields.cycle_unit')"
            :options="cycleUnits"
            :error="form.errors.cycle_unit"
            required
          />

          <FormInput
            v-model="form.shift_cycle"
            :label="t('attendance.fields.shift_cycle')"
            type="number"
            :error="form.errors.shift_cycle"
            required
          />

          <FormInput
            v-model="form.frequency"
            :label="t('attendance.fields.frequency')"
            type="number"
            :error="form.errors.frequency"
          />
        </div>
      </FormSection>

      <FormSection :title="t('attendance.fields.shift_details')" icon="fas fa-cog" :collapsible="true" :default-open="true">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 mb-6">
          <FormSwitch
            v-model="form.work_weekend"
            :label="t('attendance.fields.work_weekend')"
          />
          <FormSwitch
            v-model="form.work_day_off"
            :label="t('attendance.fields.work_day_off')"
          />
        </div>

        <div class="space-y-4">
          <div
            v-for="(detail, index) in form.details"
            :key="index"
            class="rounded-lg border p-4"
          >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
              <FormSelect
                v-model="detail.day_index"
                :label="t('attendance.fields.day_index')"
                :options="daysOfWeek"
              />
              <FormInput
                v-model="detail.in_time"
                :label="t('attendance.fields.in_time')"
                type="time"
              />
              <FormInput
                v-model="detail.out_time"
                :label="t('attendance.fields.out_time')"
                type="time"
              />
              <div class="flex items-end">
                <Button
                  variant="danger"
                  size="sm"
                  type="button"
                  @click="removeDetail(index)"
                >
                  {{ t('actions.delete') }}
                </Button>
              </div>
            </div>
          </div>
        </div>
        <Button variant="secondary" type="button" @click="addDetail" class="mt-4">
          {{ t('attendance.actions.add_detail') }}
        </Button>
      </FormSection>

      <FormActions
        :save-label="t('actions.save')"
        :cancel-label="t('actions.cancel')"
        :cancel-href="route('attendance.shifts.index')"
        :saving="form.processing"
      />
    </form>
  </AppLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import { PageHeader, Button, Card, FormInput, FormSelect, FormSwitch, FormSection, FormActions, ErrorSummary } from '@/Components/ui'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const cycleUnits = [
  { value: 1, label: 'يومي' },
  { value: 2, label: 'أسبوعي' },
  { value: 3, label: 'شهري' },
]

const daysOfWeek = [
  { value: 0, label: 'الأحد' },
  { value: 1, label: 'الإثنين' },
  { value: 2, label: 'الثلاثاء' },
  { value: 3, label: 'الأربعاء' },
  { value: 4, label: 'الخميس' },
  { value: 5, label: 'الجمعة' },
  { value: 6, label: 'السبت' },
]

const form = useForm({
  alias: '',
  cycle_unit: 1,
  shift_cycle: 1,
  frequency: 1,
  work_weekend: false,
  work_day_off: false,
  company_id: null,
  details: [],
})

const addDetail = () => {
  form.details.push({
    day_index: form.details.length,
    in_time: '08:00',
    out_time: '17:00',
    time_interval_id: null,
  })
}

const removeDetail = (index) => {
  form.details.splice(index, 1)
}

const submit = () => {
  form.post(route('attendance.shifts.store'))
}
</script>
