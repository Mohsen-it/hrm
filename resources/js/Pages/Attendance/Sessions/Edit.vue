<script setup>
import { reactive, computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, FormInput, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    session: { type: Object, required: true },
    shifts: { type: Array, default: () => [] },
});

const form = reactive({
    shift_id: props.session.shift_id ?? '',
    attendance_date: props.session.attendance_date ?? '',
    check_in_at: props.session.check_in_at ? props.session.check_in_at.replace(' ', 'T').slice(0, 16) : '',
    check_out_at: props.session.check_out_at ? props.session.check_out_at.replace(' ', 'T').slice(0, 16) : '',
    session_type: props.session.session_type ?? 'normal',
    source: props.session.source ?? 'manual',
    notes: props.session.notes ?? '',
});

const errors = ref({});
const processing = ref(false);

const shiftOptions = computed(() => props.shifts.map((s) => ({
    value: s.id,
    label: `${s.shift_name} (${s.shift_code})`,
})));

const sessionTypeOptions = [
    { value: 'normal', label: t('attendance.session_type.normal') },
    { value: 'overtime', label: t('attendance.session_type.overtime') },
    { value: 'make_up', label: t('attendance.session_type.make_up') },
];

const sourceOptions = [
    { value: 'manual', label: t('attendance.source.manual') },
    { value: 'device', label: t('attendance.source.device') },
    { value: 'api', label: t('attendance.source.api') },
    { value: 'adms', label: t('attendance.source.adms') },
];

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    router.put(route('attendance.sessions.update', props.session.id), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('attendance.actions.edit')">
        <PageHeader :title="t('attendance.actions.edit') + ' #' + session.id" :description="t('attendance.show_description')">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('attendance.sessions.show', session.id)">
                    {{ t('attendance.actions.back') }}
                </Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('attendance.session_info')" icon="fas fa-clock" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2 text-[12px] text-mistral-steel">
                        {{ t('attendance.fields.user') }}: <strong>{{ session.user?.name || '—' }}</strong>
                        ({{ session.user?.employee_code || '' }})
                    </div>

                    <FormSelect
                        v-model="form.shift_id"
                        :label="t('attendance.fields.shift')"
                        :options="shiftOptions"
                        :placeholder="t('attendance.placeholders.select_shift')"
                        :error="errorFor('shift_id')"
                        autofocus
                    />

                    <FormInput
                        v-model="form.attendance_date"
                        :label="t('attendance.fields.attendance_date')"
                        type="date"
                        :error="errorFor('attendance_date')"
                    />

                    <FormInput
                        v-model="form.check_in_at"
                        :label="t('attendance.fields.check_in_at')"
                        type="datetime-local"
                        :error="errorFor('check_in_at')"
                    />

                    <FormInput
                        v-model="form.check_out_at"
                        :label="t('attendance.fields.check_out_at')"
                        type="datetime-local"
                        :error="errorFor('check_out_at')"
                    />
                </div>
            </FormSection>

            <FormSection :title="t('attendance.details')" icon="fas fa-info-circle" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        v-model="form.session_type"
                        :label="t('attendance.fields.session_type')"
                        :options="sessionTypeOptions"
                        :placeholder="t('attendance.placeholders.select_session_type')"
                        :error="errorFor('session_type')"
                    />

                    <FormSelect
                        v-model="form.source"
                        :label="t('attendance.fields.source')"
                        :options="sourceOptions"
                        :placeholder="t('attendance.placeholders.select_source')"
                        :error="errorFor('source')"
                    />

                    <FormInput
                        v-model="form.notes"
                        :label="t('attendance.fields.notes')"
                        type="text"
                        :error="errorFor('notes')"
                    />
                </div>
            </FormSection>

            <FormActions :save-label="t('common.update')" :cancel-label="t('common.cancel')" :cancel-href="route('attendance.sessions.show', session.id)" :saving="processing" />
        </form>
    </AppLayout>
</template>
