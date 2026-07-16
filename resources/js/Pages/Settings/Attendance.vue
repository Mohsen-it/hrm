<script setup>
import { reactive, ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
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

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>

        <div class="card p-6">
            <div v-if="settings.length === 0" class="text-center py-12 text-[13px] text-[var(--color-ink-muted)]">
                {{ t('settings.no_settings') }}
            </div>
            <form v-else @submit.prevent="saveAll" class="space-y-4">
                <div v-for="s in settings" :key="s.id" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
                    <div class="md:col-span-1">
                        <label class="block text-[12px] font-medium text-[var(--color-ink-muted)] mb-1">{{ displayName(s) }}</label>
                        <p class="text-[11px] text-[var(--color-ink-subtle)] font-mono">{{ s.key }} · {{ s.type }}</p>
                        <p v-if="s.description" class="text-[11px] mt-1">{{ s.description }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <input v-if="s.type === 'string' || s.type === null" v-model="draft[s.id]" type="text" class="form-input" />
                        <input v-else-if="s.type === 'int' || s.type === 'integer'" v-model="draft[s.id]" type="number" class="form-input" />
                        <input v-else-if="s.type === 'float'" v-model="draft[s.id]" type="number" step="any" class="form-input" />
                        <select v-else-if="s.type === 'bool' || s.type === 'boolean'" v-model="draft[s.id]" class="form-input">
                            <option :value="1">{{ t('settings.true_value') }}</option>
                            <option :value="0">{{ t('settings.false_value') }}</option>
                        </select>
                        <textarea v-else-if="s.type === 'json' || s.type === 'array'" v-model="draft[s.id]" class="form-input font-mono text-[12px]" rows="4"></textarea>
                        <input v-else v-model="draft[s.id]" type="text" class="form-input" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button type="submit" variant="primary" :loading="processing" icon="fas fa-save">
                        {{ t('settings.save_all') }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
