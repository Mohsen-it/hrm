<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    user: { type: Object, required: true },
});
</script>

<template>
    <AppLayout :title="t('users.manage_fingerprints')">
        <PageHeader
            :title="t('users.manage_fingerprints')"
            :description="`${user.name} — ${t('users.fingerprints_description')}`"
        >
            <template #actions>
                <Button variant="secondary" :href="route('users.show', user.id)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <div class="card p-6">
            <EmptyState
                :title="t('users.no_fingerprints')"
                :description="t('users.fingerprints_description')"
            />
        </div>
    </AppLayout>
</template>
