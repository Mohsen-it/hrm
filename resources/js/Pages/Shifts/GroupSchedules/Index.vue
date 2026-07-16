<template>
  <AppLayout>
    <PageHeader :title="t('attendance.group_schedules')">
      <Button variant="primary" :href="route('attendance.group-schedules.create')">
        {{ t('actions.create') }} {{ t('attendance.group_schedule') }}
      </Button>
    </PageHeader>

    <Card>
      <DataTable
        :columns="columns"
        :data="schedules.data"
        :empty-message="t('attendance.messages.empty_schedules', 'لا توجد جداول فئات.')"
      >
        <template #cell-group="{ item }">
          <span class="font-medium">{{ item.group?.name }}</span>
        </template>

        <template #cell-shift="{ item }">
          {{ item.shift?.alias }}
        </template>

        <template #cell-start_date="{ item }">
          {{ item.start_date }}
        </template>

        <template #cell-end_date="{ item }">
          {{ item.end_date }}
        </template>

        <template #cell-actions="{ item }">
          <div class="flex gap-2">
            <IconButton
              :href="route('attendance.group-schedules.show', item.id)"
              icon="eye"
              :title="t('actions.view')"
            />
            <IconButton
              :href="route('attendance.group-schedules.edit', item.id)"
              icon="pencil"
              :title="t('actions.edit')"
            />
            <IconButton
              icon="trash"
              :title="t('actions.delete')"
              variant="danger"
              @click="confirmDelete(item)"
            />
          </div>
        </template>
      </DataTable>

      <Pagination :data="schedules" @page-change="handlePageChange" />
    </Card>

    <ConfirmDialog
      v-model="showDeleteDialog"
      :title="t('attendance.messages.delete_confirm_title')"
      :message="t('attendance.messages.delete_confirm_message', { name: deletingSchedule?.group?.name })"
      @confirm="deleteSchedule"
    />
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Button from '@/Components/ui/Button.vue'
import Card from '@/Components/ui/Card.vue'
import DataTable from '@/Components/ui/DataTable.vue'
import IconButton from '@/Components/ui/IconButton.vue'
import Pagination from '@/Components/ui/Pagination.vue'
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  schedules: Object,
  filters: Object,
})

const showDeleteDialog = ref(false)
const deletingSchedule = ref(null)

const columns = [
  { key: 'group', label: t('attendance.fields.group') },
  { key: 'shift', label: t('attendance.fields.shift') },
  { key: 'start_date', label: t('attendance.fields.start_date') },
  { key: 'end_date', label: t('attendance.fields.end_date') },
  { key: 'actions', label: t('general.actions') },
]

const handlePageChange = (page) => {
  router.get(route('attendance.group-schedules.index', { page }))
}

const confirmDelete = (schedule) => {
  deletingSchedule.value = schedule
  showDeleteDialog.value = true
}

const deleteSchedule = () => {
  router.delete(route('attendance.group-schedules.destroy', deletingSchedule.value.id), {
    onSuccess: () => {
      showDeleteDialog.value = false
      deletingSchedule.value = null
    },
  })
}
</script>
