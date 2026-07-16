<script setup>
import { reactive, ref, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormSwitch from '@/Components/ui/FormSwitch.vue';
import FormTextarea from '@/Components/ui/FormTextarea.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    rotation: { type: Object, required: true },
});

const form = reactive({
    name: props.rotation.name || '',
    description: props.rotation.description || '',
    anchor_start_date: props.rotation.anchor_start_date || '',
    pattern: props.rotation.pattern || [],
    number_of_groups: props.rotation.number_of_groups || 1,
    overtime_enabled: props.rotation.overtime_enabled || false,
    work_on_holidays: props.rotation.work_on_holidays || false,
    grace_minutes: props.rotation.grace_minutes || 0,
    color: props.rotation.color || '#fa520f',
});

const errors = ref({});
const processing = ref(false);
const generalError = ref('');

const errorFor = (key) => errors.value[key] || '';

const cycleLength = computed(() => form.pattern.length);
const workDays = computed(() => form.pattern.filter(v => v === 1).length);
const restDays = computed(() => form.pattern.filter(v => v === 0).length);

function toggleDay(index) {
    const newPattern = [...form.pattern];
    newPattern[index] = newPattern[index] === 1 ? 0 : 1;
    form.pattern = newPattern;
}

function addDay() {
    form.pattern = [...form.pattern, 0];
}

function removeDay() {
    if (form.pattern.length > 1) {
        form.pattern = form.pattern.slice(0, -1);
    }
}

function submit() {
    processing.value = true;
    errors.value = {};
    generalError.value = '';

    router.put(route('rotations.update', props.rotation.id), {
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
</script>

<template>
    <Head :title="t('shifts.edit_rotation')" />
    <AppLayout :title="t('shifts.edit_rotation')">
        <PageHeader
            :title="t('shifts.edit_rotation') + ': ' + rotation.name"
            :description="t('shifts.rotations_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.show', rotation.id)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="md" as="form" @submit.prevent="submit" class="max-w-4xl">
            <div v-if="generalError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-[13px] text-red-700">
                <i class="fas fa-exclamation-circle mr-1"></i>
                {{ generalError }}
            </div>
            <section>
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.basic_info') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <FormInput
                        v-model="form.name"
                        :label="t('shifts.rotation_name')"
                        name="name"
                        :error="errorFor('name')"
                        required
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
            </section>

            <section class="mt-6">
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.pattern_builder') }}</h3>

                <div class="mb-4">
                    <label class="block text-[13px] text-mistral-slate mb-2">{{ t('shifts.pattern_visual') }}</label>
                    <div class="flex flex-wrap gap-1 p-3 bg-mistral-surface rounded-md">
                        <button
                            v-for="(day, idx) in form.pattern"
                            :key="idx"
                            type="button"
                            class="w-8 h-8 rounded-md text-[11px] font-medium transition-all border-2"
                            :class="day === 1
                                ? 'bg-green-100 text-green-700 border-green-300 hover:bg-green-200'
                                : 'bg-gray-100 text-gray-500 border-gray-200 hover:bg-gray-200'"
                            :title="`Day ${idx + 1}: ${day === 1 ? t('shifts.work_day') : t('shifts.rest_day')}`"
                            @click="toggleDay(idx)"
                        >
                            {{ idx + 1 }}
                        </button>
                        <button
                            type="button"
                            class="w-8 h-8 rounded-md text-[11px] font-medium border-2 border-dashed border-mistral-hairline text-mistral-slate hover:border-mistral-primary hover:text-mistral-primary transition-colors"
                            @click="addDay"
                        >
                            +
                        </button>
                        <button
                            v-if="form.pattern.length > 1"
                            type="button"
                            class="w-8 h-8 rounded-md text-[11px] font-medium border-2 border-dashed border-red-300 text-red-400 hover:border-red-500 hover:text-red-500 transition-colors"
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
                        <p class="text-[20px] font-bold text-green-600">{{ workDays }}</p>
                    </Card>
                    <Card variant="stat" padding="sm">
                        <p class="text-[11px] text-mistral-slate uppercase">{{ t('shifts.rest_days_count') }}</p>
                        <p class="text-[20px] font-bold text-gray-500">{{ restDays }}</p>
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
            </section>

            <section class="mt-6">
                <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.options') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 p-4 bg-mistral-surface rounded-md">
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
            </section>

            <div class="mt-6 flex items-center justify-start gap-2">
                <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                    {{ t('common.update') }}
                </Button>
                <Button variant="secondary" :href="route('rotations.show', rotation.id)">
                    {{ t('common.cancel') }}
                </Button>
            </div>
        </Card>
    </AppLayout>
</template>
