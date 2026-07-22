<script setup>
import { reactive, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    group: { type: Object, required: true },
});

const form = reactive({
    name: props.group.name || '',
    start_date: props.group.start_date || '',
});

const errors = ref({});
const processing = ref(false);

const errorFor = (key) => errors.value[key] || '';

function submit() {
    processing.value = true;
    errors.value = {};

    router.put(route('rotation-groups.update', props.group.id), {
        name: form.name,
        start_date: form.start_date,
    }, {
        preserveScroll: true,
        onError: (err) => {
            errors.value = err;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('shifts.edit_group')" />
    <AppLayout :title="t('shifts.edit_group')">
        <PageHeader
            :title="t('shifts.edit_group') + ': ' + group.name"
            :description="group.rotation?.name ? t('shifts.rotation') + ': ' + group.rotation.name : ''"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotation-groups.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6 max-w-3xl" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <Card variant="base" padding="lg">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.name"
                        :label="t('shifts.group_name')"
                        name="name"
                        :error="errorFor('name')"
                        required
                        autofocus
                    />
                    <FormInput
                        v-model="form.start_date"
                        :label="t('shifts.start_date')"
                        name="start_date"
                        type="date"
                        :error="errorFor('start_date')"
                    />
                </div>
            </Card>

            <FormActions
                :save-label="t('common.update')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('rotation-groups.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
