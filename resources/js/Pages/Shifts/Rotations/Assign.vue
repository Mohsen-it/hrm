<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { router, Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    rotations: { type: Array, default: () => [] },
    preselected_rotation_id: { type: Number, default: null },
    preselected_group_id: { type: Number, default: null },
});

const activeTab = ref('single');

const tabs = [
    { value: 'single', label: t('shifts.assign_employee') },
    { value: 'bulk', label: t('shifts.bulk_assign') },
];

function onTabChange(tab) {
    if (tab.value === 'bulk') {
        router.visit(route('rotations.assign.bulk-page'), { preserveState: false });
    }
}

const form = reactive({
    employee_id: '',
    rotation_id: props.preselected_rotation_id || '',
    rotation_group_id: props.preselected_group_id || '',
    start_date: new Date().toISOString().split('T')[0],
    end_date: '',
});

const errors = ref({});
const processing = ref(false);
const employeeSearch = ref('');
const searchResults = ref([]);
const selectedEmployee = ref(null);
const isSearching = ref(false);
const generalError = ref('');

const errorFor = (key) => errors.value[key || ''];

const rotationOptions = computed(() => {
    const items = Array.isArray(props.rotations) ? props.rotations : (props.rotations?.data || []);
    return items.map(r => ({ value: r.id, label: r.name }));
});

const selectedRotation = computed(() => {
    const items = Array.isArray(props.rotations) ? props.rotations : (props.rotations?.data || []);
    const rid = Number(form.rotation_id);
    return items.find(r => Number(r.id) === rid);
});

const groupOptions = computed(() => {
    if (!selectedRotation.value || !selectedRotation.value.groups) return [];
    return selectedRotation.value.groups.map(g => ({
        value: g.id,
        label: `${g.name} (${t('shifts.offset')}: ${g.group_index})`,
    }));
});

watch(() => form.rotation_id, () => {
    form.rotation_group_id = '';
});

async function searchEmployees() {
    if (employeeSearch.value.length < 2) {
        searchResults.value = [];
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(route('rotations.search-employees', {
            search: employeeSearch.value,
        }));
        const data = await response.json();
        searchResults.value = data.employees || [];
    } catch (e) {
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
}

function selectEmployee(emp) {
    selectedEmployee.value = emp;
    form.employee_id = emp.id;
    employeeSearch.value = emp.name || emp.full_name;
    searchResults.value = [];
}

function submit() {
    processing.value = true;
    errors.value = {};
    generalError.value = '';

    router.post(route('rotations.assign.store'), {
        ...form,
        employee_id: form.employee_id ? Number(form.employee_id) : form.employee_id,
        rotation_id: form.rotation_id ? Number(form.rotation_id) : form.rotation_id,
        rotation_group_id: form.rotation_group_id ? Number(form.rotation_group_id) : form.rotation_group_id,
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
    <Head :title="t('shifts.assign_rotation')" />
    <AppLayout :title="t('shifts.assign_rotation')">
        <PageHeader
            :title="t('shifts.assign_rotation')"
            :description="t('shifts.rotation_assign_description')"
        >
            <template #actions>
                <Button variant="secondary" :href="route('rotations.index')">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="max-w-3xl">
            <Tabs :tabs="tabs" v-model="activeTab" @change="onTabChange" />

            <Card variant="base" padding="md" as="form" @submit.prevent="submit" class="mt-4">
                <div v-if="generalError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-[13px] text-red-700">
                    <i class="fas fa-exclamation-circle mr-1"></i>
                    {{ generalError }}
                </div>
                <section>
                    <h3 class="text-[14px] text-mistral-ink mb-3 font-medium">{{ t('shifts.assignment_info') }}</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] text-mistral-slate mb-1">{{ t('shifts.employee_id') }}</label>
                            <div class="relative">
                                <input
                                    v-model="employeeSearch"
                                    type="text"
                                    class="w-full border border-mistral-hairline rounded-md px-3 py-2 text-[13px] focus:outline-none focus:border-mistral-primary"
                                    :placeholder="t('shifts.search_employee_placeholder')"
                                    @input="searchEmployees"
                                />
                                <div
                                    v-if="searchResults.length > 0"
                                    class="absolute z-10 top-full left-0 right-0 mt-1 bg-white border border-mistral-hairline rounded-md shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <div
                                        v-for="emp in searchResults"
                                        :key="emp.id"
                                        class="px-3 py-2 text-[13px] cursor-pointer hover:bg-mistral-surface border-b border-mistral-hairline-soft last:border-0"
                                        @click="selectEmployee(emp)"
                                    >
                                        <span class="font-medium">{{ emp.name }}</span>
                                        <span class="text-mistral-muted mr-2">{{ emp.employee_code }}</span>
                                    </div>
                                </div>
                            </div>
                            <span v-if="selectedEmployee" class="text-[12px] text-green-600 mt-1 block">
                                <i class="fas fa-check"></i> {{ selectedEmployee.name }} ({{ selectedEmployee.employee_code }})
                            </span>
                            <span v-if="errorFor('employee_id')" class="text-[12px] text-red-500 mt-1 block">{{ errorFor('employee_id') }}</span>
                        </div>

                        <FormSelect
                            v-model="form.rotation_id"
                            :label="t('shifts.rotation')"
                            name="rotation_id"
                            :options="rotationOptions"
                            :error="errorFor('rotation_id')"
                            required
                        />

                        <FormSelect
                            v-model="form.rotation_group_id"
                            :label="t('shifts.rotation_group')"
                            name="rotation_group_id"
                            :options="groupOptions"
                            :error="errorFor('rotation_group_id')"
                            required
                        />

                        <FormInput
                            v-model="form.start_date"
                            :label="t('shifts.start_date')"
                            name="start_date"
                            type="date"
                            :error="errorFor('start_date')"
                            required
                        />

                        <FormInput
                            v-model="form.end_date"
                            :label="t('shifts.end_date_optional')"
                            name="end_date"
                            type="date"
                            :error="errorFor('end_date')"
                        />
                    </div>

                    <div v-if="selectedRotation" class="mt-4 p-4 bg-mistral-surface rounded-md">
                        <h4 class="text-[13px] font-medium text-mistral-ink mb-2">{{ t('shifts.rotation_preview') }}</h4>
                        <div class="grid grid-cols-3 gap-3 text-[12px]">
                            <div>
                                <span class="text-mistral-slate">{{ t('shifts.work_pattern') }}:</span>
                                <span class="font-mono font-medium ml-1">{{ selectedRotation.work_days_count }}+{{ selectedRotation.rest_days_count }}</span>
                            </div>
                            <div>
                                <span class="text-mistral-slate">{{ t('shifts.cycle_length') }}:</span>
                                <span class="font-medium ml-1">{{ selectedRotation.cycle_length }} {{ t('shifts.days') }}</span>
                            </div>
                            <div>
                                <span class="text-mistral-slate">{{ t('shifts.groups_count') }}:</span>
                                <span class="font-medium ml-1">{{ selectedRotation.number_of_groups }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="mt-6 flex items-center justify-start gap-2">
                    <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                        {{ t('shifts.assign_employee') }}
                    </Button>
                    <Button variant="secondary" :href="route('rotations.index')">
                        {{ t('common.cancel') }}
                    </Button>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
