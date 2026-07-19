<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, FormInput, FormSelect, FormSwitch, FormTextarea, FormSection, FormActions, ErrorSummary } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    timeSchedules: { type: Array, default: () => [] },
});

const form = reactive({
    name: '',
    description: '',
    anchor_start_date: new Date().toISOString().split('T')[0],
    pattern: [],
    number_of_groups: 4,
    overtime_enabled: false,
    work_on_holidays: false,
    grace_minutes: 0,
    color: 'var(--color-mistral-primary)',
});

const patternInput = ref('');
const errors = ref({});
const processing = ref(false);

const errorFor = (key) => errors.value[key] || '';

const presets = [
    { label: 'Sunday-Thursday (Admin)', pattern: [1, 1, 1, 1, 1, 0, 0], groups: 1 },
    { label: '3 On / 9 Off', pattern: [1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0], groups: 4 },
    { label: '24 On / 24 Off', pattern: [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], groups: 4 },
    { label: '2 On / 2 Off', pattern: [1, 1, 0, 0], groups: 2 },
    { label: '5 On / 2 Off', pattern: [1, 1, 1, 1, 1, 0, 0], groups: 3 },
    { label: '1 On / 1 Off', pattern: [1, 0], groups: 2 },
    { label: '7 On / 7 Off', pattern: [1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 0, 0, 0], groups: 2 },
];

function applyPreset(preset) {
    form.pattern = [...preset.pattern];
    form.number_of_groups = preset.groups;
    patternInput.value = preset.pattern.join('');
}

const patternPreview = computed(() => {
    const pattern = form.pattern;
    if (!pattern.length) return [];

    return pattern.map((val, idx) => ({
        day: idx + 1,
        isWork: val === 1,
    }));
});

const cycleLength = computed(() => form.pattern.length);
const workDays = computed(() => form.pattern.filter(v => v === 1).length);
const restDays = computed(() => form.pattern.filter(v => v === 0).length);

watch(() => patternInput.value, (val) => {
    if (typeof val === 'string') {
        form.pattern = val.split('').map(c => c === '1' ? 1 : 0);
    }
});

function toggleDay(index) {
    const newPattern = [...form.pattern];
    newPattern[index] = newPattern[index] === 1 ? 0 : 1;
    form.pattern = newPattern;
    patternInput.value = newPattern.join('');
}

function addDay() {
    form.pattern = [...form.pattern, 0];
    patternInput.value = form.pattern.join('');
}

function removeDay() {
    if (form.pattern.length > 1) {
        form.pattern = form.pattern.slice(0, -1);
        patternInput.value = form.pattern.join('');
    }
}

const generalError = ref('');

function submit() {
    processing.value = true;
    errors.value = {};
    generalError.value = '';

    if (form.pattern.length < 2) {
        generalError.value = t('shifts.pattern_min_error') || 'Pattern must have at least 2 days.';
        processing.value = false;
        return;
    }

    router.post(route('rotations.store'), {
        ...form,
        number_of_groups: parseInt(form.number_of_groups, 10) || 1,
        grace_minutes: parseInt(form.grace_minutes, 10) || 0,
        pattern: form.pattern,
    }, {
        preserveScroll: true,
        onError: (err) => {
            errors.value = err;
            const firstKey = Object.keys(err)[0];
            if (firstKey) {
                generalError.value = Array.isArray(err[firstKey]) ? err[firstKey][0] : err[firstKey];
            }
        },
        onFinish: () => {
            processing.value = false;
        },
    });
}

const scheduleOptions = computed(() => {
    const items = Array.isArray(props.timeSchedules) ? props.timeSchedules : (props.timeSchedules?.data || []);
    return items.map((s) => ({ value: s.id, label: s.name }));
});
</script>

