<template>
  <AppLayout>
    <PageHeader :title="t('actions.edit') + ' ' + t('attendance.attendance_group')">
      <Button variant="secondary" :href="route('attendance.groups.index')">
        {{ t('actions.back') }}
      </Button>
    </PageHeader>

    <Card>
      <form @submit.prevent="submit" class="space-y-6">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
          <FormInput
            v-model="form.code"
            :label="t('attendance.fields.code')"
            :error="form.errors.code"
            required
          />

          <FormInput
            v-model="form.name"
            :label="t('attendance.fields.name')"
            :error="form.errors.name"
            required
          />
        </div>

        <div class="flex justify-end gap-3">
          <Button variant="secondary" :href="route('attendance.groups.index')" type="button">
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
import { useTranslations } from '@/composables/useTranslations'

const { t } = useTranslations()

const props = defineProps({
  group: Object,
})

const form = useForm({
  code: props.group.code,
  name: props.group.name,
})

const submit = () => {
  form.put(route('attendance.groups.update', props.group.id))
}
</script>
