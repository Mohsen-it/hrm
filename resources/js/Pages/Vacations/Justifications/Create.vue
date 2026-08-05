<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Button, ErrorSummary, FormActions, FormCheckbox, FormDatepicker, FormInput, FormSearchableSelect, FormSection, FormTextarea, PageHeader } from '@/Components/ui';
import JustificationScheduleWindow from './Partials/JustificationScheduleWindow.vue';
import { useJustificationSchedule } from '@/composables/useJustificationSchedule';
const props = defineProps({ users: { type: Array, default: () => [] } });
const form = reactive({ user_id: '', attendance_date: '', arrival_time: '', missing_check_in: false, missing_check_out: false, late_arrival: false, reason: '' }); const errors = ref({}); const saving = ref(false);
const { schedule, loading: scheduleLoading } = useJustificationSchedule(form);
const options = computed(() => props.users.map(u => ({ value: u.id, label: u.employee_code ? `${u.employee_code} - ${u.name}` : u.name })));
function submit() { saving.value = true; errors.value = {}; router.post(route('vacations.justifications.store'), form, { onError: e => errors.value = e, onFinish: () => saving.value = false }); }
</script>

<template>
  <AppLayout title="تسجيل تبرير"><PageHeader title="تسجيل تبرير" description="يُحسب التأخير تلقائياً من وقت الوصول ونافذة الدخول المعتمدة للدورية."><template #actions><Button variant="secondary" :href="route('vacations.justifications.index')">عودة</Button></template></PageHeader>
  <form class="space-y-6" @submit.prevent="submit"><ErrorSummary :errors="errors" />
    <FormSection title="بيانات الواقعة" icon="fas fa-clock" :default-open="true"><div class="grid grid-cols-1 gap-4 md:grid-cols-2"><FormSearchableSelect v-model="form.user_id" label="الموظف" :options="options" required :error="errors.user_id" /><FormDatepicker v-model="form.attendance_date" label="التاريخ" required :error="errors.attendance_date" /><FormInput v-model="form.arrival_time" label="الوقت" type="time" hint="اختياري." :error="errors.arrival_time" /></div><JustificationScheduleWindow class="mt-4" :schedule="schedule" :loading="scheduleLoading" /></FormSection>
    <FormSection title="نوع التبرير" icon="fas fa-fingerprint" :default-open="true"><div class="grid gap-4 md:grid-cols-3"><FormCheckbox v-model="form.missing_check_in" label="بصمة دخول" hint="نسيان أو تعذر تسجيل الحضور" /><FormCheckbox v-model="form.missing_check_out" label="بصمة خروج" hint="نسيان أو تعذر تسجيل الانصراف" /><FormCheckbox v-model="form.late_arrival" label="تأخر عن الدوام" hint="يحتسب بعد هامش السماح للدورية" :error="errors.late_arrival" /></div></FormSection>
    <FormSection title="ملاحظات" icon="fas fa-align-right" :default-open="true"><FormTextarea v-model="form.reason" label="سبب التبرير" :rows="5" :error="errors.reason" /></FormSection>
    <FormActions save-label="تسجيل التبرير" cancel-label="إلغاء" :cancel-href="route('vacations.justifications.index')" :saving="saving" /></form>
  </AppLayout>
</template>
