<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { PageHeader, Button, Card, FormTextarea, FormSearchableSelect, FormSelect, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';
import VacationDateRangeFields from '../Partials/VacationDateRangeFields.vue';

const { t } = useTranslations();

const props = defineProps({
    users: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    balances: { type: Array, default: () => [] },
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

// Balance snapshot for the selected employee (lazy-loaded via ?user_id).
const selectedType = computed(() =>
    (props.types || []).find((type) => String(type.id) === String(form.vacation_type_id)) || null,
);
const activeBalance = computed(() =>
    (props.balances || []).find((b) => String(b.vacation_type_id) === String(form.vacation_type_id)) || null,
);

watch(
    () => form.user_id,
    (userId) => {
        if (userId) {
            router.reload({ only: ['balances'], data: { user_id: userId } });
        }
    },
);

function submit() {
    processing.value = true;
    errors.value = {};
    router.post(route('vacations.requests.store'), form, {
        preserveScroll: true,
        onError: (err) => { errors.value = err; },
        onFinish: () => { processing.value = false; },
    });
}


usePageTitle(t('vacations.new_request'));
</script>

<template>
    
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

            <!-- Employee balance snapshot: entitled / used / pending / remaining
                 for the selected type, plus the type policy. No extra navigation. -->
            <Card variant="cream" padding="sm">
                <div class="flex items-center gap-2 mb-3">
                    <i class="fas fa-wallet text-mistral-primary text-[14px]" aria-hidden="true"></i>
                    <h3 class="text-[14px] font-semibold text-mistral-ink">
                        {{ t('vacations.balance_card_title') }}
                    </h3>
                </div>
                <p v-if="!form.user_id" class="text-[13px] text-mistral-steel">
                    {{ t('vacations.balance_select_employee_first') }}
                </p>
                <div v-else-if="!selectedType" class="text-[13px] text-mistral-steel">
                    {{ t('vacations.vacation_type') }}: —
                </div>
                <div v-else>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="rounded-lg bg-mistral-canvas border border-mistral-hairline-soft p-3 text-center">
                            <div class="text-[20px] font-bold text-mistral-ink tabular-nums">
                                {{ activeBalance ? (activeBalance.remaining_days ?? activeBalance.days_remaining ?? 0) : (selectedType.default_days_per_year ?? 0) }}
                            </div>
                            <div class="text-[11px] text-mistral-steel mt-0.5">
                                {{ t('vacations.remaining_days') }} ({{ t('vacations.balance_days_unit') }})
                            </div>
                        </div>
                        <div class="rounded-lg bg-mistral-canvas border border-mistral-hairline-soft p-3 text-center">
                            <div class="text-[20px] font-bold text-mistral-ink tabular-nums">
                                {{ activeBalance ? (activeBalance.days_entitled ?? 0) : (selectedType.default_days_per_year ?? 0) }}
                            </div>
                            <div class="text-[11px] text-mistral-steel mt-0.5">{{ t('vacations.entitled_days') }}</div>
                        </div>
                        <div class="rounded-lg bg-mistral-canvas border border-mistral-hairline-soft p-3 text-center">
                            <div class="text-[20px] font-bold text-mistral-ink tabular-nums">
                                {{ activeBalance ? (activeBalance.days_used ?? 0) : 0 }}
                            </div>
                            <div class="text-[11px] text-mistral-steel mt-0.5">{{ t('vacations.used_days') }}</div>
                        </div>
                        <div class="rounded-lg bg-mistral-canvas border border-mistral-hairline-soft p-3 text-center">
                            <div class="text-[20px] font-bold text-mistral-ink tabular-nums">
                                {{ activeBalance ? (activeBalance.days_pending ?? 0) : 0 }}
                            </div>
                            <div class="text-[11px] text-mistral-steel mt-0.5">{{ t('vacations.pending_days') }}</div>
                        </div>
                    </div>
                    <p v-if="!activeBalance" class="text-[12px] text-mistral-steel mt-2">
                        {{ t('vacations.balance_no_record') }}
                    </p>
                    <p v-if="selectedType.max_days_per_request" class="text-[12px] text-mistral-steel mt-1">
                        {{ t('vacations.max_days_per_request') }}: {{ selectedType.max_days_per_request }}
                    </p>
                </div>
            </Card>

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
    </template>
