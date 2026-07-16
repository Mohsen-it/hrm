<template>
  <AppLayout>
    <PageHeader :title="t('attendance.group_schedule')">
      <template #subtitle>{{ schedule.group?.name }} - {{ schedule.shift?.alias }}</template>
      <div class="flex gap-2">
        <Button variant="secondary" :href="route('attendance.group-schedules.index')">
          {{ t('actions.back') }}
        </Button>
        <Button variant="primary" :href="route('attendance.group-schedules.edit', schedule.id)">
          {{ t('actions.edit') }}
        </Button>
      </div>
    </PageHeader>

    <Card>
      <dl class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
          <dt class="text-sm text-gray-500">{{ t('attendance.fields.group') }}</dt>
          <dd class="font-medium">{{ schedule.group?.name }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ t('attendance.fields.shift') }}</dt>
          <dd class="font-medium">{{ schedule.shift?.alias }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ t('attendance.fields.start_date') }}</dt>
          <dd class="font-medium">{{ schedule.start_date }}</dd>
        </div>
        <div>
          <dt class="text-sm text-gray-500">{{ t('attendance.fields.end_date') }}</dt>
          <dd class="font-medium">{{ schedule.end_date }}</dd>
        </div>
      </dl>
    </Card>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Button from '@/Components/ui/Button.vue'
import Card from '@/Components/ui/Card.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  schedule: Object,
})
</script>
