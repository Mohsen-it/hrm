<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    users: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
});

const form = reactive({
    user_id: '',
    shift_id: '',
    attendance_date: new Date().toISOString().slice(0, 10),
    check_in_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
    check_out_at: '',
    session_type: 'normal',
    source: 'manual',
    notes: '',
});

const errors = ref({});
const processing = ref(false);

const userOptions = computed(() => props.users.map((u) => ({
    value: u.id,
    label: `${u.name} (${u.employee_code || ''})`,
})));

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
    router.post(route('attendance.sessions.store'), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('attendance.add_new')">
        <PageHeader :title="t('attendance.add_new')" :description="t('attendance.index_description')">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('attendance.sessions.index')">
                    {{ t('attendance.actions.back') }}
                </Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit">
            <FormSelect
                v-model="form.user_id"
                :label="t('attendance.fields.user')"
                :options="userOptions"
                :placeholder="t('attendance.placeholders.select_user')"
                :error="errorFor('user_id')"
                required
            />

            <FormSelect
                v-model="form.shift_id"
                :label="t('attendance.fields.shift')"
                :options="shiftOptions"
                :placeholder="t('attendance.placeholders.select_shift')"
                :error="errorFor('shift_id')"
            />

            <FormInput
                v-model="form.attendance_date"
                :label="t('attendance.fields.attendance_date')"
                type="date"
                :error="errorFor('attendance_date')"
                required
            />

            <FormInput
                v-model="form.check_in_at"
                :label="t('attendance.fields.check_in_at')"
                type="datetime-local"
                :error="errorFor('check_in_at')"
                required
            />

            <FormInput
                v-model="form.check_out_at"
                :label="t('attendance.fields.check_out_at')"
                type="datetime-local"
                :error="errorFor('check_out_at')"
            />

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

            <div class="md:col-span-2 flex items-center gap-2 justify-end mt-2">
                <Button variant="secondary" :href="route('attendance.sessions.index')">
                    {{ t('common.cancel') }}
                </Button>
                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                    {{ t('common.save') }}
                </Button>
            </div>
        </Card>
    </AppLayout>
</template>