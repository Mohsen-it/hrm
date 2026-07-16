<script setup>
import { reactive, ref, computed } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import Badge from '@/Components/ui/Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();
const page = usePage();

const props = defineProps({
    zone: { type: Object, required: true },
    branches: { type: Array, default: () => [] },
});

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const displayName = computed(() => (locale.value === 'en' && props.zone.name_en ? props.zone.name_en : props.zone.name_ar));

const showAddForm = ref(false);
const adding = reactive({
    branch_id: '',
    is_primary: false,
    priority: 0,
    notes: '',
});

const yesNoOptions = [
    { value: true, label: t('common.yes') },
    { value: false, label: t('common.no') },
];

function submitAttach() {
    if (!adding.branch_id) return;
    router.post(route('zones.branches.attach', props.zone.id), adding, {
        preserveScroll: true,
        onSuccess: () => {
            showAddForm.value = false;
            adding.branch_id = '';
            adding.is_primary = false;
            adding.priority = 0;
            adding.notes = '';
        },
    });
}

function detachBranch(branchId) {
    if (!confirm(t('zones.confirm_remove_branch'))) return;
    router.delete(route('zones.branches.detach', [props.zone.id, branchId]), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :title="t('zones.manage_branches')">
        <PageHeader :title="`${t('zones.manage_branches')} · ${displayName}`" :description="zone.code">
            <template #actions>
                <Button variant="secondary" :href="route('zones.show', zone.id)">{{ t('zones.back_to_zone') }}</Button>
            </template>
        </PageHeader>

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">
            <i class="fas fa-check-circle"></i>
            <span>{{ flashSuccess }}</span>
        </div>
        <div v-if="flashError" class="alert alert-danger flex items-center gap-2 mb-4">
            <i class="fas fa-exclamation-circle"></i>
            <span>{{ flashError }}</span>
        </div>

        <div class="card p-6 mb-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-[14px] font-semibold flex items-center gap-2">
                    <i class="fas fa-code-branch text-[var(--color-primary)]"></i>
                    {{ t('zones.assigned_branches') }} ({{ branches.length }})
                </h3>
                <Button variant="primary" :icon="showAddForm ? 'fas fa-times' : 'fas fa-plus'" @click="showAddForm = !showAddForm">
                    {{ showAddForm ? t('common.cancel') : t('zones.add_branch') }}
                </Button>
            </div>

            <form v-if="showAddForm" class="border-t border-[var(--color-hairline)] pt-4 grid grid-cols-1 md:grid-cols-2 gap-3" @submit.prevent="submitAttach">
                <FormInput v-model="adding.branch_id" :label="t('zones.select_branch')" name="branch_id" type="number" required />
                <FormInput v-model="adding.priority" :label="t('zones.priority')" name="priority" type="number" min="0" />
                <FormSelect v-model="adding.is_primary" :label="t('zones.is_primary')" name="is_primary" :options="yesNoOptions" />
                <FormInput v-model="adding.notes" :label="t('zones.pivot_notes')" name="notes" />
                <div class="md:col-span-2 flex justify-end gap-2 mt-2">
                    <Button type="submit" variant="primary" icon="fas fa-save">
                        <i class="fas fa-save"></i>
                        <span>{{ t('zones.add_branch') }}</span>
                    </Button>
                </div>
            </form>

            <div v-if="branches.length === 0" class="text-center py-6 text-[13px] text-[var(--color-ink-muted)]">
                {{ t('zones.no_branches') }}
            </div>
            <table v-else class="w-full text-right mt-3" dir="rtl">
                <thead class="text-[12px] text-[var(--color-ink-muted)] border-b border-[var(--color-hairline)]">
                    <tr>
                        <th class="py-2">{{ t('branches.branch_code') }}</th>
                        <th class="py-2">{{ t('branches.branch_name') }}</th>
                        <th class="py-2">{{ t('zones.city') }}</th>
                        <th class="py-2">{{ t('zones.priority') }}</th>
                        <th class="py-2 text-center">{{ t('zones.is_primary') }}</th>
                        <th class="py-2 text-center">{{ t('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in branches" :key="b.id" class="border-b border-[var(--color-hairline)] hover:bg-[var(--color-surface-1)]">
                        <td class="py-2 font-mono text-[12px]">{{ b.branch_code }}</td>
                        <td class="py-2 font-medium">{{ b.branch_name }}</td>
                        <td class="py-2">{{ b.city || '—' }}</td>
                        <td class="py-2">{{ b.pivot_priority }}</td>
                        <td class="py-2 text-center">
                            <Badge v-if="b.pivot_is_primary" :text="t('zones.primary_branch')" variant="info" />
                            <span v-else class="text-[var(--color-ink-subtle)]">—</span>
                        </td>
                        <td class="py-2 text-center">
                            <button type="button" class="btn-icon text-[var(--color-danger)]" :title="t('zones.remove_branch')" @click="detachBranch(b.id)">
                                <i class="fas fa-unlink"></i>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
