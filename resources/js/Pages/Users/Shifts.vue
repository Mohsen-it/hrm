<script setup>
import { reactive, ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSelect, FormCheckbox, Badge, EmptyState, IconButton, Alert, ErrorSummary, FormSection, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    user: { type: Object, required: true },
    shifts: { type: Array, default: () => [] },
});

const form = reactive({
    _method: 'POST',
    shifts: [],
});

const errors = ref({});
const processing = ref(false);

const initialShifts = computed(() => {
    return (props.user.shifts || []).map((s) => ({
        shift_id: s.id,
        effective_from: s.pivot?.effective_from || '',
        effective_to: s.pivot?.effective_to || '',
        is_primary: !!s.pivot?.is_primary,
    }));
});

form.shifts = initialShifts.value.map((s) => ({ ...s }));

const availableShifts = computed(() => {
    const assigned = new Set(form.shifts.map((s) => Number(s.shift_id)));
    return props.shifts.filter((s) => !assigned.has(Number(s.id)));
});

const errorFor = (key) => errors.value[key] || '';

function addShift() {
    if (availableShifts.value.length === 0) return;
    form.shifts.push({
        shift_id: availableShifts.value[0].id,
        effective_from: '',
        effective_to: '',
        is_primary: form.shifts.length === 0,
    });
}

function removeShift(index) {
    const wasPrimary = form.shifts[index].is_primary;
    form.shifts.splice(index, 1);
    if (wasPrimary && form.shifts.length > 0) {
        form.shifts[0].is_primary = true;
    }
}

function ensureSinglePrimary(index) {
    if (form.shifts[index].is_primary) {
        form.shifts.forEach((s, i) => {
            if (i !== index) s.is_primary = false;
        });
    }
}

function submit() {
    processing.value = true;
    errors.value = {};

    router.post(route('users.shifts.update', props.user.id), {
        shifts: form.shifts,
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

const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <AppLayout :title="t('users.manage_shifts')">
        <PageHeader
            :title="t('users.manage_shifts')"
            :description="`${user.name} — ${t('users.shifts_description')}`"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('users.show', user.id)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <form class="space-y-6" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <FormSection :title="t('users.shifts')" :description="t('users.shifts_description')">
                <div class="flex items-center justify-between mb-4">
                    <Button
                        variant="primary"
                        icon="fas fa-plus"
                        :disabled="availableShifts.length === 0"
                        @click="addShift"
                    >
                        {{ t('users.add_shift') }}
                    </Button>
                </div>

                <EmptyState
                    v-if="form.shifts.length === 0"
                    :title="t('users.no_shifts_assigned')"
                />

                <div v-else class="space-y-3">
                    <div
                        v-for="(entry, idx) in form.shifts"
                        :key="idx"
                        class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end p-3 rounded-lg border border-mistral-hairline-soft bg-mistral-surface/30"
                    >
                        <div class="md:col-span-4">
                            <FormSelect
                                v-model="entry.shift_id"
                                :label="t('users.shift')"
                                :name="`shifts[${idx}][shift_id]`"
                                :options="(props.shifts).map((s) => ({ value: s.id, label: `${s.shift_code} — ${s.shift_name}` }))"
                                required
                            />
                        </div>
                        <div class="md:col-span-3">
                            <FormInput
                                v-model="entry.effective_from"
                                :label="t('users.effective_from')"
                                :name="`shifts[${idx}][effective_from]`"
                                type="date"
                            />
                        </div>
                        <div class="md:col-span-3">
                            <FormInput
                                v-model="entry.effective_to"
                                :label="t('users.effective_to')"
                                :name="`shifts[${idx}][effective_to]`"
                                type="date"
                            />
                        </div>
                        <div class="md:col-span-1 flex items-center gap-2 pb-2">
                            <FormCheckbox
                                :model-value="entry.is_primary"
                                :label="t('users.is_primary')"
                                @update:model-value="(v) => { entry.is_primary = v; ensureSinglePrimary(idx); }"
                            />
                        </div>
                        <div class="md:col-span-1 flex items-center justify-end">
                            <IconButton
                                icon="fas fa-trash"
                                :aria-label="t('users.remove_shift')"
                                variant="danger"
                                size="sm"
                                @click="removeShift(idx)"
                            />
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('users.show', user.id)"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
