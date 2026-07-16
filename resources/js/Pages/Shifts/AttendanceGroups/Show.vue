<template>
  <AppLayout>
    <PageHeader :title="group.name">
      <template #subtitle>{{ group.code }}</template>
      <div class="flex gap-2">
        <Button variant="secondary" :href="route('attendance.groups.index')">
          {{ t('actions.back') }}
        </Button>
        <Button variant="primary" :href="route('attendance.groups.edit', group.id)">
          {{ t('actions.edit') }}
        </Button>
      </div>
    </PageHeader>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <Card class="lg:col-span-1">
        <h3 class="mb-4 text-lg font-semibold">{{ t('attendance.fields.details') }}</h3>
        <dl class="space-y-3">
          <div>
            <dt class="text-sm text-gray-500">{{ t('attendance.fields.code') }}</dt>
            <dd class="font-medium">{{ group.code }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">{{ t('attendance.fields.name') }}</dt>
            <dd class="font-medium">{{ group.name }}</dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">{{ t('attendance.fields.status') }}</dt>
            <dd>
              <Badge :variant="group.status === 1 ? 'success' : 'danger'">
                {{ group.status === 1 ? t('general.active') : t('general.inactive') }}
              </Badge>
            </dd>
          </div>
          <div>
            <dt class="text-sm text-gray-500">{{ t('attendance.fields.employees_count') }}</dt>
            <dd class="font-medium">{{ employees.length }}</dd>
          </div>
        </dl>
      </Card>

      <Card class="lg:col-span-2">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-lg font-semibold">{{ t('attendance.fields.employees') }}</h3>
          <Button variant="primary" size="sm" @click="showAssignModal = true">
            {{ t('attendance.actions.assign_employee') }}
          </Button>
        </div>

        <DataTable
          :columns="employeeColumns"
          :data="employees"
          :empty-message="t('attendance.messages.empty_employees', 'لا يوجد موظفين في هذه الفئة.')"
        >
          <template #cell-name="{ item }">
            {{ item.employee?.name }}
          </template>

          <template #cell-employee_code="{ item }">
            {{ item.employee?.employee_code }}
          </template>

          <template #cell-actions="{ item }">
            <IconButton
              icon="trash"
              :title="t('actions.delete')"
              variant="danger"
              @click="confirmRemoveEmployee(item)"
            />
          </template>
        </DataTable>
      </Card>
    </div>

    <ConfirmDialog
      v-model="showRemoveDialog"
      :title="t('attendance.messages.delete_confirm_title')"
      :message="t('attendance.messages.remove_employee_confirm')"
      @confirm="removeEmployee"
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
import Badge from '@/Components/ui/Badge.vue'
import IconButton from '@/Components/ui/IconButton.vue'
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue'
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  group: Object,
  employees: Array,
})

const showRemoveDialog = ref(false)
const removingEmployee = ref(null)

const employeeColumns = [
  { key: 'employee_code', label: t('attendance.fields.employee_code') },
  { key: 'name', label: t('attendance.fields.employee') },
  { key: 'actions', label: t('general.actions') },
]

const confirmRemoveEmployee = (employee) => {
  removingEmployee.value = employee
  showRemoveDialog.value = true
}

const removeEmployee = () => {
  router.delete(route('attendance.groups.remove-employee', [props.group.id, removingEmployee.value.id]))
  showRemoveDialog.value = false
  removingEmployee.value = null
}
</script>
