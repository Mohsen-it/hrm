<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { usePageTitle } from '@/composables/usePageTitle';

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { PageHeader, Button, Card, EmptyState } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    user: { type: Object, required: true },
    faceTemplates: { type: Array, default: () => [] },
});

const faceTemplateSets = computed(() => {
    const groups = new Map();

    for (const template of props.faceTemplates) {
        const key = template.set_id || `legacy-${template.device || 'unknown'}`;
        const group = groups.get(key) || {
            id: key,
            device: template.device || 'Unknown device',
            version: template.version,
            updatedAt: template.updated_at,
            components: [],
        };

        group.components.push(template);
        if ((template.updated_at || '') > (group.updatedAt || '')) group.updatedAt = template.updated_at;
        groups.set(key, group);
    }

    return [...groups.values()].sort((a, b) => (b.updatedAt || '').localeCompare(a.updatedAt || ''));
});

function componentLabel(component) {
    return Number.isInteger(component.component_index)
        ? `#${component.component_index}`
        : `#${component.id}`;
}


usePageTitle(t('users.manage_fingerprints'));
</script>

<template>
    
        <PageHeader
            :title="t('users.manage_fingerprints')"
            :description="`${user.name} — ${t('users.fingerprints_description')}`"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('users.show', user.id)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <div v-if="faceTemplates.length" class="p-5 sm:p-6 space-y-4">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm text-mistral-muted">Face-template components</p>
                        <p class="text-2xl font-semibold text-mistral-ink">{{ faceTemplates.length }}</p>
                    </div>
                    <span class="rounded-full bg-mistral-primary/10 px-3 py-1 text-sm font-medium text-mistral-primary">
                        {{ faceTemplateSets.length }} enrollment set{{ faceTemplateSets.length === 1 ? '' : 's' }}
                    </span>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="set in faceTemplateSets"
                        :key="set.id"
                        class="rounded-lg border border-mistral-hairline p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-mistral-ink">{{ set.device }}</p>
                                <p class="text-sm text-mistral-muted">Version {{ set.version }} · {{ set.updatedAt }}</p>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-medium"
                                :class="set.components.length === 15
                                    ? 'bg-mistral-success/10 text-mistral-success'
                                    : 'bg-mistral-warning/10 text-mistral-warning'"
                            >
                                <i :class="set.components.length === 15 ? 'fas fa-check-circle' : 'fas fa-clock'" class="me-1"></i>
                                {{ set.components.length }}/15 components
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="component in set.components"
                                :key="component.id"
                                class="rounded-md bg-mistral-surface px-2 py-1 text-xs font-medium text-mistral-slate"
                            >
                                {{ componentLabel(component) }}
                            </span>
                        </div>
                        <p class="mt-3 text-xs text-mistral-muted">
                            {{ set.components.length === 15
                                ? 'The complete set is queued automatically for enabled compatible devices.'
                                : 'Automatic distribution waits for the complete 15-component enrollment set.' }}
                        </p>
                    </div>
                </div>
            </div>
            <div v-else class="p-5 sm:p-6">
                <EmptyState
                    :title="t('users.no_fingerprints')"
                    :description="t('users.fingerprints_description')"
                />
            </div>
        </Card>
    </template>