<template>
    <Head :title="t('shifts.add_rotation')" />
    <AppLayout :title="t('shifts.add_rotation')">
        <PageHeader
            :title="t('shifts.add_rotation')"
            :description="t('shifts.rotations_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <form class="space-y-6 max-w-4xl" @submit.prevent="submit">
            <ErrorSummary :errors="errors" />

            <div v-if="generalError" class="p-3 bg-mistral-danger/10 border border-mistral-danger/20 rounded-md text-[13px] text-mistral-danger">
                <i class="fas fa-exclamation-circle mr-1"></i>
                {{ generalError }}
            </div>

            <FormSection :title="t('shifts.basic_info')" icon="fas fa-info-circle" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.name"
                        :label="t('shifts.rotation_name')"
                        name="name"
                        :error="errorFor('name')"
                        required
                        autofocus
                    />
                    <FormInput
                        v-model="form.anchor_start_date"
                        :label="t('shifts.anchor_start_date')"
                        name="anchor_start_date"
                        type="date"
                        :error="errorFor('anchor_start_date')"
                        required
                    />
                    <div class="md:col-span-2">
                        <FormTextarea
                            v-model="form.description"
                            :label="t('shifts.description')"
                            name="description"
                            :error="errorFor('description')"
                            rows="2"
                        />
                    </div>
                </div>
            </FormSection>

            <FormSection :title="t('shifts.pattern_builder')" icon="fas fa-th" :collapsible="true" :default-open="true">
                <div class="mb-4">
                    <label class="block text-[13px] text-mistral-slate mb-2">{{ t('shifts.quick_presets') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(preset, idx) in presets"
                            :key="idx"
                            type="button"
                            class="px-3 py-1.5 text-[12px] border border-mistral-hairline rounded-md hover:border-mistral-primary hover:text-mistral-primary transition-colors"
                            @click="applyPreset(preset)"
                        >
                            {{ preset.label }}
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-[13px] text-mistral-slate mb-2">{{ t('shifts.pattern_visual') }}</label>
                    <div class="flex flex-wrap gap-1 p-3 bg-mistral-surface rounded-md">
                        <button
                            v-for="(day, idx) in form.pattern"
                            :key="idx"
                            type="button"
                            class="w-8 h-8 rounded-md text-[11px] font-medium transition-all border-2"
                            :class="day === 1
                                ? 'bg-mistral-success/10 text-mistral-success border-mistral-success/30 hover:bg-mistral-success/20'
                                : 'bg-mistral-surface text-mistral-steel border-mistral-hairline-soft hover:bg-mistral-surface'"
                            :title="`Day ${idx + 1}: ${day === 1 ? t('shifts.work_day') : t('shifts.rest_day')}`"
                            @click="toggleDay(idx)"
                        >
                            {{ idx + 1 }}
                        </button>
                        <button
                            type="button"
                            class="w-8 h-8 rounded-md text-[11px] font-medium border-2 border-dashed border-mistral-hairline text-mistral-slate hover:border-mistral-primary hover:text-mistral-primary transition-colors"
                            :title="t('shifts.add_day')"
                            @click="addDay"
                        >
                            +
                        </button>
                        <button
                            v-if="form.pattern.length > 1"
                            type="button"
                            class="w-8 h-8 rounded-md text-[11px] font-medium border-2 border-dashed border-mistral-danger/30 text-mistral-danger/60 hover:border-mistral-danger hover:text-mistral-danger transition-colors"
                            :title="t('shifts.remove_day')"
                            @click="removeDay"
                        >
                            -
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <Card variant="stat" padding="sm">
                        <p class="text-[11px] text-mistral-slate uppercase">{{ t('shifts.cycle_length') }}</p>
                        <p class="text-[20px] font-bold text-mistral-ink">{{ cycleLength }}</p>
                    </Card>
                    <Card variant="stat" padding="sm">
                        <p class="text-[11px] text-mistral-slate uppercase">{{ t('shifts.work_days_count') }}</p>
                        <p class="text-[20px] font-bold text-mistral-success">{{ workDays }}</p>
                    </Card>
                    <Card variant="stat" padding="sm">
                        <p class="text-[11px] text-mistral-slate uppercase">{{ t('shifts.rest_days_count') }}</p>
                        <p class="text-[20px] font-bold text-mistral-steel">{{ restDays }}</p>
                    </Card>
                    <div>
                        <FormInput
                            v-model="form.number_of_groups"
                            :label="t('shifts.number_of_groups')"
                            name="number_of_groups"
                            type="number"
                            min="1"
                            max="26"
                            :error="errorFor('number_of_groups')"
                        />
                    </div>
                </div>
            </FormSection>

            <FormSection :title="t('shifts.options')" icon="fas fa-cog" :collapsible="true" :default-open="true">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <FormSwitch v-model="form.overtime_enabled" :label="t('shifts.overtime_enabled')" name="overtime_enabled" />
                    <FormSwitch v-model="form.work_on_holidays" :label="t('shifts.work_on_holidays')" name="work_on_holidays" />
                    <FormInput
                        v-model="form.grace_minutes"
                        :label="t('shifts.grace_minutes')"
                        name="grace_minutes"
                        type="number"
                        min="0"
                        max="120"
                        :error="errorFor('grace_minutes')"
                    />
                    <FormInput
                        v-model="form.color"
                        :label="t('shifts.color')"
                        name="color"
                        type="color"
                        :error="errorFor('color')"
                    />
                </div>
            </FormSection>

            <FormActions
                :save-label="t('common.save')"
                :cancel-label="t('common.cancel')"
                :cancel-href="route('rotations.index')"
                :saving="processing"
            />
        </form>
    </AppLayout>
</template>
