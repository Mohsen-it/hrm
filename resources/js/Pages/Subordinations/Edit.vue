<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, FormInput, FormTextarea, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    subordination: { type: Object, required: true },
    statusOptions: { type: Array, default: () => [] },
});

const form = reactive({
    _method: 'PUT',
    code: props.subordination.code || '',
    name_ar: props.subordination.name_ar || '',
    name_en: props.subordination.name_en || '',
    description: props.subordination.description || '',
    status: Number(props.subordination.status ?? 1),
    sort_order: Number(props.subordination.sort_order ?? 0),
});

const errors = ref({});
const processing = ref(false);

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('subordinations.update', props.subordination.id), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('subordinations.edit_subordination')">
        <PageHeader
            :title="t('subordinations.edit_subordination')"
            :description="subordination.name_ar"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('subordinations.index')">
                    {{ t('common.back') }}
                </Button>
            </template>
        </PageHeader>

        <ErrorSummary :errors="errors" />

        <form class="space-y-6" @submit.prevent="submit">
            <FormSection
                :title="t('subordinations.title')"
                icon="fas fa-map-location-dot"
                :collapsible="true"
                :default-open="true"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.code"
                        :label="t('subordinations.code')"
                        name="code"
                        required
                        :error="errorFor('code')"
                    />
                    <FormInput
                        v-model="form.name_ar"
                        :label="t('subordinations.name_ar')"
                        name="name_ar"
                        required
                        :error="errorFor('name_ar')"
                    />
                    <FormInput
                        v-model="form.name_en"
                        :label="t('subordinations.name_en')"
                        name="name_en"
                        :error="errorFor('name_en')"
                    />
                    <FormInput
                        v-model="form.sort_order"
                        :label="t('subordinations.sort_order')"
                        name="sort_order"
                        type="number"
                        min="0"
                        :error="errorFor('sort_order')"
                    />
                    <FormSelect
                        v-model="form.status"
                        :label="t('subordinations.status')"
                        name="status"
                        :options="props.statusOptions"
                        :error="errorFor('status')"
                    />
                </div>
            </FormSection>

            <FormSection
                :title="t('subordinations.description')"
                icon="fas fa-align-left"
                :collapsible="true"
                :default-open="true"
            >
                <FormTextarea
                    v-model="form.description"
                    :label="t('subordinations.description')"
                    name="description"
                    :rows="4"
                    :error="errorFor('description')"
                />
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('subordinations.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
