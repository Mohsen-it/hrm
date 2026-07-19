<script setup>
import { reactive, ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSelect, FormTextarea, Alert, EmptyState, ErrorSummary, FormSection, FormActions } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    settings: { type: Array, default: () => [] },
});

const flashSuccess = computed(() => page.props.flash?.success);

const draft = reactive({});
const processing = ref(false);

for (const s of props.settings) {
    if (!(s.id in draft)) {
        draft[s.id] = s.value ?? '';
    }
}

function displayName(s) {
    return s.name_ar || s.name_en || s.key;
}

const booleanOptions = [
    { value: 1, label: t('settings.true_value') },
    { value: 0, label: t('settings.false_value') },
];

function saveAll() {
    processing.value = true;
    const settings = props.settings.map((s) => ({
        key: s.key,
        value: s.id in draft ? draft[s.id] : s.value,
        type: s.type,
    }));
    router.post(route('settings.bulk-update'), { settings }, {
        preserveScroll: true,
        onFinish: () => { processing.value = false; },
    });
}
</script>

<template>
    <AppLayout :title="t('settings.attendance_settings')">
        <PageHeader :title="t('settings.attendance_settings')" :description="t('settings.index_description')">
            <template #actions>
                <Button variant="secondary" :href="route('settings.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <EmptyState
            v-if="settings.length === 0"
            icon="fas fa-fingerprint"
            :title="t('settings.no_settings')"
        />

        <form v-else class="space-y-6" @submit.prevent="saveAll">
            <ErrorSummary :errors="{}" />

            <FormSection :title="t('settings.attendance_settings')" :description="t('settings.index_description')">
                <div class="space-y-4">
                    <div v-for="s in settings" :key="s.id" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
                        <div class="md:col-span-1">
                            <label class="block text-[12px] font-medium text-mistral-steel mb-1">{{ displayName(s) }}</label>
                            <p class="text-[11px] text-mistral-stone font-mono">{{ s.key }} · {{ s.type }}</p>
                            <p v-if="s.description" class="text-[11px] mt-1 text-mistral-stone">{{ s.description }}</p>
                        </div>
                        <div class="md:col-span-2">
                            <FormInput
                                v-if="s.type === 'string' || s.type === null"
                                v-model="draft[s.id]"
                                type="text"
                            />
                            <FormInput
                                v-else-if="s.type === 'int' || s.type === 'integer'"
                                v-model="draft[s.id]"
                                type="number"
                            />
                            <FormInput
                                v-else-if="s.type === 'float'"
                                v-model="draft[s.id]"
                                type="number"
                                step="any"
                            />
                            <FormSelect
                                v-else-if="s.type === 'bool' || s.type === 'boolean'"
                                v-model="draft[s.id]"
                                :options="booleanOptions"
                            />
                            <FormTextarea
                                v-else-if="s.type === 'json' || s.type === 'array'"
                                v-model="draft[s.id]"
                                :rows="4"
                            />
                            <FormInput
                                v-else
                                v-model="draft[s.id]"
                                type="text"
                            />
                        </div>
                    </div>
                </div>
            </FormSection>

            <FormActions
                :save-label="t('settings.save_all')"
                :cancel-label="t('common.back')"
                :cancel-href="route('settings.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
