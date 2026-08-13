<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Alert from '@/Components/ui/Alert.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const props = defineProps({
    schedule: { type: Object, required: true },
});
</script>

<template>
    <AppLayout :title="t('attendance.group_schedule', 'جدول الفئة') + ': ' + (schedule.group?.name || '')">
        <PageHeader
            :title="t('attendance.group_schedule', 'جدول الفئة')"
            :description="schedule.group?.name + ' - ' + schedule.shift?.alias"
        >
            <template #actions>
                <Button variant="secondary" :href="route('attendance.group-schedules.index')">
                    {{ t('common.back') }}
                </Button>
                <Button variant="primary" :href="route('attendance.group-schedules.edit', schedule.id)" icon="fas fa-edit">
                    {{ t('common.edit') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" dismissible class="mb-4" />

        <Card variant="base" padding="none" class="max-w-4xl">
            <div class="p-5 sm:p-6">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <div class="flex flex-col">
                        <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('attendance.fields.group') }}</dt>
                        <dd class="text-[14px] text-mistral-ink mt-1 font-medium">{{ schedule.group?.name }}</dd>
                    </div>
                    <div class="flex flex-col">
                        <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('attendance.fields.shift') }}</dt>
                        <dd class="text-[14px] text-mistral-ink mt-1 font-medium">{{ schedule.shift?.alias }}</dd>
                    </div>
                    <div class="flex flex-col">
                        <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('attendance.fields.start_date') }}</dt>
                        <dd class="text-[14px] text-mistral-ink mt-1 font-medium">{{ schedule.start_date }}</dd>
                    </div>
                    <div class="flex flex-col">
                        <dt class="text-[12px] font-semibold text-mistral-slate uppercase tracking-wider">{{ t('attendance.fields.end_date') }}</dt>
                        <dd class="text-[14px] text-mistral-ink mt-1 font-medium">{{ schedule.end_date }}</dd>
                    </div>
                </dl>
            </div>
        </Card>
    </AppLayout>
</template>
