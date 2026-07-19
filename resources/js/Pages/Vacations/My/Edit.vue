<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, FormInput, FormTextarea, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    request: { type: Object, required: true },
    types: { type: Array, default: () => [] },
});

const form = reactive({
    _method: 'PUT',
    vacation_type_id: props.request.vacation_type_id || '',
    start_date: props.request.start_date || '',
    end_date: props.request.end_date || '',
    reason: props.request.reason || '',
});

const errors = ref({});
const processing = ref(false);

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
    router.post(route('vacations.my.update', props.request.id), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('vacations.edit_request')">
        <PageHeader :title="t('vacations.edit_request')" :description="request.vacation_type?.name_ar">
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('vacations.my.show', request.id)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('vacations.request_info')" icon="fas fa-paper-plane" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormSelect
                        v-model="form.vacation_type_id"
                        :label="t('vacations.vacation_type')"
                        name="vacation_type_id"
                        :options="typeOptions"
                        required
                        :error="errorFor('vacation_type_id')"
                        autofocus
                    />
                    <div class="hidden md:block"></div>
                    <FormInput
                        v-model="form.start_date"
                        :label="t('vacations.start_date')"
                        name="start_date"
                        type="date"
                        required
                        :error="errorFor('start_date')"
                    />
                    <FormInput
                        v-model="form.end_date"
                        :label="t('vacations.end_date')"
                        name="end_date"
                        type="date"
                        required
                        :error="errorFor('end_date')"
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

            <FormActions :save-label="t('common.update')" :cancel-label="t('common.cancel')" :cancel-href="route('vacations.my.show', request.id)" :saving="processing" />
        </form>
    </AppLayout>
</template>
