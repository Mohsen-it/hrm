<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { FormModal, FormSelect, FormInput, FormTextarea, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    user: { type: Object, required: true },
    types: { type: Array, required: true },
    initialTypeId: { type: Number, default: null },
});

const { t } = useTranslations();
const isOpen = ref(false);

const form = useForm({
    user_id: props.user.id,
    vacation_type_id: props.initialTypeId || '',
    year: new Date().getFullYear(),
    days_delta: 0,
    notes: '',
});

const typeOptions = computed(() =>
    props.types.map(type => ({ value: type.id, label: type.name_ar }))
);

const submit = () => {
    form.post(route('vacations.balances.adjust'), {
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <button type="button" @click="isOpen = true" class="text-mistral-primary hover:text-mistral-primary-deep p-2">
        <i class="fas fa-pen text-[14px]"></i>
    </button>

    <FormModal v-model="isOpen" :title="t('vacations.adjust_balance')">
        <form @submit.prevent="submit" class="space-y-4">
            <FormSelect v-model="form.vacation_type_id" :label="t('vacations.vacation_type')" :options="typeOptions" required />
            <FormInput v-model="form.year" type="number" :label="t('common.year')" required />
            <FormInput v-model="form.days_delta" type="number" :label="t('vacations.days_adjustment')" required />
            <FormTextarea v-model="form.notes" :label="t('common.notes')" />
            
            <FormActions :save-label="t('common.save')" @cancel="isOpen = false" :saving="form.processing" />
        </form>
    </FormModal>
</template>
