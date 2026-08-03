<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, FormTextarea, FormSearchableSelect, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';
import VacationDateRangeFields from '../Partials/VacationDateRangeFields.vue';

const { t } = useTranslations();

const props = defineProps({
    users: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});

const form = reactive({
    user_id: '',
    vacation_type_id: '',
    start_date: '',
    end_date: '',
    days_count: '',
    reason: '',
});

const errors = ref({});
const processing = ref(false);

const userOptions = computed(() =>
    (props.users || []).map((u) => ({
        value: u.id,
        label: u.employee_code ? `${u.employee_code} - ${u.name}` : u.name,
    })),
);

const typeOptions = computed(() =>
    (props.types || []).map((type) => ({
        value: type.id,
        label: type.code ? `${type.code} - ${type.name_ar}` : type.name_ar,
    })),
);

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('vacations.requests.store'), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('vacations.new_request')">
        <PageHeader :title="t('vacations.new_request')" :description="t('vacations.requests_description')">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('vacations.requests.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('vacations.request_info')" icon="fas fa-paper-plane" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSearchableSelect
                        v-model="form.user_id"
                        :label="t('vacations.employee')"
                        name="user_id"
                        :options="userOptions"
                        required
                        :error="errorFor('user_id')"
                    />
                    <FormSelect
                        v-model="form.vacation_type_id"
                        :label="t('vacations.vacation_type')"
                        name="vacation_type_id"
                        :options="typeOptions"
                        required
                        :error="errorFor('vacation_type_id')"
                    />
                    <VacationDateRangeFields
                        v-model:start-date="form.start_date"
                        v-model:end-date="form.end_date"
                        v-model:days-count="form.days_count"
                        :errors="errors"
                        :labels="{ startDate: t('vacations.start_date'), daysCount: t('vacations.total_days'), endDate: t('vacations.end_date') }"
                    />
                </div>
            </FormSection>

            <FormSection :title="t('vacations.additional')" icon="fas fa-align-left" :collapsible="true" :default-open="true">
                <FormTextarea
                    v-model="form.reason"
                    :label="t('vacations.reason')"
                    name="reason"
                    :rows="4"
                    :error="errorFor('reason')"
                />
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('vacations.requests.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
