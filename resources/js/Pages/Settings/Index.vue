<script setup>
import { ref, computed, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Badge, Button, Card, FormInput, FormSelect, FormTextarea, Alert } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    groups: { type: Array, default: () => [] },
    settingsByGroup: { type: Object, default: () => ({}) },
});

const activeGroup = ref(props.groups?.[0] || 'general');
const processing = ref(false);
const flashSuccess = computed(() => page.props.flash?.success);

const draft = reactive({});

function ensureDraft(setting) {
    if (!(setting.id in draft)) {
        draft[setting.id] = setting.value ?? '';
    }
}

function displayName(setting) {
    if (setting.name_ar) return setting.name_ar;
    if (setting.name_en) return setting.name_en;
    return setting.key;
}

const boolOptions = computed(() => [
    { value: 1, label: t('settings.true_value') },
    { value: 0, label: t('settings.false_value') },
]);

const activeSettings = computed(() => (props.settingsByGroup?.[activeGroup.value] || []).map((s) => {
    ensureDraft(s);
    return s;
}));

function inputTypeFor(setting) {
    switch (setting.type) {
        case 'int':
        case 'integer':
            return 'number';
        case 'float':
            return 'number';
        case 'json':
        case 'array':
            return 'textarea';
        default:
            return 'text';
    }
}

function saveGroup() {
    processing.value = true;
    const settings = (props.settingsByGroup?.[activeGroup.value] || []).map((s) => ({
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
    <AppLayout :title="t('settings.title')">
        <PageHeader :title="t('settings.title')" :description="t('settings.index_description')">
            <template #actions>
                <Button variant="secondary" icon="fas fa-cog" :href="route('settings.general')">
                    {{ t('settings.general_settings') }}
                </Button>
                <Button variant="secondary" icon="fas fa-fingerprint" :href="route('settings.attendance')">
                    {{ t('settings.attendance_settings') }}
                </Button>
            </template>
        </PageHeader>

        <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <Card variant="base" padding="none" class="lg:col-span-1">
                <div class="p-4">
                    <h3 class="text-[12px] font-semibold text-mistral-steel uppercase tracking-wider px-2 py-2">
                        {{ t('settings.group') }}
                    </h3>
                    <ul class="space-y-1">
                        <li v-for="g in groups" :key="g">
                            <button
                                type="button"
                                :class="[
                                    'w-full text-start px-3 py-2 rounded-md text-[13px] transition-colors',
                                    activeGroup === g
                                        ? 'bg-mistral-primary text-mistral-on-primary'
                                        : 'hover:bg-mistral-surface text-mistral-ink',
                                ]"
                                @click="activeGroup = g"
                            >
                                <i class="fas fa-folder-open text-[11px] me-1"></i>
                                {{ t('settings.group_' + g) }}
                            </button>
                        </li>
                    </ul>
                </div>
            </Card>

            <Card variant="base" padding="none" class="lg:col-span-3">
                <div class="p-5 sm:p-6">
                    <div v-if="activeSettings.length === 0" class="text-center py-12 text-[13px] text-mistral-steel">
                        {{ t('settings.no_settings') }}
                    </div>
                    <form v-else @submit.prevent="saveGroup">
                        <div
                            v-for="s in activeSettings"
                            :key="s.id"
                            class="grid grid-cols-1 md:grid-cols-12 gap-3 py-3 border-b border-mistral-hairline items-start"
                        >
                            <div class="md:col-span-3">
                                <div class="font-mono text-[12px] text-mistral-steel">{{ s.key }}</div>
                                <div class="text-[13px] text-mistral-ink mt-1">{{ displayName(s) }}</div>
                            </div>
                            <div class="md:col-span-2">
                                <Badge :text="s.type || 'string'" variant="info" />
                            </div>
                            <div class="md:col-span-7">
                                <FormInput
                                    v-if="inputTypeFor(s) === 'text'"
                                    v-model="draft[s.id]"
                                    type="text"
                                />
                                <FormInput
                                    v-else-if="inputTypeFor(s) === 'number'"
                                    v-model="draft[s.id]"
                                    type="number"
                                    :step="s.type === 'float' ? 'any' : '1'"
                                />
                                <FormSelect
                                    v-else-if="s.type === 'bool' || s.type === 'boolean'"
                                    v-model="draft[s.id]"
                                    :options="boolOptions"
                                />
                                <FormTextarea
                                    v-else-if="inputTypeFor(s) === 'textarea'"
                                    v-model="draft[s.id]"
                                    :rows="4"
                                    :placeholder="t('settings.json_placeholder')"
                                />
                                <FormInput
                                    v-else
                                    v-model="draft[s.id]"
                                    type="text"
                                />
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-end gap-2">
                            <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                                {{ t('settings.save_all') }}
                            </Button>
                        </div>
                    </form>
                </div>
            </Card>
        </div>
    </AppLayout>
</template>
