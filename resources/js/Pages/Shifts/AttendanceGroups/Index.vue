<template>
  <AppLayout>
    <PageHeader :title="t('attendance.attendance_groups')">
      <Button variant="primary" :href="route('attendance.groups.create')">
        {{ t('actions.create') }} {{ t('attendance.attendance_group') }}
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
        :data="groups.data"
        :empty-message="t('attendance.messages.empty_groups', 'لا توجد فئات حضور.')"
      >
        <template #cell-code="{ item }">
          <span class="font-medium">{{ item.code }}</span>
        </template>

        <template #cell-name="{ item }">
          {{ item.name }}
        </template>

        <template #cell-employees_count="{ item }">
          <Badge variant="info">{{ item.employees?.length ?? 0 }}</Badge>
        </template>

        <template #cell-status="{ item }">
          <Badge :variant="item.status === 1 ? 'success' : 'danger'">
            {{ item.status === 1 ? t('general.active') : t('general.inactive') }}
          </Badge>
        </template>

        <template #cell-actions="{ item }">
          <div class="flex gap-2">
            <IconButton
              :href="route('attendance.groups.show', item.id)"
              icon="eye"
              :title="t('actions.view')"
            />
            <IconButton
              :href="route('attendance.groups.edit', item.id)"
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

      <Pagination :data="groups" @page-change="handlePageChange" />
    </Card>

    <ConfirmDialog
      v-model="showDeleteDialog"
      :title="t('attendance.messages.delete_confirm_title')"
      :message="t('attendance.messages.delete_confirm_message', { name: deletingGroup?.name })"
      @confirm="deleteGroup"
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
  groups: Object,
  filters: Object,
})

const filters = reactive({ search: props.filters?.search || '' })
const showDeleteDialog = ref(false)
const deletingGroup = ref(null)

const columns = [
  { key: 'code', label: t('attendance.fields.code') },
  { key: 'name', label: t('attendance.fields.name') },
  { key: 'employees_count', label: t('attendance.fields.employees_count') },
  { key: 'status', label: t('attendance.fields.status') },
  { key: 'actions', label: t('general.actions') },
]

const applyFilters = () => {
  router.get(route('attendance.groups.index'), { search: filters.search }, { preserveState: true })
}

const handlePageChange = (page) => {
  router.get(route('attendance.groups.index', { page, search: filters.search }))
}

const confirmDelete = (group) => {
  deletingGroup.value = group
  showDeleteDialog.value = true
}

const deleteGroup = () => {
  router.delete(route('attendance.groups.destroy', deletingGroup.value.id), {
    onSuccess: () => {
      showDeleteDialog.value = false
      deletingGroup.value = null
    },
  })
}
</script>
