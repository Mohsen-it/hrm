<template>
  <AppLayout>
    <PageHeader :title="t('attendance.attendance_shifts')">
      <Button variant="primary" :href="route('attendance.shifts.create')">
        {{ t('actions.create') }} {{ t('attendance.attendance_shift') }}
      </Button>
    </PageHeader>

    <Card>
      <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <SearchInput
          v-model="filters.search"
          :placeholder="t('attendance.filters.search')"
          @search="applyFilters"
        />
      </div>

      <DataTable
        :columns="columns"
        :data="shifts.data"
        :empty-message="t('attendance.messages.empty_shifts', 'لا توجد مناوبات.')"
      >
        <template #cell-alias="{ item }">
          <span class="font-medium">{{ item.alias }}</span>
        </template>

        <template #cell-cycle_unit="{ item }">
          {{ formatCycleUnit(item.cycle_unit) }}
        </template>

        <template #cell-shift_cycle="{ item }">
          {{ item.shift_cycle }}
        </template>

        <template #cell-details_count="{ item }">
          <Badge variant="info">{{ item.details?.length ?? 0 }}</Badge>
        </template>

        <template #cell-actions="{ item }">
          <div class="flex gap-2">
            <IconButton
              :href="route('attendance.shifts.show', item.id)"
              icon="eye"
              :title="t('actions.view')"
            />
            <IconButton
              :href="route('attendance.shifts.edit', item.id)"
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

      <Pagination :data="shifts" @page-change="handlePageChange" />
    </Card>

    <ConfirmDialog
      v-model="showDeleteDialog"
      :title="t('attendance.messages.delete_confirm_title')"
      :message="t('attendance.messages.delete_confirm_message', { name: deletingShift?.alias })"
      @confirm="deleteShift"
    />
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import PageHeader from '@/Components/ui/PageHeader.vue'
import Button from '@/Components/ui/Button.vue'
import Card from '@/Components/ui/Card.vue'
import DataTable from '@/Components/ui/DataTable.vue'
import SearchInput from '@/Components/ui/SearchInput.vue'
import Badge from '@/Components/ui/Badge.vue'
import IconButton from '@/Components/ui/IconButton.vue'
import Pagination from '@/Components/ui/Pagination.vue'
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  shifts: Object,
  filters: Object,
})

const filters = reactive({ search: props.filters?.search || '' })
const showDeleteDialog = ref(false)
const deletingShift = ref(null)

const columns = [
  { key: 'alias', label: t('attendance.fields.alias') },
  { key: 'cycle_unit', label: t('attendance.fields.cycle_unit') },
  { key: 'shift_cycle', label: t('attendance.fields.shift_cycle') },
  { key: 'details_count', label: t('attendance.fields.details') },
  { key: 'actions', label: t('general.actions') },
]

const formatCycleUnit = (unit) => {
  const units = { 1: 'يومي', 2: 'أسبوعي', 3: 'شهري' }
  return units[unit] || unit
}

const applyFilters = () => {
  router.get(route('attendance.shifts.index'), { search: filters.search }, { preserveState: true })
}

const handlePageChange = (page) => {
  router.get(route('attendance.shifts.index', { page, search: filters.search }))
}

const confirmDelete = (shift) => {
  deletingShift.value = shift
  showDeleteDialog.value = true
}

const deleteShift = () => {
  router.delete(route('attendance.shifts.destroy', deletingShift.value.id), {
    onSuccess: () => {
      showDeleteDialog.value = false
      deletingShift.value = null
    },
  })
}
</script>
