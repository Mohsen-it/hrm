<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button, ErrorSummary, FormActions, FormCheckbox, FormDatepicker, FormInput, FormSearchableSelect, FormSection, FormTextarea, PageHeader } from '@/Components/ui';
import JustificationScheduleWindow from './Partials/JustificationScheduleWindow.vue';
import { useJustificationSchedule } from '@/composables/useJustificationSchedule';
const props = defineProps({ request: { type: Object, required: true }, users: { type: Array, default: () => [] } });
const form = reactive({ user_id: props.request.user_id, attendance_date: props.request.attendance_date, arrival_time: props.request.arrival_time?.slice(0, 5) || '', missing_check_in: props.request.missing_check_in, missing_check_out: props.request.missing_check_out, late_arrival: props.request.late_arrival, reason: props.request.reason || '' }); const errors = ref({}); const saving = ref(false);
const { schedule, loading: scheduleLoading } = useJustificationSchedule(form);
const options = computed(() => props.users.map(u => ({ value: u.id, label: u.employee_code ? `${u.employee_code} - ${u.name}` : u.name })));
function submit() { saving.value = true; errors.value = {}; router.put(route('vacations.justifications.update', props.request.id), form, { onError: e => errors.value = e, onFinish: () => saving.value = false }); }


usePageTitle('تعديل تبرير');
</script>
<template><PageHeader title="تعديل تبرير" description="يعاد حساب التأخير تلقائياً عند الحفظ وفق بيانات الدورية الحالية."><template #actions><Button variant="secondary" :href="route('vacations.justifications.index')">عودة</Button></template></PageHeader><form class="space-y-6" @submit.prevent="submit"><ErrorSummary :errors="errors" /><FormSection title="بيانات الواقعة" icon="fas fa-clock" :default-open="true"><div class="grid grid-cols-1 gap-4 md:grid-cols-2"><FormSearchableSelect v-model="form.user_id" label="الموظف" :options="options" required :error="errors.user_id" /><FormDatepicker v-model="form.attendance_date" label="التاريخ" required :error="errors.attendance_date" /><FormInput v-model="form.arrival_time" label="الوقت" type="time" hint="اختياري." :error="errors.arrival_time" /></div><JustificationScheduleWindow class="mt-4" :schedule="schedule" :loading="scheduleLoading" /></FormSection><FormSection title="نوع التبرير" icon="fas fa-fingerprint" :default-open="true"><div class="grid gap-4 md:grid-cols-3"><FormCheckbox v-model="form.missing_check_in" label="بصمة دخول" /><FormCheckbox v-model="form.missing_check_out" label="بصمة خروج" /><FormCheckbox v-model="form.late_arrival" label="تأخر عن الدوام" :error="errors.late_arrival" /></div></FormSection><FormSection title="ملاحظات" icon="fas fa-align-right" :default-open="true"><FormTextarea v-model="form.reason" label="سبب التبرير" :rows="5" :error="errors.reason" /></FormSection><FormActions save-label="حفظ التعديلات" cancel-label="إلغاء" :cancel-href="route('vacations.justifications.index')" :saving="saving" /></form></template>
