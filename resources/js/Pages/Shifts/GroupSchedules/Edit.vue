<template>
  <AppLayout>
    <PageHeader :title="t('actions.edit') + ' ' + t('attendance.group_schedule')">
      <Button variant="secondary" :href="route('attendance.group-schedules.index')">
        {{ t('actions.back') }}
      </Button>
    </PageHeader>

    <Card>
      <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <FormSelect
            v-model="form.group_id"
            :label="t('attendance.fields.group')"
            :options="groups"
            :error="form.errors.group_id"
            required
          />

          <FormSelect
            v-model="form.shift_id"
            :label="t('attendance.fields.shift')"
            :options="shifts"
            :error="form.errors.shift_id"
            required
          />

          <FormInput
            v-model="form.start_date"
            :label="t('attendance.fields.start_date')"
            type="date"
            :error="form.errors.start_date"
            required
          />

          <FormInput
            v-model="form.end_date"
            :label="t('attendance.fields.end_date')"
            type="date"
            :error="form.errors.end_date"
            required
          />
        </div>

        <div class="flex justify-end gap-3">
          <Button variant="secondary" :href="route('attendance.group-schedules.index')" type="button">
            {{ t('actions.cancel') }}
          </Button>
          <Button variant="primary" type="submit" :disabled="form.processing">
            {{ t('actions.save') }}
          </Button>
        </div>
      </form>
    </Card>
  </AppLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Button from '@/Components/ui/Button.vue'
import Card from '@/Components/ui/Card.vue'
import FormInput from '@/Components/ui/FormInput.vue'
import FormSelect from '@/Components/ui/FormSelect.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  schedule: Object,
  groups: Array,
  shifts: Array,
})

const form = useForm({
  group_id: props.schedule.group_id,
  shift_id: props.schedule.shift_id,
  start_date: props.schedule.start_date,
  end_date: props.schedule.end_date,
})

const submit = () => {
  form.put(route('attendance.group-schedules.update', props.schedule.id))
}
</script>
