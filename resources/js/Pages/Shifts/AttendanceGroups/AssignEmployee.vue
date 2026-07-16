<template>
  <AppLayout>
    <PageHeader :title="t('attendance.actions.assign_employee')">
      <Button variant="secondary" :href="route('attendance.groups.show', groupId)">
        {{ t('actions.back') }}
      </Button>
    </PageHeader>

    <Card>
      <form @submit.prevent="submit" class="space-y-6">
        <FormSelect
          v-model="form.emp_id"
          :label="t('attendance.fields.employee')"
          :options="users"
          :error="form.errors.emp_id"
          required
        />

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <FormSwitch
            v-model="form.enable_attendance"
            :label="t('attendance.fields.enable_attendance')"
          />
          <FormSwitch
            v-model="form.enable_schedule"
            :label="t('attendance.fields.enable_schedule')"
          />
          <FormSwitch
            v-model="form.enable_overtime"
            :label="t('attendance.fields.enable_overtime')"
          />
          <FormSwitch
            v-model="form.enable_holiday"
            :label="t('attendance.fields.enable_holiday')"
          />
          <FormSwitch
            v-model="form.enable_compensatory"
            :label="t('attendance.fields.enable_compensatory')"
          />
        </div>

        <div class="flex justify-end gap-3">
          <Button variant="secondary" :href="route('attendance.groups.show', groupId)" type="button">
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
import FormSelect from '@/Components/ui/FormSelect.vue'
import FormSwitch from '@/Components/ui/FormSwitch.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  groupId: Number,
  users: Array,
})

const form = useForm({
  emp_id: null,
  enable_attendance: true,
  enable_schedule: true,
  enable_overtime: false,
  enable_holiday: true,
  enable_compensatory: false,
})

const submit = () => {
  form.post(route('attendance.groups.assign-employee', props.groupId))
}
</script>
